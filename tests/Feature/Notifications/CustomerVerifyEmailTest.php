<?php

namespace Tests\Feature\Notifications;

use App\Models\Customer;
use App\Notifications\CustomerVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CustomerVerifyEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_message_uses_a_valid_temporary_signed_verification_url(): void
    {
        $customer = Customer::factory()->unverified()->create();

        $mailMessage = (new CustomerVerifyEmail)->toMail($customer);

        $verificationUrl = $mailMessage->viewData['verificationUrl'];

        $this->assertTrue(URL::hasValidSignature(
            Request::create($verificationUrl)
        ));
        $this->assertStringContainsString('/email/verify/', $verificationUrl);
    }

    public function test_mail_message_renders_branded_content(): void
    {
        $customer = Customer::factory()->unverified()->create(['first_name' => 'Juana']);

        $mailMessage = (new CustomerVerifyEmail)->toMail($customer);
        $rendered = $mailMessage->render();

        $this->assertStringContainsString('VERIFY YOUR EMAIL ADDRESS', $rendered);
        $this->assertStringContainsString('Juana', $rendered);
        $this->assertStringContainsString(
            htmlspecialchars($mailMessage->viewData['verificationUrl']),
            $rendered
        );
        $this->assertStringContainsString(config('app.name'), $rendered);
        $this->assertStringContainsString('The CLSU Campus Store.', $rendered);
        $this->assertStringContainsString((string) $mailMessage->viewData['expireMinutes'], $rendered);
        $this->assertStringContainsString('#1E6031', $rendered);
        $this->assertStringContainsString('ubap@clsu.edu.ph', $rendered);
    }
}
