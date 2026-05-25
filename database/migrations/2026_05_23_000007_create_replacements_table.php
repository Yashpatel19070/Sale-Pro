<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('replacements', function (Blueprint $table) {
            $table->id();

            $table->string('number', 20)->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            // Self-referential — FK added after table creation
            $table->unsignedBigInteger('parent_id')->nullable();

            $table->foreignId('complaint_id')
                ->constrained('complaints')
                ->restrictOnDelete();

            $table->string('type', 10);   // free | charged
            $table->decimal('charge', 10, 2)->unsigned()->nullable();
            $table->string('pay_status', 10)->nullable();
            $table->string('status', 20)->default('pending');

            $table->timestamp('shipped_at')->nullable();
            $table->foreignId('shipped_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('delivered_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('order_id');
            $table->index('complaint_id');
            $table->index('parent_id');

            // Self-referential FK added after table exists
            $table->foreign('parent_id')
                ->references('id')
                ->on('replacements')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('replacements');
    }
};
