<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stub migration — minimal schema required by InventorySerialService::receive().
 * The full inventory-movement module will extend this table with additional columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();

            $table->foreignId('inventory_serial_id')
                ->constrained('inventory_serials')
                ->cascadeOnDelete();

            $table->foreignId('from_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->foreignId('to_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->string('type', 50);
            $table->unsignedInteger('quantity')->default(1);
            $table->string('reference', 255)->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
