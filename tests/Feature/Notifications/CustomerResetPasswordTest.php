<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResetPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_message_uses_the_password_reset_route(): void
    {
        $customer = Customer::factory()->create();

        $mailMessage = (new CustomerResetPassword('test-token'))->toMail($customer);

        $resetUrl = $mailMessage->viewData['resetUrl'];

        $this->assertStringContainsString(route('password.reset', 'test-token', false), $resetUrl);
        $this->assertStringContainsString('email='.urlencode($customer->email), $resetUrl);
    }

    public function test_mail_message_renders_branded_content(): void
    {
        $customer = Customer::factory()->create(['first_name' => 'Juana']);

        $mailMessage = (new CustomerResetPassword('test-token'))->toMail($customer);
        $rendered = $mailMessage->render();

        $this->assertStringContainsString('RESET YOUR PASSWORD', $rendered);
        $this->assertStringContainsString('Juana', $rendered);
        $this->assertStringContainsString(
            htmlspecialchars($mailMessage->viewData['resetUrl']),
            $rendered
        );
        $this->assertStringContainsString(config('app.name'), $rendered);
        $this->assertStringContainsString((string) $mailMessage->viewData['expireMinutes'], $rendered);
        $this->assertStringContainsString('#1E6031', $rendered);
        $this->assertStringContainsString('ubap@clsu.edu.ph', $rendered);
    }
}
