<?php

namespace Tests\Feature\Filament\ActivityLogs;

use App\Filament\Pages\Auth\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Sign-ins are driven through the real Filament login page. That page is a
 * Livewire component, so the submit arrives on Livewire's update endpoint,
 * never on /admin/* — the listeners used to require an admin/* path and
 * silently dropped every real admin login and failed attempt.
 */
class AdminAuthActivityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function panelAdmin(): \App\Models\User
    {
        $user = ActivityLogResourceTest::makeAdmin('Login', 'Tester');
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        Activity::query()->delete();

        return $user;
    }

    public function test_a_successful_admin_login_is_logged_once(): void
    {
        $admin = $this->panelAdmin();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($admin, 'web');

        $logins = Activity::query()->where('log_name', 'authentication')->where('event', 'login')->get();
        $this->assertCount(1, $logins);
        $this->assertTrue($logins->first()->causer->is($admin));
        $this->assertSame($admin->email, $logins->first()->properties['email']);
    }

    public function test_a_wrong_password_on_the_admin_login_is_logged_as_a_failed_attempt(): void
    {
        $admin = $this->panelAdmin();

        Livewire::test(Login::class)
            ->fillForm(['email' => $admin->email, 'password' => 'not-the-password'])
            ->call('authenticate');

        $this->assertGuest('web');

        $failures = Activity::query()->where('log_name', 'authentication')->where('event', 'login_failed')->get();
        $this->assertCount(1, $failures);
        $this->assertSame($admin->email, $failures->first()->properties['attempted_email']);
        $this->assertSame(0, Activity::query()->where('event', 'login')->count());
    }
}
