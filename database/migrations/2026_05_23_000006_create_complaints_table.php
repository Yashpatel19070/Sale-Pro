<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            $table->string('number', 20)->unique();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->restrictOnDelete();

            $table->foreignId('order_line_id')
                ->constrained('order_lines')
                ->restrictOnDelete();

            $table->foreignId('inventory_serial_id')
                ->constrained('inventory_serials')
                ->restrictOnDelete();

            $table->string('status', 20);
            $table->string('examination_result', 30)->nullable();
            $table->string('unit_outcome', 30)->nullable();

            $table->text('issue_description');

            $table->timestamp('unit_received_at')->nullable();

            $table->foreignId('examined_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('examination_notes')->nullable();

            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('withdrawn_at')->nullable();
            $table->foreignId('withdrawn_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->constrained('users')
                ->restrictOnDelete();

            $table->timestamps();

            $table->index('order_id');
            $table->index('order_line_id');
            $table->index('inventory_serial_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};
