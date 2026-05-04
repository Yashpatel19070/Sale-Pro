<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_order_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products');
            $table->string('description', 500);
            $table->decimal('qty_ordered', 12, 2)->unsigned();
            $table->decimal('qty_received', 12, 2)->unsigned()->default(0);
            $table->decimal('qty_on_hand_snapshot', 12, 2)->unsigned()->nullable();
            $table->decimal('unit_cost', 12, 2)->unsigned();
            $table->decimal('tax_rate', 5, 2)->unsigned()->default(0);
            $table->decimal('line_total', 12, 2)->unsigned();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_lines');
    }
};
