<?php

namespace Tests\Feature\Filament\ActivityLogs;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The audit trail is always read newest-first or counted by day, and
 * spatie/laravel-activitylog's own migration indexes only log_name and the two
 * morph pairs. Without the index added by
 * 2026_09_27_100000_add_created_at_index_to_activity_log_table, both
 * ActivityLogResource's table (defaultSort created_at desc) and the super
 * admin's two dashboard widgets scan the whole table -- EXPLAIN on the live
 * MySQL table reported type=ALL, possible_keys=NULL and "Using filesort".
 *
 * These assert the index is actually there and that the migration rolls back,
 * because a migration that cannot be reversed is one that cannot be safely
 * deployed.
 */
class ActivityLogIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The declared column order matters: created_at leads so the date-range
     * scan is served by a leftmost prefix, and id follows so
     * "ORDER BY created_at DESC, id DESC" is index-ordered by definition rather
     * than by relying on InnoDB appending the primary key.
     */
    public function test_the_activity_log_is_indexed_by_created_at_then_id(): void
    {
        $columns = collect(Schema::getIndexes('activity_log'))
            ->pluck('columns');

        $this->assertTrue(
            $columns->contains(['created_at', 'id']),
            'activity_log should carry a (created_at, id) index; found: '
                .$columns->map(fn (array $c): string => '('.implode(', ', $c).')')->implode(' '),
        );
    }

    public function test_the_index_migration_is_reversible(): void
    {
        $migration = $this->indexMigration();

        $migration->down();
        $this->assertFalse($this->hasCreatedAtIndex(), 'down() should drop the index');

        $migration->up();
        $this->assertTrue($this->hasCreatedAtIndex(), 'up() should restore the index');
    }

    private function indexMigration(): Migration
    {
        return require database_path(
            'migrations/2026_09_27_100000_add_created_at_index_to_activity_log_table.php',
        );
    }

    private function hasCreatedAtIndex(): bool
    {
        return collect(Schema::getIndexes('activity_log'))
            ->pluck('columns')
            ->contains(['created_at', 'id']);
    }
}
