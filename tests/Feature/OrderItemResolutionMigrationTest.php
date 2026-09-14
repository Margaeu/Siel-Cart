<?php

namespace Tests\Feature;

use App\Enums\OrderItemResolutionReason;
use App\Enums\OrderItemResolutionType;
use App\Models\Order;
use App\Models\OrderItemResolution;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Concerns\BuildsResolvableOrders;
use Tests\TestCase;

/**
 * The retired self-service workflow left a refund_requests table and two order
 * statuses behind. None of it may be dropped or rewritten, and only outcomes
 * that name one order line unambiguously are carried into the new records.
 */
class OrderItemResolutionMigrationTest extends TestCase
{
    use BuildsResolvableOrders, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-13 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function runMigration(string $file): void
    {
        (require database_path("migrations/{$file}"))->up();
    }
}
