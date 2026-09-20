<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserFormPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function actAsSuperAdmin(): User
    {
        $role = Role::findOrCreate('super_admin', 'web');
        foreach (['ViewAny:User', 'View:User', 'Create:User', 'Update:User'] as $permission) {
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

        $this->actingAs($actor);
        Filament::setCurrentPanel('admin');

        return $actor;
    }

    private function makeTarget(): User
    {
        return User::query()->create([
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'email' => 'ana@example.test',
            'password' => 'original-secret',
            'is_active' => true,
        ]);
    }

    public function test_editing_account_details_without_a_password_keeps_the_current_one(): void
    {
        $this->actAsSuperAdmin();
        $target = $this->makeTarget();

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm([
                'first_name' => 'Anna',
                'password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $target->refresh();

        $this->assertSame('Anna', $target->first_name);
        $this->assertTrue(Hash::check('original-secret', $target->password));
    }

    public function test_editing_with_a_new_password_replaces_it(): void
    {
        $this->actAsSuperAdmin();
        $target = $this->makeTarget();

        Livewire::test(EditUser::class, ['record' => $target->getKey()])
            ->fillForm(['password' => 'brand-new-secret'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('brand-new-secret', $target->refresh()->password));
    }

    public function test_creating_a_user_still_requires_a_password(): void
    {
        $this->actAsSuperAdmin();

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => 'new@example.test',
                'password' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['password' => 'required']);
    }
}
