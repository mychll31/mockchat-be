<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customer_types') && !Schema::hasColumn('customer_types', 'updated_at')) {
            Schema::table('customer_types', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('customer_types', 'updated_at')) {
            Schema::table('customer_types', function (Blueprint $table) {
                $table->dropColumn('updated_at');
            });
        }
    }
};
