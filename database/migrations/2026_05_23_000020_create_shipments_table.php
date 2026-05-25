<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipments', function (Blueprint $table) {
            $table->id();

            // Polymorphic — order | complaint | replacement
            $table->string('shippable_type', 30);
            $table->unsignedBigInteger('shippable_id');

            // Actual delivery address for outbound carrier shipments. NULL for in-store pickup and inbound returns.
            $table->foreignId('customer_address_id')
                ->nullable()
                ->constrained('customer_addresses')
                ->restrictOnDelete();

            $table->string('direction', 10);    // outbound | inbound
            $table->string('carrier', 50)->nullable();
            $table->string('tracking', 100)->nullable();
            $table->decimal('label_cost', 8, 2)->unsigned()->default(0.00);

            $table->string('status', 20)->default('pending');  // ShipmentStatus enum

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->timestamp('delivered_at')->nullable();

            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index(['shippable_type', 'shippable_id']);
            $table->index('customer_address_id');
            $table->index('tracking');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
