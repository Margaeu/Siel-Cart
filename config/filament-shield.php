<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Resource
    |--------------------------------------------------------------------------
    |
    | Here you may configure the built-in role management resource. You can
    | customize the URL, choose whether to show model paths, group it under
    | a cluster, and decide which permission tabs to display.
    |
    */

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | When your application supports teams, Shield will automatically detect
    | and configure the tenant model during setup. This enables tenant-scoped
    | roles and permissions throughout your application.
    |
    */

    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This value contains the class name of your user model. This model will
    | be used for role assignments and must implement the HasRoles trait
    | provided by the Spatie\Permission package.
    |
    */

    'auth_provider_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Here you may define a super admin that has unrestricted access to your
    | application. You can choose to implement this via Laravel's gate system
    | or as a traditional role with all permissions explicitly assigned.
    |
    */

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel User
    |--------------------------------------------------------------------------
    |
    | When enabled, Shield will create a basic panel user role that can be
    | assigned to users who should have access to your Filament panels but
    | don't need any specific permissions beyond basic authentication.
    |
    */

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Builder
    |--------------------------------------------------------------------------
    |
    | You can customize how permission keys are generated to match your
    | preferred naming convention and organizational standards. Shield uses
    | these settings when creating permission names from your resources.
    |
    | Supported formats: snake, kebab, pascal, camel, upper_snake, lower_snake
    |
    */

    'permissions' => [
        'separator' => ':',
        'case' => 'pascal',
        'generate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Shield can automatically generate Laravel policies for your resources.
    | When merge is enabled, the methods below will be combined with any
    | resource-specific methods you define in the resources section.
    |
    */

    'policies' => [
        'path' => app_path('Policies'),
        // Off so a resource listed under resources.manage gets exactly that
        // list. With merge on, the defaults were added back to every entry, so
        // a read-only resource could never lose its Create/Update/Delete boxes.
        'merge' => false,
        'generate' => true,
        // Replicate and Reorder are not offered: nothing in the panel
        // duplicates a record, and the one reorderable table (banners) is
        // gated on Update:Banner in BannerResource::canReorder(). Note that
        // Filament treats an ability the policy does not define as ALLOWED,
        // so dropping a box here is only safe where no UI exposes the action
        // or the resource class refuses it itself (as those overrides do).
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Shield supports multiple languages out of the box. When enabled, you
    | can provide translated labels for permissions to create a more
    | localized experience for your international users.
    |
    */

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Here you can fine-tune permissions for specific Filament resources.
    | Use the 'manage' array to override the default policy methods for
    | individual resources, giving you granular control over permissions.
    |
    */

    'resources' => [
        'subject' => 'model',
        'manage' => [
            \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
            // Orders are created by checkout, never from the panel
            // (OrderResource::canCreate()), so there is no Create box.
            // Refunds and exchanges are recorded under Returns & Refunds, but
            // recording one is something done to an order, so the permission
            // lives with orders rather than on a resource of its own.
            \App\Filament\Resources\Orders\OrderResource::class => [
                'viewAny',
                'view',
                'update',
                'delete',
                'restore',
                'forceDelete',
                'forceDeleteAny',
                'restoreAny',
                'recordResolution',
            ],
            // No Create, Restore or Force Delete: customers register on the
            // storefront, and restore/force delete are refused in
            // CustomerResource for everyone. Delete stays, because it is what
            // CustomerResource::canDelete() reads to allow "Delete account"
            // (anonymise + soft delete), so tick it sparingly.
            \App\Filament\Resources\Customers\CustomerResource::class => [
                'viewAny',
                'view',
                'update',
                'delete',
            ],
            // Reviews are written by customers and have no edit action in the
            // panel; admins can only look at them and remove them.
            \App\Filament\Resources\Reviews\ReviewResource::class => [
                'viewAny',
                'view',
                'delete',
            ],
            // Reports are filed by customers, so there is no Create page.
            \App\Filament\Resources\Reports\ReportResource::class => [
                'viewAny',
                'view',
                'update',
                'delete',
            ],
            // Not soft-deleted, so Restore and Force Delete have nothing to act on.
            \App\Filament\Resources\Banners\BannerResource::class => [
                'viewAny', 'view', 'create', 'update', 'delete',
            ],
            \App\Filament\Resources\Categories\CategoryResource::class => [
                'viewAny', 'view', 'create', 'update', 'delete',
            ],
            \App\Filament\Resources\Themes\ThemeResource::class => [
                'viewAny', 'view', 'create', 'update', 'delete',
            ],
            \App\Filament\Resources\Users\UserResource::class => [
                'viewAny', 'view', 'create', 'update', 'delete',
            ],
        ],
        'exclude' => [
            // Read-only and super-admin-only, enforced in the resource class
            // itself (see ActivityLogResource), so no box here would ever
            // change who can see or touch the audit trail.
            \App\Filament\Resources\ActivityLogs\ActivityLogResource::class,
            // Access follows the order permissions above -- see
            // ReturnRefundResolutionPolicy -- so there is nothing of its own for
            // Shield to generate, and a generated policy would replace that.
            \App\Filament\Resources\ReturnRefunds\ReturnRefundResource::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Most Filament pages only require view permissions. Pages listed in the
    | exclude array will be skipped during permission generation and won't
    | appear in your role management interface.
    |
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            // The panel's dashboard is App\Filament\Pages\Dashboard — a
            // subclass of Filament's, registered in AdminPanelProvider so the
            // widgets can be picked by role. Excluded for the same reason
            // Filament's own was, and naming the subclass here keeps the
            // generated permission set exactly what it was: the dashboard is
            // every admin's landing page, so a "View:Dashboard" tickbox would
            // only ever be a way to lock someone out of it.
            \App\Filament\Pages\Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Like pages, widgets typically only need view permissions. Add widgets
    | to the exclude array if you don't want them to appear in your role
    | management interface.
    |
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            \Filament\Widgets\AccountWidget::class,
            \Filament\Widgets\FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Sometimes you need permissions that don't map to resources, pages, or
    | widgets. Define any custom permissions here and they'll be available
    | when editing roles in your application.
    |
    */

    'custom_permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery
    |--------------------------------------------------------------------------
    |
    | By default, Shield only looks for entities in your default Filament
    | panel. Enable these options if you're using multiple panels and want
    | Shield to discover entities across all of them.
    |
    */

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Policy
    |--------------------------------------------------------------------------
    |
    | Shield can automatically register a policy for role management itself.
    | This lets you control who can manage roles using Laravel's built-in
    | authorization system. Requires a RolePolicy class in your app.
    |
    */

    'register_role_policy' => true,

];
