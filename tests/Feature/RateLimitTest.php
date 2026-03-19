<?php

namespace Tests\Feature;

use App\Contracts\ChatServiceInterface;
use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CustomerType $customerType;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiter needs a persistent cache driver — 'array' resets between requests
        config(['cache.default' => 'file']);
        app('cache')->forgetDriver('file');

        $this->user = User::factory()->create(['enabled' => true]);

        $this->customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite. Tagalog.',
        ]);
    }

    protected function tearDown(): void
    {
        // Clear file cache so rate limit counters don't leak between tests
        app('cache')->store('file')->flush();
        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // Auth login rate limiting (10 per minute)
    // -----------------------------------------------------------------------

    public function test_auth_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/auth/login', [
                'email' => 'test@test.com',
                'password' => 'wrong',
            ]);
        }

        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@test.com',
            'password' => 'wrong',
        ]);

        $response->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // Chat send rate limiting (30 per minute)
    // -----------------------------------------------------------------------

    public function test_chat_send_is_rate_limited(): void
    {
        $conversation = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Kumusta po!',
        ]);

        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->andReturn('Salamat po!');

        for ($i = 0; $i < 30; $i++) {
            $this->actingAs($this->user)
                ->postJson('/api/chat/send', [
                    'conversation_id' => $conversation->id,
                    'message' => 'Message number ' . ($i + 1),
                ]);
        }

        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'This should be rate limited',
            ]);

        $response->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // Chat new rate limiting (10 per minute)
    // -----------------------------------------------------------------------

    public function test_chat_new_is_rate_limited(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerOpener')
            ->andReturn('Kumusta po!');

        for ($i = 0; $i < 10; $i++) {
            $this->actingAs($this->user)
                ->getJson('/api/chat/new');
        }

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/new');

        $response->assertStatus(429);
    }

    // -----------------------------------------------------------------------
    // Rate limit headers
    // -----------------------------------------------------------------------

    public function test_rate_limit_headers_are_present(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerOpener')
            ->andReturn('Kumusta po!');

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/new');

        $response->assertStatus(200);
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
    }
}
