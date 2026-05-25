<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacement_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('replacement_id')
                ->constrained('replacements')
                ->cascadeOnDelete();

            $table->foreignId('order_line_id')
                ->constrained('order_lines')
                ->restrictOnDelete();

            $table->string('sku', 100);
            $table->string('product_name', 255);

            $table->foreignId('old_serial_id')
                ->constrained('inventory_serials')
                ->restrictOnDelete();

            $table->foreignId('new_serial_id')
                ->constrained('inventory_serials')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('replacement_id');
            $table->index('order_line_id');
            $table->index('old_serial_id');
            $table->index('new_serial_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacement_lines');
    }
};
