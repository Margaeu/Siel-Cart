<?php

namespace App\Livewire;

use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use Livewire\Component;
use App\Models\OrderItem;
use App\Services\CartService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
     * Disables the button while this page submits. The database cart lock
     * below also protects submissions from separate tabs or requests.
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
                ->with('error', 'Some items in your cart are no longer available. Lower quantities that exceed stock, or remove unavailable items before checkout.');
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
        return $this->cartLines($this->loadCartItems($cartService));
    }

    /**
     * Submission takes current locking reads of the cart, its lines, then
     * products and variants in ID order. Prices and availability therefore
     * come from the same rows that remain locked until the order is saved.
     */
    protected function loadCartItems(CartService $cartService, bool $lock = false): Collection
    {
        $cart = $cartService->getCart();

        if (!$cart) {
            return new Collection;
        }

        if ($lock) {
            $cart = Cart::whereKey($cart->id)
                ->where('customer_id', auth('customer')->id())
                ->lockForUpdate()->first();

            if (!$cart) {
                return new Collection;
            }
        }

        $items = $cart->items()->orderBy('id');

        if ($lock) {
            $items->lockForUpdate();
        }

        return $items->with([
            'product' => function ($query) use ($lock) {
                $query->orderBy('id')->with('primaryImage');
                if ($lock) {
                    $query->lockForUpdate();
                }
            },
            'variant' => function ($query) use ($lock) {
                $query->orderBy('id')->with('images');
                if ($lock) {
                    $query->lockForUpdate();
                }
            },
        ])->get();
    }

    protected function cartLines(Collection $items): array
    {
        return $items->map(function (CartItem $item) {
            return [
                'cart_item_id' => $item->id,
                'product_id'   => $item->product_id,
                'variant_id'   => $item->product_variant_id,
                'name'         => $item->product->name,
                'variant_name' => $item->variant?->name,
                'sku'          => $item->variant?->sku ?? $item->product->sku,
                'price'        => $item->price,
                'image'        => $item->display_image_url,
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

        try {
            $order = DB::transaction(function () use ($cartService) {
                // Do not use the browser's cart or a snapshot read before the
                // transaction. A second submission waits, then sees no items.
                $items = $this->loadCartItems($cartService, lock: true);
                if ($items->isEmpty()) {
                    return null;
                }

                $this->reserveStock($items);
                $cart = $this->cartLines($items);
                $this->cart = $cart;
                $subtotal = $this->subtotalFor($cart);
                $customer = auth('customer')->user();

                $order = Order::create([
                    'customer_id'          => $customer->id,
                    'subtotal'             => $subtotal,
                    'total'                => $subtotal,
                    'pickup_location'      => self::PICKUP_LOCATION,
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

                // Consume only the locked lines included in this order.
                // An item added separately must not disappear unpurchased.
                CartItem::whereIn('id', $items->modelKeys())->delete();

                return $order;
            });
        } catch (\DomainException $e) {
            $this->placingOrder = false;
            return redirect()->route('cart.index')->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            $this->placingOrder = false;
            report($e);
            session()->flash('error', 'We could not place your order. Please try again.');
            return;
        }

        if (!$order) {
            $this->placingOrder = false;
            return redirect()->route('cart.index');
        }

        $successMessage = 'Thank you for your purchase! Please wait for an email confirmation to know when your order is being processed.';

        $this->dispatch('cart-updated');

        // No claim number is quoted here. One is only issued once the order
        // is ready to be collected, so the customer is told to wait for it.
        return redirect()
            ->route('customer.orders.show', $order->id)
            ->with([
                'order_success_title' => 'Order placed successfully!',
                'order_success_message' => $successMessage,
            ]);
    }

    /**
     * Validate and reserve from the locked product/variant models. Grouping
     * also handles older carts containing duplicate lines for the same item.
     */
    protected function reserveStock(Collection $items): void
    {
        foreach ($items as $item) {
            if (!$item->is_purchasable || $item->price < 0) {
                $name = $item->product?->name ?? 'This product';
                throw new \DomainException(
                    "{$name} is no longer available. Please remove it from your cart."
                );
            }

            if ($item->quantity < 1) {
                throw new \DomainException('Every item must have a quantity of at least 1. Please update your cart.');
            }
        }

        $groups = $items->groupBy(fn (CartItem $item) => $item->product_variant_id
            ? 'variant-'.$item->product_variant_id
            : 'product-'.$item->product_id);

        foreach ($groups as $group) {
            $item = $group->first();
            $stockHolder = $item->variant ?? $item->product;
            $quantity = $group->sum('quantity');

            if ($stockHolder->stock_quantity < $quantity) {
                throw new \DomainException(
                    "Only {$stockHolder->stock_quantity} left of {$item->product->name}. Please lower the quantity in your cart."
                );
            }

            $stockHolder->decrement('stock_quantity', $quantity);
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
        ])->layout('components.layouts.front-end-layout', ['title' => 'Checkout - '.config('app.name')]);
    }
}
