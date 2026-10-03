<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ChatController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ChatServiceSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_service_token_is_sent_as_a_bearer_token_when_configured(): void
    {
        config(['services.chatbot.token' => 'shared-secret-for-tests-0123456789abcdef']);
        Http::fake(['*' => Http::response(['response' => 'ok'])]);

        $this->postJson('/api/chat', ['message' => 'hi'])->assertOk();

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer shared-secret-for-tests-0123456789abcdef'));
    }

    public function test_no_authorization_header_is_sent_when_no_token_is_configured(): void
    {
        config(['services.chatbot.token' => null]);
        Http::fake(['*' => Http::response(['response' => 'ok'])]);

        $this->postJson('/api/chat', ['message' => 'hi'])->assertOk();

        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
    }

    public function test_a_connection_failure_shows_the_friendly_message_without_internal_details(): void
    {
        config(['services.chatbot.url' => 'http://10.1.2.3:3000/api/chat']);
        Http::fake(fn () => throw new ConnectionException('cURL error 7: Failed to connect to 10.1.2.3 port 3000'));

        $response = $this->postJson('/api/chat', ['message' => 'hi'])
            ->assertOk()
            ->assertExactJson(['response' => ChatController::UNAVAILABLE_MESSAGE]);

        $this->assertStringNotContainsString('10.1.2.3', $response->getContent());
        $this->assertStringNotContainsString('cURL', $response->getContent());
    }

    public function test_an_error_status_from_the_service_is_not_echoed_to_the_shopper(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);

        $response = $this->postJson('/api/chat', ['message' => 'hi'])
            ->assertOk()
            ->assertExactJson(['response' => ChatController::UNAVAILABLE_MESSAGE]);

        $this->assertStringNotContainsString('500', $response->getContent());
    }

    public function test_a_rejected_token_is_logged_as_a_configuration_fault(): void
    {
        Log::spy();
        Http::fake(['*' => Http::response(['error' => 'Unauthorized.'], 401)]);

        $this->postJson('/api/chat', ['message' => 'hi'])
            ->assertOk()
            ->assertExactJson(['response' => ChatController::UNAVAILABLE_MESSAGE]);

        Log::shouldHaveReceived('error')
            ->withArgs(fn ($message) => str_contains($message, 'CHATBOT_SERVICE_TOKEN'))
            ->once();
    }

    public function test_the_unavailable_message_matches_the_chatbot_service_wording(): void
    {
        $knowledge = file_get_contents(base_path('chatbot-server/lib/store-knowledge.js'));

        $this->assertStringContainsString(
            'FRIENDLY_ERROR_MESSAGE = "'.ChatController::UNAVAILABLE_MESSAGE.'"',
            $knowledge,
        );
    }

    public function test_the_message_limit_matches_the_chatbot_service_limit(): void
    {
        $guard = file_get_contents(base_path('chatbot-server/lib/http-guard.js'));

        $this->assertStringContainsString(
            'export const MAX_MESSAGE_LENGTH = '.ChatController::MAX_MESSAGE_LENGTH.';',
            $guard,
        );
    }

    public function test_readiness_reports_ok_when_the_database_answers(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'database' => 'ok']);
    }

    public function test_readiness_reports_503_without_leaking_connection_details(): void
    {
        DB::shouldReceive('select')->once()->andThrow(new \RuntimeException('SQLSTATE[HY000] [2002] db.internal.example refused'));

        $response = $this->getJson('/health/ready')->assertStatus(503);

        $this->assertStringNotContainsString('db.internal.example', $response->getContent());
    }

    public function test_readiness_does_not_start_a_session(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertCookieMissing(config('session.cookie'));
    }
}
