<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminLoginFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_failed_login_clears_the_fields_and_keeps_the_error(): void
    {
        $component = Livewire::test(Login::class)
            ->fillForm([
                'email' => 'ubap@admin.com',
                'password' => 'wrong-password',
                'remember' => true,
            ])
            ->call('authenticate')
            ->assertHasErrors(['login'])
            ->assertSchemaStateSet([
                'email' => null,
                'password' => null,
                'remember' => false,
            ], 'form');

        $html = $component->html();

        // The error appears once, before the email field, while both inputs are highlighted.
        $this->assertSame(1, substr_count($html, 'role="alert"'));
        $this->assertSame(1, substr_count($html, e(__('filament-panels::auth/pages/login.messages.failed'))));
        $this->assertLessThan(strpos($html, 'Email address'), strpos($html, 'role="alert"'));
        $this->assertSame(2, substr_count($html, 'fi-invalid'));
    }

    public function test_password_input_is_not_marked_invalid_before_a_failed_attempt(): void
    {
        Livewire::test(Login::class)
            ->assertDontSeeHtml('role="alert"')
            ->assertDontSeeHtml('fi-invalid');
    }
}
