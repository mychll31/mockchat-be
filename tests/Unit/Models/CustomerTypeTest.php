<?php

namespace Tests\Unit\Models;

use App\Models\Conversation;
use App\Models\CustomerType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_fillable_attributes(): void
    {
        $fillable = (new CustomerType)->getFillable();

        $this->assertContains('type_key', $fillable);
        $this->assertContains('label', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('personality', $fillable);
    }

    public function test_has_many_conversations(): void
    {
        $user = User::factory()->create(['enabled' => true]);

        $customerType = CustomerType::create([
            'type_key' => 'normal_buyer',
            'label' => 'Normal Customer',
            'description' => 'A friendly customer.',
            'personality' => 'Friendly, polite.',
        ]);

        Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Customer A',
            'status' => 'active',
        ]);

        Conversation::create([
            'user_id' => $user->id,
            'customer_type_id' => $customerType->id,
            'customer_name' => 'Customer B',
            'status' => 'closed',
        ]);

        $this->assertCount(2, $customerType->conversations);
        $this->assertInstanceOf(Conversation::class, $customerType->conversations->first());
    }
}
