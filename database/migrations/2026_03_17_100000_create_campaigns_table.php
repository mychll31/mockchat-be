<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('campaign_name');
            $table->string('status')->default('Off'); // Off | On
            $table->string('delivery')->default('Off'); // Off | Active | Learning
            $table->integer('results')->nullable();
            $table->string('result_type')->nullable(); // e.g. "Messaging conversations"
            $table->decimal('cost_per_result', 12, 2)->nullable();
            $table->string('cost_per_result_type')->nullable();
            $table->decimal('budget', 12, 2)->nullable();
            $table->string('budget_type')->nullable(); // e.g. "Daily average"
            $table->decimal('amount_spent', 12, 2)->nullable();
            $table->integer('impressions')->nullable();
            $table->integer('reach')->nullable();
            $table->string('ends')->nullable(); // e.g. "Ongoing"
            $table->string('attribution_setting')->nullable();
            $table->string('bid_strategy')->nullable();
            $table->integer('total_messaging')->nullable();
            $table->integer('new_messaging')->nullable();
            $table->integer('purchases')->nullable();
            $table->decimal('cost_per_purchase', 12, 2)->nullable();
            $table->decimal('purchases_conversion_value', 12, 2)->nullable();
            $table->decimal('purchase_roas', 8, 2)->nullable();
            $table->decimal('cost_per_new_messaging', 12, 2)->nullable();
            $table->integer('messaging_conversations')->nullable();
            $table->decimal('cost_per_messaging', 12, 2)->nullable();
            $table->integer('orders_created')->nullable();
            $table->integer('orders_shipped')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
