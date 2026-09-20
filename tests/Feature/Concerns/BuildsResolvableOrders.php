<?php

namespace Tests\Feature\Concerns;

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReturnRefundResolution;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ReturnRefundResolutionService;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

/**
 * Collected orders, and the admins who record what UBAP decided about them.
 */
trait BuildsResolvableOrders
{
    private ?User $resolvingAdmin = null;

    protected function adminWithPermissions(array $permissions, array $attributes = []): User
    {
        $admin = User::factory()->create($attributes);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        return $admin->givePermissionTo($permissions);
    }

    /** An admin who can open orders and record their outcomes. */
    protected function recordingAdmin(): User
    {
        return $this->resolvingAdmin ??= $this->adminWithPermissions(
            ['ViewAny:Order', 'View:Order', 'RecordResolution:Order'],
            ['first_name' => 'Maria', 'last_name' => 'Santos'],
        );
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'is_active' => true,
            'has_variants' => false,
            'price' => 180,
            'stock_quantity' => 10,
        ], $attributes));
    }

    /** @return array{0: Product, 1: ProductVariant, 2: ProductVariant} */
    protected function shirtWithSizes(int $mediumStock, int $largeStock): array
    {
        $shirt = $this->product(['name' => 'CLSU Shirt', 'has_variants' => true, 'price' => 350, 'stock_quantity' => 0]);

        $variant = fn (string $name, int $stock): ProductVariant => ProductVariant::factory()->for($shirt)->create([
            'name' => $name,
            'price' => 350,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        return [$shirt, $variant('Green / Medium', $mediumStock), $variant('Green / Large', $largeStock)];
    }

    /** Collected two days before the frozen "now" of these tests. */
    protected function completedOrder(array $attributes = [], ?Customer $customer = null): Order
    {
        return Order::create(array_merge([
            'customer_id' => ($customer ?? Customer::factory()->create())->id,
            'subtotal' => 0,
            'total' => 0,
            'payment_method' => 'cash_on_pickup',
            'payment_status' => 'paid',
            'status' => 'completed',
            'completed_at' => now()->subDays(2),
            'claimant_name' => 'Juan Dela Cruz',
            'claimant_phone' => '09171234567',
        ], $attributes));
    }

    /** Add a line the way checkout snapshots one, and keep the totals adding up. */
    protected function line(Order $order, Product $product, ?ProductVariant $variant = null, int $quantity = 1): OrderItem
    {
        $price = (float) ($variant?->price ?? $product->price);

        $line = OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant?->id,
            'product_name' => $product->name,
            'product_sku' => $variant?->sku ?? $product->sku,
            'variant_name' => $variant?->name,
            'price' => $price,
            'quantity' => $quantity,
            'subtotal' => round($price * $quantity, 2),
        ]);

        $total = $order->items()->sum('subtotal');
        $order->update(['subtotal' => $total, 'total' => $total]);

        return $line;
    }

    protected function resolutions(): ReturnRefundResolutionService
    {
        return app(ReturnRefundResolutionService::class);
    }

    protected function refund(OrderItem $line, float $amount, int $quantity = 1, array $extra = []): ReturnRefundResolution
    {
        return $this->resolutions()->recordRefund($line, $this->recordingAdmin(), array_merge([
            'reason' => 'defective',
            'quantity' => $quantity,
            'refund_amount' => $amount,
            'processed_at' => now()->toDateString(),
        ], $extra));
    }

    /**
     * An exchange for seller error: UBAP released the wrong item, and it came
     * back in the given condition.
     */
    protected function exchange(
        OrderItem $line,
        Product $released,
        ?ProductVariant $releasedVariant = null,
        string $condition = 'sellable',
        int $quantity = 1,
        array $extra = [],
    ): ReturnRefundResolution {
        return $this->resolutions()->recordExchange($line, $this->recordingAdmin(), array_merge([
            'reason' => 'seller_error',
            'quantity' => $quantity,
            'incorrect_product_id' => $released->id,
            'incorrect_variant_id' => $releasedVariant?->id,
            'incorrect_item_condition' => $condition,
            'processed_at' => now()->toDateString(),
        ], $extra));
    }

    /**
     * An exchange because the item handed over -- the one that was ordered --
     * was defective or damaged. There is no item released in error to name.
     */
    protected function exchangeFaultyItem(
        OrderItem $line,
        string $reason = 'defective',
        int $quantity = 1,
        array $extra = [],
    ): ReturnRefundResolution {
        return $this->resolutions()->recordExchange($line, $this->recordingAdmin(), array_merge([
            'reason' => $reason,
            'quantity' => $quantity,
            'processed_at' => now()->toDateString(),
        ], $extra));
    }

    protected function assertRejected(string $field, callable $record): void
    {
        try {
            $record();
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                $field,
                $exception->errors(),
                'Rejected, but for a different reason: '.json_encode($exception->errors()),
            );

            return;
        }

        $this->fail("Expected the resolution to be rejected on [{$field}].");
    }
}
