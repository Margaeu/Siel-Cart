<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ChatController;
use App\Models\ChatMessage;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * POST /api/chat is open to guests, writes two chat_messages rows per call and
 * forwards the message to a paid model API, so its input size and request rate
 * are both bounded.
 */
class ChatEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*' => Http::response(['response' => 'Hello from the bot.']),
        ]);
    }

    private function chat(string $message)
    {
        return $this->postJson('/api/chat', ['message' => $message]);
    }

    public function test_a_guest_can_still_chat_and_both_messages_are_stored(): void
    {
        $this->chat('What are your pickup hours?')
            ->assertOk()
            ->assertJson(['response' => 'Hello from the bot.']);

        $this->assertDatabaseHas('chat_messages', ['sender' => 'user', 'message' => 'What are your pickup hours?', 'customer_id' => null]);
        $this->assertDatabaseHas('chat_messages', ['sender' => 'bot', 'message' => 'Hello from the bot.']);
        Http::assertSent(fn ($request) => $request['message'] === 'What are your pickup hours?');
    }

    public function test_a_message_at_the_limit_is_accepted(): void
    {
        $this->chat(str_repeat('a', ChatController::MAX_MESSAGE_LENGTH))->assertOk();
    }

    public function test_a_message_over_the_limit_is_rejected_before_anything_is_stored_or_forwarded(): void
    {
        $this->chat(str_repeat('a', ChatController::MAX_MESSAGE_LENGTH + 1))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, ChatMessage::count());
        Http::assertNothingSent();
    }

    public function test_repeated_requests_are_throttled(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->chat('Message '.$i)->assertOk();
        }

        $this->chat('One too many')->assertTooManyRequests();

        // The throttled request never reached the controller.
        $this->assertSame(20, ChatMessage::count());
        Http::assertSentCount(10);
    }

    /**
     * The endpoint must stay in the web group so CSRF still applies. The test
     * harness disables CSRF checking for the requests it builds, so this is
     * asserted on the route's resolved middleware instead.
     */
    public function test_csrf_protection_still_applies(): void
    {
        $route = Route::getRoutes()->match(Request::create('/api/chat', 'POST'));

        $middleware = Route::gatherRouteMiddleware($route);

        $this->assertContains(ValidateCsrfToken::class, $middleware);
        $this->assertContains('Illuminate\Routing\Middleware\ThrottleRequests:10,1', $middleware);
    }
}
