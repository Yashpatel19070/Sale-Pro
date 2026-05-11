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
            $table->foreignId('goods_receipt_id')->nullable()->after('user_id')
                ->constrained('goods_receipts')->nullOnDelete();

            $table->index('goods_receipt_id');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['goods_receipt_id']);
            $table->dropIndex(['goods_receipt_id']);
            $table->dropColumn('goods_receipt_id');
        });
    }
};
