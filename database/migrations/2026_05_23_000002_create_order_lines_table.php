<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Product snapshots — copied at order creation, immutable
            $table->string('sku', 100);
            $table->string('product_name', 255);

            // NULL when back-ordered (stock not yet in). Unique when set — DB-level oversell guard.
            $table->foreignId('inventory_serial_id')
                ->nullable()
                ->unique()
                ->constrained('inventory_serials')
                ->restrictOnDelete();

            $table->decimal('unit_price', 10, 2)->unsigned();
            $table->decimal('tax_rate', 6, 4)->unsigned()->default(0.0000);
            $table->decimal('tax_amount', 10, 2)->unsigned()->default(0.00);
            $table->decimal('line_total', 10, 2)->unsigned();

            $table->timestamps();

            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
