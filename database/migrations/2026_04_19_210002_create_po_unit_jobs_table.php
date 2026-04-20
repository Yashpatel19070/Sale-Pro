<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_unit_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')
                ->constrained('purchase_orders')
                ->cascadeOnDelete();
            $table->foreignId('po_line_id')
                ->constrained('po_lines')
                ->cascadeOnDelete();
            $table->foreignId('inventory_serial_id')
                ->nullable()
                ->constrained('inventory_serials')
                ->nullOnDelete();
            $table->string('pending_serial_number', 100)->nullable();
            $table->string('current_stage', 20)->default('receive');
            $table->string('status', 20)->default('pending');
            $table->foreignId('assigned_to_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('purchase_order_id');
            $table->index('po_line_id');
            $table->index('current_stage');
            $table->index('status');
            $table->index(['current_stage', 'status']);
            $table->unique('pending_serial_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_unit_jobs');
    }
};
