<?php

namespace Tests\Feature;

use App\Contracts\ChatServiceInterface;
use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\User;
use App\Services\ChatStageDetection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChatSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $intruder;
    protected CustomerType $customerType;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create(['enabled' => true]);
        $this->intruder = User::factory()->create(['enabled' => true]);

        $this->customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite. Tagalog.',
        ]);

        $this->conversation = Conversation::create([
            'user_id' => $this->owner->id,
            'customer_type_id' => $this->customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        Message::create([
            'conversation_id' => $this->conversation->id,
            'sender' => 'customer',
            'body' => 'Kumusta po! May tanong lang ako.',
        ]);
    }

    // -----------------------------------------------------------------------
    // sendMessage — ownership checks
    // -----------------------------------------------------------------------

    public function test_send_message_rejects_request_for_conversation_owned_by_other_user(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldNotReceive('getCustomerReply');

        $response = $this->actingAs($this->intruder)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => 'Hello po!',
            ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Conversation not found or already closed']);

        // Verify no agent message was persisted
        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $this->conversation->id,
            'sender' => 'agent',
            'body' => 'Hello po!',
        ]);
    }

    public function test_send_message_succeeds_for_conversation_owner(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->once()
            ->andReturn('Opo, paano kita matutulungan?');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => 'Hello po!',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'agent_message',
            'customer_response',
            'suggested_stage',
        ]);
    }

    // -----------------------------------------------------------------------
    // getMessages — ownership checks
    // -----------------------------------------------------------------------

    public function test_get_messages_rejects_request_for_conversation_owned_by_other_user(): void
    {
        $response = $this->actingAs($this->intruder)
            ->getJson('/api/chat/messages?conversation_id=' . $this->conversation->id);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Conversation not found']);
    }

    public function test_get_messages_succeeds_for_conversation_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/chat/messages?conversation_id=' . $this->conversation->id);

        $response->assertStatus(200);
        $response->assertJsonStructure(['messages']);
        $response->assertJsonCount(1, 'messages');
    }

    // -----------------------------------------------------------------------
    // getConversation — ownership checks
    // -----------------------------------------------------------------------

    public function test_get_conversation_rejects_request_for_conversation_owned_by_other_user(): void
    {
        $response = $this->actingAs($this->intruder)
            ->getJson('/api/chat/conversation?conversation_id=' . $this->conversation->id);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Conversation not found']);
    }

    public function test_get_conversation_succeeds_for_conversation_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/chat/conversation?conversation_id=' . $this->conversation->id);

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
    }

    // -----------------------------------------------------------------------
    // endConversation — ownership checks
    // -----------------------------------------------------------------------

    public function test_end_conversation_rejects_request_for_conversation_owned_by_other_user(): void
    {
        $response = $this->actingAs($this->intruder)
            ->postJson('/api/chat/end', [
                'conversation_id' => $this->conversation->id,
            ]);

        // endConversation always returns 200 with status=closed, but should not
        // actually close the conversation for a non-owner
        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'status' => 'active',
        ]);
    }

    public function test_end_conversation_succeeds_for_conversation_owner(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/end', [
                'conversation_id' => $this->conversation->id,
            ]);

        $response->assertStatus(200);
        $response->assertJson(['status' => 'closed']);

        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'status' => 'closed',
        ]);
    }

    // -----------------------------------------------------------------------
    // sendMessage — input sanitization
    // -----------------------------------------------------------------------

    public function test_send_message_strips_html_tags_from_body(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->once()
            ->andReturn('Salamat po!');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => '<script>alert("xss")</script>Hello po!',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('agent_message', 'alert("xss")Hello po!');

        // Verify the stored message has no HTML tags
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'sender' => 'agent',
            'body' => 'alert("xss")Hello po!',
        ]);

        $this->assertDatabaseMissing('messages', [
            'conversation_id' => $this->conversation->id,
            'sender' => 'agent',
            'body' => '<script>alert("xss")</script>Hello po!',
        ]);
    }

    public function test_send_message_strips_nested_html_tags(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->once()
            ->andReturn('Opo!');

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => '<b><i>Bold italic</i></b> <a href="http://evil.com">click me</a>',
            ]);

        $response->assertStatus(200);
        $response->assertJsonPath('agent_message', 'Bold italic click me');
    }

    // -----------------------------------------------------------------------
    // sendMessage — max length validation
    // -----------------------------------------------------------------------

    public function test_send_message_rejects_body_exceeding_max_length(): void
    {
        $longMessage = str_repeat('a', 2001);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => $longMessage,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_send_message_accepts_body_at_max_length(): void
    {
        $mock = $this->mock(ChatServiceInterface::class);
        $mock->shouldReceive('getCustomerReply')
            ->once()
            ->andReturn('Ok po.');

        $maxMessage = str_repeat('a', 2000);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => $maxMessage,
            ]);

        $response->assertStatus(200);
    }

    public function test_send_message_rejects_empty_body(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => '',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_send_message_rejects_missing_conversation_id(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'message' => 'Hello',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['conversation_id']);
    }

    // -----------------------------------------------------------------------
    // sendMessage — closed conversation
    // -----------------------------------------------------------------------

    public function test_send_message_rejects_message_to_closed_conversation(): void
    {
        $this->conversation->update(['status' => 'closed']);

        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => $this->conversation->id,
                'message' => 'Hello po!',
            ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Conversation not found or already closed']);
    }

    // -----------------------------------------------------------------------
    // Unauthenticated access
    // -----------------------------------------------------------------------

    public function test_unauthenticated_user_cannot_send_message(): void
    {
        $response = $this->postJson('/api/chat/send', [
            'conversation_id' => $this->conversation->id,
            'message' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_get_messages(): void
    {
        $response = $this->getJson('/api/chat/messages?conversation_id=' . $this->conversation->id);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_get_conversation(): void
    {
        $response = $this->getJson('/api/chat/conversation?conversation_id=' . $this->conversation->id);

        $response->assertStatus(401);
    }

    public function test_unauthenticated_user_cannot_end_conversation(): void
    {
        $response = $this->postJson('/api/chat/end', [
            'conversation_id' => $this->conversation->id,
        ]);

        $response->assertStatus(401);
    }

    // -----------------------------------------------------------------------
    // ChatStageDetection — sequential logic
    // -----------------------------------------------------------------------

    public function test_stage_detection_returns_1_with_no_agent_messages(): void
    {
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Kumusta po!'],
        ]);

        $this->assertEquals(1, $result);
    }

    public function test_stage_detection_returns_1_with_empty_history(): void
    {
        $result = ChatStageDetection::fromConversation([]);

        $this->assertEquals(1, $result);
    }

    public function test_stage_detection_advances_to_2_after_greeting(): void
    {
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Kumusta po!'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw! Paano kita matutulungan?'],
        ]);

        // Greeting detected => stage advances to 2
        // Also has a question so probing detected => stage 3
        // But no empathy, so should stop at 3
        $this->assertGreaterThanOrEqual(2, $result);
    }

    public function test_stage_detection_requires_sequential_completion(): void
    {
        // Agent jumps straight to solution without greeting or probing
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'May problema ako.'],
            ['sender' => 'agent', 'body' => 'I-recommend ko ang product namin.'],
        ]);

        // Without greeting first, should not advance past stage 1
        $this->assertEquals(1, $result);
    }

    public function test_stage_detection_full_sequence_reaches_stage_7(): void
    {
        $messages = [
            ['sender' => 'customer', 'body' => 'Hi, may tanong ako.'],
            ['sender' => 'agent', 'body' => 'Hello po! Magandang araw!'],
            ['sender' => 'customer', 'body' => 'May issue ako sa order ko.'],
            ['sender' => 'agent', 'body' => 'Ano po ang nangyari sa order nyo?'],
            ['sender' => 'customer', 'body' => 'Hindi dumating yung package ko.'],
            ['sender' => 'agent', 'body' => 'Naiintindihan ko po ang frustration nyo. Pasensya na po.'],
            ['sender' => 'customer', 'body' => 'Ano pwede nyo gawin?'],
            ['sender' => 'agent', 'body' => 'I-recommend ko po na mag-file tayo ng replacement. Ito po ang solusyon namin.'],
            ['sender' => 'customer', 'body' => 'Sigurado ba yan?'],
            ['sender' => 'agent', 'body' => 'Opo, maraming satisfied customers na ang naka-try nito. Quality guarantee po namin yan.'],
            ['sender' => 'customer', 'body' => 'Sige, gusto ko na.'],
            ['sender' => 'agent', 'body' => 'Gusto mo po ba mag-order na? Shall we proceed?'],
            ['sender' => 'customer', 'body' => 'Oo, go na.'],
            ['sender' => 'agent', 'body' => 'Ano po ang pangalan mo at address mo para sa delivery?'],
        ];

        $result = ChatStageDetection::fromConversation($messages);

        $this->assertEquals(7, $result);
    }

    public function test_stage_detection_stops_at_empathy_without_solution(): void
    {
        $messages = [
            ['sender' => 'customer', 'body' => 'May issue ako.'],
            ['sender' => 'agent', 'body' => 'Hello po! Kumusta?'],
            ['sender' => 'customer', 'body' => 'Hindi gumagana yung binili ko.'],
            ['sender' => 'agent', 'body' => 'Ano po ang nangyari exactly?'],
            ['sender' => 'customer', 'body' => 'Sira agad pagka-dating.'],
            ['sender' => 'agent', 'body' => 'Naiintindihan ko po. Sorry to hear that.'],
        ];

        $result = ChatStageDetection::fromConversation($messages);

        // Greeting (stage 2), Probing (stage 3), Empathy (stage 4)
        // No solution yet, so should be at stage 4
        $this->assertEquals(4, $result);
    }

    public function test_stage_detection_greeting_with_tagalog_variants(): void
    {
        $variants = [
            'Kumusta po!',
            'Magandang umaga po!',
            'Magandang hapon!',
            'Magandang gabi po!',
            'Hi po!',
            'Hey, kamusta?',
        ];

        foreach ($variants as $greeting) {
            $result = ChatStageDetection::fromConversation([
                ['sender' => 'customer', 'body' => 'Test'],
                ['sender' => 'agent', 'body' => $greeting],
            ]);

            $this->assertGreaterThanOrEqual(2, $result, "Greeting not detected for: {$greeting}");
        }
    }

    public function test_stage_detection_non_greeting_stays_at_stage_1(): void
    {
        $result = ChatStageDetection::fromConversation([
            ['sender' => 'customer', 'body' => 'Hi'],
            ['sender' => 'agent', 'body' => 'I will check your order status now.'],
        ]);

        $this->assertEquals(1, $result);
    }

    // -----------------------------------------------------------------------
    // Nonexistent conversation
    // -----------------------------------------------------------------------

    public function test_send_message_to_nonexistent_conversation_returns_404(): void
    {
        $response = $this->actingAs($this->owner)
            ->postJson('/api/chat/send', [
                'conversation_id' => 99999,
                'message' => 'Hello',
            ]);

        $response->assertStatus(404);
    }

    public function test_get_messages_for_nonexistent_conversation_returns_404(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/chat/messages?conversation_id=99999');

        $response->assertStatus(404);
    }

    public function test_get_conversation_for_nonexistent_conversation_returns_404(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson('/api/chat/conversation?conversation_id=99999');

        $response->assertStatus(404);
    }
}
