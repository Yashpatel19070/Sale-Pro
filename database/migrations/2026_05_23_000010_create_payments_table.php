<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // Always set — even for replacement charge-backs; no FK so payments survive order deletion
            $table->unsignedBigInteger('order_id');

            // Polymorphic — order or replacement
            $table->string('payable_type', 30);
            $table->unsignedBigInteger('payable_id');

            $table->string('method', 30);   // PaymentMethod enum — PHP guard
            $table->decimal('amount', 12, 2)->unsigned();
            $table->string('status', 20);   // PaymentStatus enum: pending | paid | expired
            $table->char('currency', 3)->default('USD');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            // Stripe columns — nullable, only set for relevant method
            $table->string('stripe_payment_intent_id', 100)->nullable();
            $table->string('stripe_charge_id', 100)->nullable();
            $table->string('stripe_terminal_reader_id', 100)->nullable();
            $table->string('stripe_checkout_session_id', 100)->nullable();

            // Cash
            $table->timestamp('cash_received_at')->nullable();

            // Cheque
            $table->string('cheque_number', 50)->nullable();
            $table->date('cheque_date')->nullable();

            // Deferred confirmation (cheque + stripe_checkout)
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('order_id');
            $table->index(['payable_type', 'payable_id']);
            $table->index('stripe_payment_intent_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
