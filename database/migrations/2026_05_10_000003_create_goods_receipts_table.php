<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders');
            $table->string('grn_number', 20)->unique();
            $table->foreignId('received_by')->constrained('users');
            $table->date('received_date');
            $table->text('notes')->nullable();
            $table->string('status')->default('draft');
            $table->softDeletes();
            $table->timestamps();
            $table->index('purchase_order_id');
            $table->index('grn_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
