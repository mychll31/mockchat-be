<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customer_types')) {
            Schema::create('customer_types', function (Blueprint $table) {
                $table->id();
                $table->string('type_key', 50)->unique();
                $table->string('label', 100);
                $table->text('description')->nullable();
                $table->text('personality');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_type_id')->constrained()->cascadeOnDelete();
                $table->string('customer_name', 100);
                $table->enum('status', ['active', 'closed'])->default('active');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('messages')) {
            Schema::create('messages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
                $table->enum('sender', ['agent', 'customer']);
                $table->text('body');
                $table->timestamp('created_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
        Schema::dropIfExists('customer_types');
    }
};
