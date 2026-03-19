<?php

namespace Tests\Feature;

use App\Contracts\ChatServiceInterface;
use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected CustomerType $customerType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['enabled' => true]);

        $this->customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite. Tagalog.',
        ]);
    }

    // -----------------------------------------------------------------------
    // newConversation
    // -----------------------------------------------------------------------

    public function test_new_conversation_creates_conversation_and_returns_opener(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerOpener')
            ->once()
            ->andReturn('Kumusta po! May gusto po ba kayong bilhin?');

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/new');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'conversation_id',
            'customer_name',
            'customer_type',
            'type_key',
            'opener',
        ]);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'status' => 'active',
        ]);

        // Verify opener message was persisted
        $conversationId = $response->json('conversation_id');
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversationId,
            'sender' => 'customer',
            'body' => 'Kumusta po! May gusto po ba kayong bilhin?',
        ]);
    }

    public function test_new_conversation_with_product(): void
    {
        $product = Product::create([
            'user_id' => $this->user->id,
            'name' => 'Test Widget',
            'description' => 'A fantastic widget for testing.',
            'price' => 299.99,
            'category' => 'Gadgets',
        ]);

        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerOpener')
            ->once()
            ->withArgs(function ($typeKey, $customerName, $productContext) {
                return $typeKey === 'normal_buyer'
                    && is_string($customerName)
                    && str_contains($productContext, 'Test Widget');
            })
            ->andReturn('Interesado po ako sa Test Widget nyo!');

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/new?product_id=' . $product->id);

        $response->assertStatus(200);
        $response->assertJsonPath('product_id', (string) $product->id);

        $this->assertDatabaseHas('conversations', [
            'user_id' => $this->user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_new_conversation_returns_500_when_no_customer_types(): void
    {
        // Delete all customer types
        CustomerType::query()->delete();

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/new');

        $response->assertStatus(500);
        $response->assertJson(['error' => 'No customer types configured']);
    }

    // -----------------------------------------------------------------------
    // sendMessage
    // -----------------------------------------------------------------------

    public function test_send_message_returns_correct_structure(): void
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
            ->once()
            ->andReturn('Salamat po sa pag-message!');

        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'Hello po! Magandang araw!',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'agent_message',
            'customer_response',
            'suggested_stage',
            'auto_ended',
        ]);

        $response->assertJsonPath('agent_message', 'Hello po! Magandang araw!');
        $response->assertJsonPath('customer_response', 'Salamat po sa pag-message!');
    }

    public function test_send_message_auto_ends_conversation_at_stage_7(): void
    {
        $conversation = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        // Build full 7-stage conversation messages (need 7+ agent messages and stage 7)
        $existingMessages = [
            ['sender' => 'customer', 'body' => 'Hi, may tanong ako.'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw!'],
            ['sender' => 'customer', 'body' => 'May issue ako sa order ko.'],
            ['sender' => 'agent', 'body' => 'Ano po ang nangyari sa order nyo?'],
            ['sender' => 'customer', 'body' => 'Hindi dumating yung package ko.'],
            ['sender' => 'agent', 'body' => 'Naiintindihan ko po ang frustration nyo. Pasensya na po.'],
            ['sender' => 'customer', 'body' => 'Ano pwede nyo gawin?'],
            ['sender' => 'agent', 'body' => 'I-recommend ko po na mag-file tayo ng replacement. Ito po ang solusyon namin.'],
            ['sender' => 'customer', 'body' => 'Sigurado ba yan?'],
            ['sender' => 'agent', 'body' => 'Maraming satisfied customers na ang naka-try nito. Quality guarantee po namin yan.'],
            ['sender' => 'customer', 'body' => 'Sige, gusto ko na.'],
            ['sender' => 'agent', 'body' => 'Gusto mo po ba mag-order na? Shall we proceed?'],
            ['sender' => 'customer', 'body' => 'Oo, go na.'],
        ];

        foreach ($existingMessages as $msg) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender' => $msg['sender'],
                'body' => $msg['body'],
            ]);
        }

        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->once()
            ->andReturn('Opo, ito po ang details ko.');

        // This is the 7th agent message and should trigger stage 7
        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'Ano po ang pangalan mo at address mo para sa delivery?',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('suggested_stage', 7);
        $response->assertJsonPath('auto_ended', true);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => 'closed',
        ]);
    }

    public function test_send_message_returns_502_on_service_error(): void
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
            ->once()
            ->andThrow(new \RuntimeException('API connection failed'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/send', [
                'conversation_id' => $conversation->id,
                'message' => 'Hello po!',
            ]);

        $response->assertStatus(502);
        $response->assertJson(['error' => 'API connection failed']);
    }

    // -----------------------------------------------------------------------
    // getStats
    // -----------------------------------------------------------------------

    public function test_get_stats_returns_correct_counts(): void
    {
        $secondType = CustomerType::create([
            'type_key' => 'irate_returner',
            'label' => 'Irate Returner',
            'description' => 'An irate customer returning a product.',
            'personality' => 'Angry, impatient.',
        ]);

        // Create conversations for the first type
        $conv1 = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Customer A',
            'status' => 'active',
        ]);

        $conv2 = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $secondType->id,
            'customer_name' => 'Customer B',
            'status' => 'closed',
        ]);

        // Create messages
        Message::create(['conversation_id' => $conv1->id, 'sender' => 'agent', 'body' => 'Hello']);
        Message::create(['conversation_id' => $conv1->id, 'sender' => 'customer', 'body' => 'Hi']);
        Message::create(['conversation_id' => $conv2->id, 'sender' => 'agent', 'body' => 'Hey']);

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'total_conversations',
            'active_conversations',
            'total_agent_messages',
            'by_type',
        ]);

        $response->assertJsonPath('total_conversations', 2);
        $response->assertJsonPath('active_conversations', 1);
        $response->assertJsonPath('total_agent_messages', 2);
    }

    // -----------------------------------------------------------------------
    // getMyConversations
    // -----------------------------------------------------------------------

    public function test_get_my_conversations_returns_user_conversations_only(): void
    {
        $otherUser = User::factory()->create(['enabled' => true]);

        Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'My Customer',
            'status' => 'active',
        ]);

        Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'My Other Customer',
            'status' => 'closed',
        ]);

        Conversation::create([
            'user_id' => $otherUser->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Other User Customer',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/my-conversations');

        $response->assertStatus(200);
        $response->assertJsonCount(2);

        $customerNames = collect($response->json())->pluck('customer_name')->toArray();
        $this->assertContains('My Customer', $customerNames);
        $this->assertContains('My Other Customer', $customerNames);
        $this->assertNotContains('Other User Customer', $customerNames);
    }

    // -----------------------------------------------------------------------
    // endConversation
    // -----------------------------------------------------------------------

    public function test_end_conversation_sets_status_to_closed(): void
    {
        $conversation = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/chat/end', [
                'conversation_id' => $conversation->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'closed']);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'status' => 'closed',
        ]);
    }

    // -----------------------------------------------------------------------
    // getMessages
    // -----------------------------------------------------------------------

    public function test_get_messages_returns_ordered_messages(): void
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
            'body' => 'First message',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'body' => 'Second message',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Third message',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/messages?conversation_id=' . $conversation->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['messages']);
        $response->assertJsonCount(3, 'messages');

        $messages = $response->json('messages');
        $this->assertEquals('First message', $messages[0]['body']);
        $this->assertEquals('Second message', $messages[1]['body']);
        $this->assertEquals('Third message', $messages[2]['body']);
    }

    // -----------------------------------------------------------------------
    // getConversation
    // -----------------------------------------------------------------------

    public function test_get_conversation_includes_suggested_stage(): void
    {
        $conversation = Conversation::create([
            'user_id' => $this->user->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        // Add a greeting to move past stage 1
        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Hi!',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'body' => 'Hello po! Magandang araw!',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/chat/conversation?conversation_id=' . $conversation->id);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'conversation' => [
                'id',
                'customer_name',
                'status',
                'customer_type',
                'type_key',
                'suggested_stage',
            ],
        ]);

        // With a greeting message, stage should be >= 2
        $this->assertGreaterThanOrEqual(2, $response->json('conversation.suggested_stage'));
    }
}
