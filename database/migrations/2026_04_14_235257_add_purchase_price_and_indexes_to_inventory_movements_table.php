<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            // Add purchase_price — only meaningful on receive type
            $table->decimal('purchase_price', 10, 2)->nullable()->after('to_location_id');

            // Indexes for common query patterns
            $table->index('type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropIndex(['created_at']);
            $table->dropColumn('purchase_price');
        });
    }
};
