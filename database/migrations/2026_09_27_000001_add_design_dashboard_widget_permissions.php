<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISSIONS = [
        'View:DesignOverviewStats',
        'View:DesignOverviewBannerPreview',
        'View:DesignOverviewThemeCard',
        'View:DesignOverviewRecentDesigns',
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $name) {
            Permission::findOrCreate($name, 'web');
        }

        // Enable the new design widgets for existing super admins on upgrade.
        // Subsequent changes in Roles remain authoritative.
        Role::where('name', 'super_admin')->where('guard_name', 'web')->first()
            ?->givePermissionTo(self::PERMISSIONS);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', self::PERMISSIONS)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
