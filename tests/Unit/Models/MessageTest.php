<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $fillable = (new Message)->getFillable();

        $this->assertContains('conversation_id', $fillable);
        $this->assertContains('sender', $fillable);
        $this->assertContains('body', $fillable);
    }

    public function test_timestamps_disabled(): void
    {
        $message = new Message;

        $this->assertFalse($message->timestamps);
    }

    public function test_belongs_to_conversation(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite.',
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Kumusta po!',
        ]);

        $this->assertInstanceOf(Conversation::class, $message->conversation);
        $this->assertEquals($conversation->id, $message->conversation->id);
    }

    public function test_sender_values(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite.',
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        $agentMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'body' => 'Hello po!',
        ]);

        $customerMessage = Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Kumusta po!',
        ]);

        $this->assertEquals('agent', $agentMessage->sender);
        $this->assertEquals('customer', $customerMessage->sender);

        $this->assertDatabaseHas('messages', [
            'id' => $agentMessage->id,
            'sender' => 'agent',
        ]);

        $this->assertDatabaseHas('messages', [
            'id' => $customerMessage->id,
            'sender' => 'customer',
        ]);
    }
}
