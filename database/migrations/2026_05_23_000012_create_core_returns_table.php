<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_returns', function (Blueprint $table) {
            $table->id();

            $table->string('number', 25)->unique();

            $table->foreignId('order_core_charge_id')
                ->constrained('order_core_charges')
                ->restrictOnDelete();

            $table->string('return_method', 10);    // counter | mail
            $table->string('status', 20)->default('pending');

            $table->timestamp('received_at')->nullable();
            $table->foreignId('received_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('inspected_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('inspected_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // inspected_at + 30 days

            $table->string('inspection_result', 10)->nullable(); // accepted | rejected | NULL
            $table->text('rejection_reason')->nullable();

            $table->foreignId('fraud_serial_id')
                ->nullable()
                ->constrained('inventory_serials')
                ->nullOnDelete();

            $table->string('core_outcome', 30)->nullable();

            $table->foreignId('refund_payment_id')
                ->nullable()
                ->constrained('payments')
                ->nullOnDelete();

            $table->text('notes')->nullable();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('order_core_charge_id');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_returns');
    }
};
