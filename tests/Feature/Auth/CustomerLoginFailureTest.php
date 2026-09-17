<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoginFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_clears_the_fields_and_highlights_both_inputs(): void
    {
        $this->from(route('login'))
            ->post(route('login.store'), [
                'email' => 'nobody@example.com',
                'password' => 'wrong-password',
            ])
            ->assertRedirect(route('login'));

        $response = $this->get(route('login'));

        $response
            ->assertSee(__('auth.failed'))
            ->assertDontSee('nobody@example.com');

        $this->assertSame(2, substr_count($response->getContent(), 'border-red-500'));
    }

    public function test_inputs_are_not_highlighted_before_a_failed_attempt(): void
    {
        $this->get(route('login'))->assertDontSee('border-red-500');
    }
}
