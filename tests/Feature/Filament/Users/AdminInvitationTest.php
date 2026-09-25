<?php

namespace Tests\Feature\Filament\Users;

use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Filament\Pages\Auth\ResetPassword;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Customer;
use App\Models\User;
use App\Notifications\AdminInvitation;
use App\Notifications\AdminResetPassword;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Feature\Filament\ActivityLogs\ActivityLogResourceTest;
use Tests\TestCase;

/**
 * A super admin creates an administrator without ever knowing their password:
 * the account gets a random placeholder, and the new admin is emailed a
 * `users`-broker link to the panel's own reset page to choose one.
 *
 * Permissions are real Shield permissions here, not a blanket Gate::before, so
 * the super-admin-only rule on UserResource::canCreate() is actually exercised.
 */
class AdminInvitationTest extends TestCase
{
    use RefreshDatabase;

    private const PERMISSIONS = ['ViewAny:User', 'View:User', 'Create:User', 'Update:User'];

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (['super_admin', 'ubap', 'stratcom'] as $role) {
            // Every role holds every User permission, Create:User included, so
            // the only thing separating them in these tests is the role itself.
            Role::findOrCreate($role, 'web')->givePermissionTo(self::PERMISSIONS);
        }

        $this->superAdmin = ActivityLogResourceTest::makeAdmin('Super', 'Admin');
        $this->superAdmin->assignRole('super_admin');
    }

    private function createAdminThroughThePanel(string $email = 'new.admin@example.com', string $role = 'ubap'): User
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Nina',
                'last_name' => 'Reyes',
                'email' => $email,
                'roles' => [Role::findByName($role, 'web')->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        return User::where('email', $email)->firstOrFail();
    }

    private function sentInvitation(User $admin): AdminInvitation
    {
        $sent = null;

        Notification::assertSentTo($admin, AdminInvitation::class, function (AdminInvitation $notification) use (&$sent): bool {
            $sent = $notification;

            return true;
        });

        return $sent;
    }

    public function test_super_admin_creates_an_admin_without_supplying_a_password(): void
    {
        Notification::fake();

        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->assertFormFieldHidden('password');

        $admin = $this->createAdminThroughThePanel();

        $this->assertSame('Nina', $admin->first_name);
        $this->assertTrue($admin->is_active);
        $this->assertNotEmpty($admin->password);
        $this->assertTrue(Hash::isHashed($admin->password));
        $this->assertSame(['ubap'], $admin->getRoleNames()->all());
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));

        // Role assignment still goes through syncRolesAndLog().
        $this->assertTrue(Activity::query()
            ->where('subject_type', $admin->getMorphClass())
            ->where('subject_id', $admin->id)
            ->where('description', 'Changed roles')
            ->exists());
    }

    public function test_a_role_is_required_when_creating_an_admin(): void
    {
        Notification::fake();

        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Nina',
                'last_name' => 'Reyes',
                'email' => 'no.role@example.com',
                'roles' => [],
            ])
            ->call('create')
            ->assertHasFormErrors(['roles' => 'required']);

        $this->assertDatabaseMissing('users', ['email' => 'no.role@example.com']);
        Notification::assertNothingSent();
    }

    public function test_the_new_admin_is_emailed_a_filament_setup_link_from_the_users_broker(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $invitation = $this->sentInvitation($admin);

        Notification::assertSentToTimes($admin, AdminInvitation::class, 1);
        $this->assertSame(['mail'], $invitation->via($admin));

        // The token is live under the users broker, and the customers broker has
        // no account to resolve it against.
        $this->assertTrue(Password::broker('users')->tokenExists($admin, $invitation->token));
        $this->assertNull(Password::broker('customers')->getUser(['email' => $admin->email]));

        $url = $invitation->setupUrl($admin);
        $expectedPath = parse_url(route('filament.admin.auth.password-reset.reset'), PHP_URL_PATH);

        $this->assertSame($expectedPath, parse_url($url, PHP_URL_PATH));
        $this->assertStringStartsWith('/admin/', $expectedPath);
        $this->assertNotSame(parse_url(route('password.reset', $invitation->token), PHP_URL_PATH), parse_url($url, PHP_URL_PATH));
        $this->assertTrue(URL::hasValidSignature(Request::create($url)));

        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame($admin->email, $query['email']);
        $this->assertSame($invitation->token, $query['token']);
        $this->assertSame('1', $query['invitation']);
    }

    public function test_the_invitation_mail_is_branded_and_carries_no_password(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $invitation = $this->sentInvitation($admin);

        $mail = $invitation->toMail($admin);
        $html = $mail->render();
        $text = view($mail->view['text'], $mail->viewData)->render();

        foreach ([$html, $text] as $rendered) {
            $this->assertStringContainsString('Nina Reyes', $rendered);
            $this->assertStringContainsString($admin->email, $rendered);
            $this->assertStringContainsString('3 minutes', $rendered);
            $this->assertStringContainsString(Filament::getPanel('admin')->getLoginUrl(), $rendered);
        }

        $this->assertStringContainsString('Set your password', $html);
        $this->assertStringContainsString(htmlspecialchars($invitation->setupUrl($admin)), $html);
        $this->assertStringContainsString($invitation->setupUrl($admin), $text);

        // The placeholder is never known to the test either, so prove it isn't
        // in the mail the only way possible: no word of either rendering is the
        // password the account was saved with.
        $words = preg_split('/[\s<>"\']+/', $html.' '.strip_tags($html).' '.$text, -1, PREG_SPLIT_NO_EMPTY);

        foreach (array_unique($words) as $word) {
            $this->assertFalse(Hash::check($word, $admin->password), 'The invitation mail contains the account password.');
        }
    }

    public function test_admin_invitations_last_3_minutes_while_the_customer_broker_is_unchanged(): void
    {
        $this->assertSame(3, config('auth.passwords.users.expire'));
        $this->assertSame(3, AdminInvitation::expireMinutes());
        $this->assertSame(3, config('auth.passwords.customers.expire'));
        $this->assertSame('users', Filament::getPanel('admin')->getAuthPasswordBroker());
    }

    public function test_the_panel_has_password_reset_but_not_registration(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue($panel->hasPasswordReset());
        $this->assertFalse($panel->hasRegistration());
    }

    public function test_the_invited_admin_sets_a_password_once_and_can_sign_in(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $token = $this->sentInvitation($admin)->token;

        // The invitee opens the link in their own browser: no signed-in super
        // admin, and none of the create page's toasts left in the session.
        Auth::guard('web')->logout();
        session()->flush();

        Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token, 'invitation' => true])
            ->assertSee('Set your password')
            ->assertSee('Set password')
            ->assertDontSee('Reset your password')
            ->fillForm([
                'password' => 'Chosen-by-Nina-2026',
                'passwordConfirmation' => 'Chosen-by-Nina-2026',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors()
            ->assertNotified('Your password has been set.');

        $this->assertTrue(Hash::check('Chosen-by-Nina-2026', $admin->fresh()->password));
        $this->assertFalse(Password::broker('users')->tokenExists($admin->fresh(), $token));
        $this->assertTrue(Auth::guard('web')->validate(['email' => $admin->email, 'password' => 'Chosen-by-Nina-2026']));

        // The same link does not work a second time. The token is now consumed,
        // so mount() itself catches it and the branded "link expired" page
        // renders before the form ever would - no Livewire round trip needed.
        session()->flush();

        $reusedUrl = Filament::getPanel('admin')->getResetPasswordUrl($token, $admin->fresh());

        $this->get($reusedUrl)
            ->assertStatus(419)
            ->assertViewIs('errors.link-expired')
            ->assertSee('This reset link has expired');

        $this->assertTrue(Hash::check('Chosen-by-Nina-2026', $admin->fresh()->password));
    }

    public function test_an_expired_or_consumed_invitation_shows_the_branded_expired_page_instead_of_the_form(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $token = $this->sentInvitation($admin)->token;

        Password::broker('users')->deleteToken($admin);

        Auth::guard('web')->logout();
        session()->flush();

        $expiredUrl = Filament::getPanel('admin')->getResetPasswordUrl($token, $admin, ['invitation' => 1]);

        $this->get($expiredUrl)
            ->assertStatus(419)
            ->assertViewIs('errors.link-expired')
            ->assertSee('This invitation has expired')
            ->assertSee('Ask a super admin to resend your invitation');
    }

    public function test_an_expired_admin_reset_link_shows_the_branded_expired_page(): void
    {
        Notification::fake();

        $admin = ActivityLogResourceTest::makeAdmin('Known', 'Admin');
        $admin->assignRole('ubap');
        $token = Password::broker('users')->createToken($admin);

        Password::broker('users')->deleteToken($admin);

        $expiredUrl = Filament::getPanel('admin')->getResetPasswordUrl($token, $admin);

        $this->get($expiredUrl)
            ->assertStatus(419)
            ->assertViewIs('errors.link-expired')
            ->assertSee('This reset link has expired')
            ->assertSee('Request a new reset link')
            ->assertSee(Filament::getPanel('admin')->getRequestPasswordResetUrl(), false)
            ->assertDontSee('This invitation has expired');
    }

    public function test_a_forgot_password_link_keeps_the_reset_wording(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $token = Password::broker('users')->createToken($admin);

        Auth::guard('web')->logout();
        session()->flush();

        // No `invitation` flag: this is the link RequestPasswordReset mails.
        Livewire::test(ResetPassword::class, ['email' => $admin->email, 'token' => $token])
            ->assertSee('Reset your password')
            ->assertDontSee('Set your password')
            ->fillForm([
                'password' => 'Forgotten-and-reset-2026',
                'passwordConfirmation' => 'Forgotten-and-reset-2026',
            ])
            ->call('resetPassword')
            ->assertHasNoFormErrors()
            ->assertNotified(__(Password::PASSWORD_RESET));
    }

    public function test_non_super_admins_cannot_create_admins_even_with_the_create_permission(): void
    {
        Notification::fake();

        foreach (['ubap', 'stratcom'] as $role) {
            $staff = ActivityLogResourceTest::makeAdmin('Staff', ucfirst($role));
            $staff->assignRole($role);
            $this->assertTrue($staff->can('Create:User'));

            $this->actingAs($staff);

            Livewire::test(CreateUser::class)->assertForbidden();
            Livewire::test(ListUsers::class)->assertActionHidden('create');
        }

        Notification::assertNothingSent();
    }

    public function test_non_super_admins_cannot_resend_invitations(): void
    {
        $target = ActivityLogResourceTest::makeAdmin('Target', 'Admin');
        $target->assignRole('stratcom');

        $staff = ActivityLogResourceTest::makeAdmin('Staff', 'Member');
        $staff->assignRole('ubap');

        Notification::fake();

        $this->actingAs($staff);

        $list = Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('resendInvitation')->table($target));

        // Hidden is not just cosmetic: the action itself refuses this admin, and
        // Filament will not mount an unauthorized action.
        $this->assertFalse($list->instance()->getAction([['name' => 'resendInvitation', 'context' => ['table' => true, 'recordKey' => $target->getKey()]]])->isAuthorized());

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $target->email]);
    }

    public function test_resending_an_invitation_replaces_the_previous_token(): void
    {
        Notification::fake();

        $admin = $this->createAdminThroughThePanel();
        $firstToken = $this->sentInvitation($admin)->token;

        Livewire::test(ListUsers::class)
            ->assertActionVisible(TestAction::make('resendInvitation')->table($admin))
            ->callAction(TestAction::make('resendInvitation')->table($admin))
            ->assertNotified('Invitation sent');

        Notification::assertSentToTimes($admin, AdminInvitation::class, 2);

        $tokens = [];
        Notification::assertSentTo($admin, AdminInvitation::class, function (AdminInvitation $notification) use (&$tokens): bool {
            $tokens[] = $notification->token;

            return true;
        });
        $secondToken = end($tokens);

        $this->assertNotSame($firstToken, $secondToken);
        $this->assertFalse(Password::broker('users')->tokenExists($admin, $firstToken));
        $this->assertTrue(Password::broker('users')->tokenExists($admin, $secondToken));
        $this->assertSame(1, DB::table('password_reset_tokens')->where('email', $admin->email)->count());
    }

    public function test_resend_is_not_offered_to_accounts_that_cannot_use_the_link(): void
    {
        $inactive = ActivityLogResourceTest::makeAdmin('Inactive', 'Admin');
        $inactive->assignRole('ubap');
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('resendInvitation')->table($inactive));
    }

    public function test_a_super_admin_cannot_resend_an_invitation_to_their_own_account(): void
    {
        Notification::fake();

        $other = ActivityLogResourceTest::makeAdmin('Other', 'Admin');
        $other->assignRole('ubap');

        $this->actingAs($this->superAdmin);

        $list = Livewire::test(ListUsers::class)
            ->assertActionHidden(TestAction::make('resendInvitation')->table($this->superAdmin))
            ->assertActionVisible(TestAction::make('resendInvitation')->table($other));

        // Refused, not merely hidden.
        $this->assertFalse($list->instance()->getAction([['name' => 'resendInvitation', 'context' => ['table' => true, 'recordKey' => $this->superAdmin->getKey()]]])->isAuthorized());

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $this->superAdmin->email]);
    }

    public function test_a_mail_failure_keeps_the_account_and_tells_the_super_admin(): void
    {
        Exceptions::fake();
        Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP unavailable'));

        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Nina',
                'last_name' => 'Reyes',
                'email' => 'mail.down@example.com',
                'roles' => [Role::findByName('ubap', 'web')->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors()
            ->assertNotified('Invitation email not sent');

        $admin = User::where('email', 'mail.down@example.com')->firstOrFail();
        $this->assertTrue($admin->hasRole('ubap'));
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_the_admin_forgot_password_page_does_not_reveal_which_emails_exist(): void
    {
        Notification::fake();

        $admin = ActivityLogResourceTest::makeAdmin('Known', 'Admin');
        $admin->assignRole('ubap');
        Customer::factory()->create(['email' => 'customer.only@example.com']);

        // A real admin, an unknown address, a customer-only address, then the real
        // admin again (now RESET_THROTTLED by the broker) all read the same.
        foreach ([$admin->email, 'nobody@example.com', 'customer.only@example.com', $admin->email] as $email) {
            // The page's own per-IP limiter allows 2 requests a minute; clear it
            // so every attempt reaches the broker.
            Cache::flush();

            Livewire::test(RequestPasswordReset::class)
                ->fillForm(['email' => $email])
                ->call('request')
                ->assertNotified(__(Password::RESET_LINK_SENT))
                ->assertSet('data.email', null);
        }
    }

    public function test_the_admin_forgot_password_mail_is_branded_like_the_others(): void
    {
        Notification::fake();

        $admin = ActivityLogResourceTest::makeAdmin('Known', 'Admin');
        $admin->assignRole('ubap');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $admin->email])
            ->call('request');

        $sent = null;
        Notification::assertSentTo($admin, AdminResetPassword::class, function (AdminResetPassword $notification) use (&$sent) {
            $sent = $notification;

            return true;
        });

        $mail = $sent->toMail($admin);
        $html = $mail->render();
        $text = view($mail->view['text'], $mail->viewData)->render();

        // The link is the panel's signed reset URL, with no invitation flag, so
        // the page it opens keeps the "Reset" wording.
        $this->assertStringStartsWith(route('filament.admin.auth.password-reset.reset'), $sent->url);
        $this->assertTrue(URL::hasValidSignature(Request::create($sent->url)));
        $this->assertStringNotContainsString('invitation=', $sent->url);

        foreach ([$html, $text] as $rendered) {
            $this->assertStringContainsString($admin->name, $rendered);
            $this->assertStringContainsString('RESET YOUR PASSWORD', $rendered);
            $this->assertStringContainsString('3 minutes', $rendered);
            $this->assertStringContainsString('UBAP Team', $rendered);
        }

        $this->assertStringContainsString(htmlspecialchars($sent->url), $html);
        $this->assertStringContainsString($sent->url, $text);
    }
}
