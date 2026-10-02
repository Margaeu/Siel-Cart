<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\ChatController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatRecommendationContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_already_shown_products_and_last_query_are_forwarded_to_the_chatbot_service(): void
    {
        Http::fake(['*' => Http::response(['response' => 'Here are a few more:'])]);

        $this->postJson('/api/chat', [
            'message' => 'Show me more',
            'shown' => ['tote-bag', 'clsu-mug'],
            'last_query' => 'Recommend products',
        ])->assertOk();

        Http::assertSent(fn ($request) => $request['message'] === 'Show me more'
            && $request['shown'] === ['tote-bag', 'clsu-mug']
            && $request['last_query'] === 'Recommend products');
    }

    public function test_recommendation_details_from_the_chatbot_service_reach_the_widget(): void
    {
        Http::fake(['*' => Http::response([
            'response' => 'A few things you might like:',
            'products' => ['tote-bag'],
            'query' => 'Recommend products',
            'chips' => ['Show me more'],
        ])]);

        $this->postJson('/api/chat', ['message' => 'Recommend products'])
            ->assertOk()
            ->assertJson([
                'response' => 'A few things you might like:',
                'products' => ['tote-bag'],
                'query' => 'Recommend products',
                'chips' => ['Show me more'],
            ]);
    }

    public function test_plain_answers_carry_no_recommendation_fields(): void
    {
        Http::fake(['*' => Http::response(['response' => 'Payment is cash on pickup.'])]);

        $this->postJson('/api/chat', ['message' => 'How do I pay?'])
            ->assertOk()
            ->assertExactJson(['response' => 'Payment is cash on pickup.']);
    }

    public function test_message_is_capped_at_the_widget_limit(): void
    {
        Http::fake(['*' => Http::response(['response' => 'ok'])]);

        $this->postJson('/api/chat', ['message' => str_repeat('a', ChatController::MAX_MESSAGE_LENGTH)])
            ->assertOk();

        $this->postJson('/api/chat', ['message' => str_repeat('a', ChatController::MAX_MESSAGE_LENGTH + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');

        $this->assertSame(100, ChatController::MAX_MESSAGE_LENGTH);
    }

    public function test_shown_list_is_bounded(): void
    {
        $this->postJson('/api/chat', [
            'message' => 'Show me more',
            'shown' => array_fill(0, 101, 'tote-bag'),
        ])->assertUnprocessable()->assertJsonValidationErrors('shown');
    }
}
