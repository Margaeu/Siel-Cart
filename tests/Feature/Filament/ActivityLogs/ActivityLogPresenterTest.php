<?php

namespace Tests\Feature\Filament\ActivityLogs;

use App\Support\ActivityLogPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Formatting rules behind the Activity Logs timeline and detail page.
 */
class ActivityLogPresenterTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function present(array $attributes): ActivityLogPresenter
    {
        $activity = new Activity;
        $activity->forceFill(array_merge([
            'log_name' => 'default',
            'description' => 'updated',
            'event' => 'updated',
            'properties' => [],
        ], $attributes));
        $activity->save();

        return ActivityLogPresenter::for($activity->fresh(['causer', 'subject']));
    }

    public function test_field_names_are_humanized(): void
    {
        $this->assertSame('Is active', ActivityLogPresenter::humanizeKey('is_active'));
        $this->assertSame('Category ID', ActivityLogPresenter::humanizeKey('category_id'));
        $this->assertSame('Low stock threshold', ActivityLogPresenter::humanizeKey('low_stock_threshold'));
    }

    public function test_values_are_formatted_by_type(): void
    {
        $this->assertSame('₱1,250.50', ActivityLogPresenter::formatValue('price', '1250.5'));
        $this->assertSame('₱99.00', ActivityLogPresenter::formatValue('total', 99));
        $this->assertSame('Yes', ActivityLogPresenter::formatValue('is_active', true));
        $this->assertSame('No', ActivityLogPresenter::formatValue('is_featured', 0));
        $this->assertSame('—', ActivityLogPresenter::formatValue('claim_number', null));
        $this->assertSame('—', ActivityLogPresenter::formatValue('claim_number', ''));
        $this->assertSame('Ready for pickup', ActivityLogPresenter::formatValue('status', 'ready_for_pickup'));
        $this->assertSame('12', ActivityLogPresenter::formatValue('stock_quantity', 12));
        $this->assertSame('a, b', ActivityLogPresenter::formatValue('tags', ['a', 'b']));
    }

    public function test_dates_are_shown_in_the_app_timezone(): void
    {
        // 00:30 UTC is 08:30 in Manila.
        $this->assertSame('Sep 17, 2026 8:30 AM', ActivityLogPresenter::formatValue('completed_at', '2026-09-17T00:30:00.000000Z'));
        $this->assertSame('Sep 17, 2026', ActivityLogPresenter::formatValue('pickup_date', '2026-09-17'));
    }

    public function test_credential_keys_are_detected_without_hiding_innocent_fields(): void
    {
        foreach (['password', 'remember_token', 'api_token', 'client_secret', 'session_id', 'two_factor_secret', 'pickup_pin'] as $key) {
            $this->assertTrue(ActivityLogPresenter::isSensitiveKey($key), $key);
        }

        foreach (['shipping_note', 'email', 'pickup_slot', 'description', 'status'] as $key) {
            $this->assertFalse(ActivityLogPresenter::isSensitiveKey($key), $key);
        }

        $this->assertSame('Hidden', ActivityLogPresenter::formatValue('password', 'hash'));

        $nested = ActivityLogPresenter::formatValue('client', ['browser' => 'Firefox', 'auth' => ['access_token' => 'abc123']]);
        $this->assertStringContainsString('Firefox', $nested);
        $this->assertStringNotContainsString('abc123', $nested);
    }

    public function test_an_update_lists_only_the_fields_that_changed(): void
    {
        $changes = $this->present([
            'properties' => [
                'old' => ['name' => 'Mug', 'price' => '100.00', 'stock_quantity' => 5],
                'attributes' => ['name' => 'Mug', 'price' => '100', 'stock_quantity' => 4],
            ],
        ])->changes();

        $this->assertSame(['stock_quantity'], array_column($changes, 'key'));
        $this->assertSame('5', $changes[0]['old']['full']);
        $this->assertSame('4', $changes[0]['new']['full']);
    }

    public function test_a_creation_shows_meaningful_new_values_and_a_deletion_the_previous_ones(): void
    {
        $created = $this->present([
            'event' => 'created',
            'description' => 'created',
            'properties' => ['attributes' => ['name' => 'Mug', 'description' => null, 'is_active' => false]],
        ]);

        $this->assertSame('new', $created->changeMode());
        $this->assertSame(['name', 'is_active'], array_column($created->changes(), 'key'));

        $deleted = $this->present([
            'event' => 'deleted',
            'description' => 'deleted',
            'properties' => ['old' => ['name' => 'Mug', 'sku' => null]],
        ]);

        $this->assertSame('old', $deleted->changeMode());
        $this->assertSame(['name'], array_column($deleted->changes(), 'key'));
        $this->assertSame('Mug', $deleted->changes()[0]['old']['full']);
    }

    public function test_long_values_are_truncated_but_kept_in_full(): void
    {
        $long = Str::repeat('abc ', 60);

        $change = $this->present([
            'properties' => ['old' => ['description' => 'short'], 'attributes' => ['description' => $long]],
        ])->changes()[0];

        $this->assertTrue($change['new']['truncated']);
        $this->assertLessThanOrEqual(ActivityLogPresenter::TRUNCATE_AT + 3, mb_strlen($change['new']['display']));
        $this->assertSame($long, $change['new']['full']);
    }

    public function test_the_actor_falls_back_from_name_to_system_or_guest(): void
    {
        $admin = ActivityLogResourceTest::makeAdmin('Maria', 'Santos');

        $this->assertSame('Maria Santos', $this->present([
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => $admin->getKey(),
        ])->actorName());

        $this->assertSame('System', $this->present([])->actorName());

        $this->assertSame('Guest', $this->present([
            'event' => 'login_failed',
            'description' => 'Failed admin login attempt',
        ])->actorName());

        // A causer that no longer exists is named, not passed off as "System".
        $this->assertSame('User #999', $this->present([
            'causer_type' => $admin->getMorphClass(),
            'causer_id' => 999,
        ])->actorName());
    }

    public function test_subjects_use_the_short_class_name_and_id(): void
    {
        $presenter = $this->present(['subject_type' => 'App\\Models\\Order', 'subject_id' => 105]);

        $this->assertSame('Order #105', $presenter->subjectLabel());
        $this->assertSame('Order', ActivityLogPresenter::modelLabelFor('App\\Models\\Order'));
        $this->assertSame('Product Variant', ActivityLogPresenter::modelLabelFor('App\\Models\\ProductVariant'));
    }
}
