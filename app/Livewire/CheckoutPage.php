<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Product;
use Livewire\Component;
use App\Models\OrderItem;
use App\Models\ProductVariant;
use App\Services\CartService;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckoutPage extends Component
{
    /**
     * Orders are always collected in person and always paid in cash at
     * the counter, so there is nothing for the customer to choose. These
     * are written by the server and never read from the request.
     */
    public const PICKUP_LOCATION = 'UBAP_office';
    public const PICKUP_LOCATION_LABEL = 'UBAP Office';
    public const PAYMENT_METHOD = 'cash_on_pickup';
    public const PAYMENT_METHOD_LABEL = 'Cash on Pickup';

    /**
     * The cart as shown on the page. It is rebuilt from the database
     * before the order is written, so nothing coming back from the
     * browser is ever used to price an order.
     */
    public array $cart = [];

    /**
     * Stops a second order being created when the customer double-taps
     * Place Order before the first request has redirected away.
     */
    public bool $placingOrder = false;

    public function mount(CartService $cartService)
    {
        // Load the customer's permanent cart from the database.
        $this->cart = $this->buildCartFromDatabase($cartService);

        if (empty($this->cart)) {
            return redirect()->route('cart.index');
        }

        // Never allow checkout to start with out-of-stock items.
        if ($cartService->hasUnavailableItems()) {
            return redirect()->route('cart.index')
                ->with('error', 'Some items in your cart are no longer available. Please remove them before checkout.');
        }
    }

    /**
     * Convert the database cart into the flat array the
     * checkout view and order creation already expect.
     *
     * Pricing comes from CartItem's price accessor, which is the same
     * one the cart page uses. That keeps the amount shown in the cart
     * and the amount actually charged in sync.
     */
    protected function buildCartFromDatabase(CartService $cartService): array
    {
        $cart = $cartService->getCart();

        if (!$cart) {
            return [];
        }

        $cart->load([           
            'items.product.primaryImage',
            'items.variant.images',
        ]);

        return $cart->items->map(function ($item) {
            return [
                'cart_item_id' => $item->id,
                'product_id'   => $item->product_id,
                'variant_id'   => $item->product_variant_id,
                'name'         => $item->product->name,
                'variant_name' => $item->variant?->name,
                'sku'          => $item->variant?->sku ?? $item->product->sku,
                'price'        => $item->price,
                'image'        => ($item->variant?->images->first() ?? $item->product->primaryImage)?->url,
                'quantity'     => $item->quantity,
            ];
        })->all();
    }

    /**
     * Add up the merchandise total for a cart.
     *
     * Nothing is added to or taken off this figure. There is no shipping,
     * no tax and no discount, so the order total is the same number.
     */
    protected function subtotalFor(array $cart): float
    {
        return round(array_sum(array_map(
            fn ($item) => $item['price'] * $item['quantity'],
            $cart,
        )), 2);
    }

    public function placeOrder(CartService $cartService)
    {
        if ($this->placingOrder) {
            return;
        }

        $this->placingOrder = true;

        // Price the order from the database rather than from $this->cart.
        // That property round-trips through the browser, and the page may
        // also have sat open while stock or prices changed.
        $cart = $this->buildCartFromDatabase($cartService);
        $this->cart = $cart;

        if (empty($cart)) {
            $this->placingOrder = false;
            return redirect()->route('cart.index');
        }

        if ($cartService->hasUnavailableItems()) {
            $this->placingOrder = false;
            return redirect()->route('cart.index')
                ->with('error', 'Some items in your cart are no longer available. Please remove them before checkout.');
        }

        try {
            $order = DB::transaction(function () use ($cart, $cartService) {
                $subtotal = $this->subtotalFor($cart);
                $customer = auth('customer')->user();

                $order = Order::create([
                    'customer_id'          => $customer->id,
                    'subtotal'             => $subtotal,
                    'total'                => $subtotal,
                    'pickup_location'      => self::PICKUP_LOCATION,
                    'claimant_name'  => $customer->name,
                    'claimant_phone' => $customer->phone,
                    // pickup_date is left null on purpose. The UBAP admins
                    // schedule it later, once the order is ready to claim.
                    'payment_method'       => self::PAYMENT_METHOD,
                    // Cash changes hands at the counter, so the order is
                    // only marked paid when staff release it.
                    'payment_status'       => 'pending',
                    'status'               => 'pending',
                ]);

                foreach ($cart as $item) {
                    OrderItem::create([
                        'order_id'           => $order->id,
                        'product_id'         => $item['product_id'],
                        'product_variant_id' => $item['variant_id'],
                        // Name, SKU, price, and image are copied instead of looked
                        // up later, so the order history stays intact if deleted.
                        'product_name'       => $item['name'],
                        'product_sku'        => $item['sku'],
                        'variant_name'       => $item['variant_name'],
                        'product_image'      => $item['image'],
                        'price'              => $item['price'],
                        'quantity'           => $item['quantity'],
                        'subtotal'           => round($item['price'] * $item['quantity'], 2),
                    ]);
                }

                $this->reserveStock($cart);

                // Clearing inside the transaction means an order can never
                // end up existing alongside the cart that produced it.
                $cartService->clearCart();

                // Dispatch order confirmation email to the customer
                Mail::to($customer->email)->send(new OrderConfirmation($order));

                return $order;
            });
        } catch (\DomainException $e) {
            // Raised by reserveStock. The message names the product and is
            // written for the customer, so it is safe to show.
            $this->placingOrder = false;
            session()->flash('error', $e->getMessage());
            return;
        } catch (\Throwable $e) {
            $this->placingOrder = false;
            report($e);
            session()->flash('error', 'We could not place your order. Please try again.');
            return;
        }

        $this->dispatch('cart-updated');

        // No claim number is quoted here. One is only issued once the order
        // is ready to be collected, so the customer is told to wait for it.
        return redirect()
            ->route('customer.orders.show', $order->id)
            ->with([
                'order_success_title' => 'Order placed successfully!',
                'order_success_message' => 'Thank you for your purchase! Please wait for an email confirmation to know when your order is being processed.',
            ]);
    }

    /**
     * Take the ordered quantities out of stock.
     *
     * Rows are locked before they are read, so two customers checking out
     * at the same moment cannot both pass the stock check and sell the
     * same last unit twice. The lock is held until the transaction commits.
     *
     * Locks are always taken in the same order, so two concurrent
     * checkouts can never each hold the row the other is waiting for.
     */
    protected function reserveStock(array $cart): void
    {
        $lines = collect($cart)
            ->sortBy(fn ($line) => sprintf(
                '%s-%012d',
                $line['variant_id'] ? 'v' : 'p',
                $line['variant_id'] ?? $line['product_id'],
            ))
            ->values();

        foreach ($lines as $line) {
            // A variant carries its own stock, so that is the row to lock
            // whenever the customer picked one.
            $stockHolder = $line['variant_id']
                ? ProductVariant::whereKey($line['variant_id'])->lockForUpdate()->first()
                : Product::whereKey($line['product_id'])->lockForUpdate()->first();

            if (!$stockHolder) {
                throw new \DomainException(
                    "{$line['name']} is no longer available. Please remove it from your cart."
                );
            }

            if ($stockHolder->stock_quantity < $line['quantity']) {
                throw new \DomainException(
                    "Only {$stockHolder->stock_quantity} left of {$line['name']}. Please lower the quantity in your cart."
                );
            }

            $stockHolder->decrement('stock_quantity', $line['quantity']);
        }
    }

    public function render()
    {
        $subtotal = $this->subtotalFor($this->cart);

        return view('livewire.checkout-page', [
            // Section 1 shows these read-only. The account is the source of
            // truth for who bought the order, which is a separate question
            // from who is going to collect it.
            'customer'       => auth('customer')->user(),
            'subtotal'       => $subtotal,
            'total'          => $subtotal,
            'pickupLocation' => self::PICKUP_LOCATION_LABEL,
            'paymentMethod'  => self::PAYMENT_METHOD_LABEL,
        ]);
    }
}