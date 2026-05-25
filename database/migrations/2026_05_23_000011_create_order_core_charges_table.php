<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_core_charges', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('order_line_id')
                ->unique() // one charge per line
                ->constrained('order_lines')
                ->restrictOnDelete();

            $table->string('description', 255);
            $table->decimal('amount', 12, 2)->unsigned();
            $table->decimal('tax_rate', 8, 4)->unsigned()->default(0.0000);
            $table->decimal('tax_amount', 12, 2)->unsigned()->default(0.00);
            $table->decimal('total', 12, 2)->unsigned();

            $table->string('status', 20)->default('outstanding'); // outstanding | refunded | forfeited

            $table->timestamps();

            $table->index('order_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_core_charges');
    }
};
