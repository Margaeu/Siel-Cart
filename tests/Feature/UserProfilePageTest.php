<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_renders_as_one_livewire_root(): void
    {
        config(['app.debug' => true]);

        $role = Role::findOrCreate('super_admin', 'web');
        foreach (['ViewAny:User', 'View:User'] as $permission) {
            $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
        }

        $actor = User::query()->create([
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $actor->assignRole($role);

        $target = User::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'email' => 'ana@example.test',
            'password' => 'password',
            'is_active' => true,
        ]);
        $target->assignRole(Role::findOrCreate('ubap', 'web'));

        $this->actingAs($actor);
        Filament::setCurrentPanel('admin');

        Livewire::test(ViewUser::class, ['record' => $target->getKey()])
            ->assertOk()
            ->assertSee('Ana Santos')
            ->assertSee('Record history');    
    }
}
