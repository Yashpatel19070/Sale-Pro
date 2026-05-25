<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();

            $table->string('number', 20)->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->string('type', 20);             // order | complaint
            $table->unsignedBigInteger('payable_id'); // polymorphic — no DB FK

            $table->decimal('amount', 12, 2)->unsigned();
            $table->decimal('ship_refund', 10, 2)->unsigned()->default(0.00);

            $table->string('method', 10);           // stripe | cash | cheque
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('pending');
            $table->char('currency', 3)->default('USD');

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('processed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('processed_at')->nullable();

            $table->timestamps();

            $table->index('order_id');
            $table->index(['type', 'payable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
    }
};
