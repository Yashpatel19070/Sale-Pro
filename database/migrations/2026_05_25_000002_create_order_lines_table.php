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
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_listing_id')->constrained('product_listings');
            $table->string('sku', 100);
            $table->string('product_name', 255);
            $table->foreignId('inventory_serial_id')->nullable()->unique()->constrained('inventory_serials');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_rate', 6, 4)->default(0.0000);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->decimal('line_total', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_lines');
    }
};
