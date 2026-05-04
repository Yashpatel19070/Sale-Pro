<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_serial_id')
                ->constrained('inventory_serials')
                ->restrictOnDelete(); // protect audit trail — hard-delete of a serial with movements must be blocked, not silently cascaded

            $table->enum('type', ['receive', 'transfer', 'sale', 'adjustment']);

            $table->foreignId('from_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->foreignId('to_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->decimal('purchase_price', 10, 2)->nullable()->unsigned();
            $table->string('reference', 150)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->index('inventory_serial_id');
            $table->index('type');
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
