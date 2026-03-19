<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $fillable = (new Conversation)->getFillable();

        $this->assertContains('user_id', $fillable);
        $this->assertContains('customer_type_id', $fillable);
        $this->assertContains('product_id', $fillable);
        $this->assertContains('customer_name', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('mentor_feedback', $fillable);
        $this->assertContains('mentor_score', $fillable);
    }

    public function test_casts(): void
    {
        $conversation = new Conversation;
        $casts = $conversation->getCasts();

        $this->assertArrayHasKey('updated_at', $casts);
        $this->assertEquals('datetime', $casts['updated_at']);
        $this->assertArrayHasKey('mentor_score', $casts);
        $this->assertEquals('integer', $casts['mentor_score']);
    }

    public function test_belongs_to_customer_type(): void
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

        $this->assertInstanceOf(CustomerType::class, $conversation->customerType);
        $this->assertEquals($customerType->id, $conversation->customerType->id);
        $this->assertEquals('normal_buyer', $conversation->customerType->type_key);
    }

    public function test_belongs_to_product(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite.',
        ]);

        $product = Product::create([
            'user_id' => $user->id,
            'name' => 'Test Product',
            'description' => 'A test product.',
            'price' => 99.99,
            'category' => 'Electronics',
        ]);

        $conversation = Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'product_id' => $product->id,
            'customer_name' => 'Test Customer',
            'status' => 'active',
        ]);

        $this->assertInstanceOf(Product::class, $conversation->product);
        $this->assertEquals($product->id, $conversation->product->id);
        $this->assertEquals('Test Product', $conversation->product->name);
    }

    public function test_has_many_messages(): void
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

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'customer',
            'body' => 'Kumusta po!',
        ]);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender' => 'agent',
            'body' => 'Hello po!',
        ]);

        $this->assertCount(2, $conversation->messages);
        $this->assertInstanceOf(Message::class, $conversation->messages->first());
    }

    public function test_status_defaults(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite.',
        ]);

        $activeConversation = Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Active Customer',
            'status' => 'active',
        ]);

        $closedConversation = Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Closed Customer',
            'status' => 'closed',
        ]);

        $this->assertEquals('active', $activeConversation->status);
        $this->assertEquals('closed', $closedConversation->status);

        $this->assertDatabaseHas('conversations', [
            'id' => $activeConversation->id,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $closedConversation->id,
            'status' => 'closed',
        ]);
    }
}
