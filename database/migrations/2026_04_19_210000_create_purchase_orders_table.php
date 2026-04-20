<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 20)->unique();
            $table->enum('type', ['purchase', 'return'])->default('purchase');
            $table->foreignId('parent_po_id')
                ->nullable()
                ->constrained('purchase_orders')
                ->nullOnDelete();
            $table->foreignId('supplier_id')
                ->constrained('suppliers')
                ->restrictOnDelete();
            $table->enum('status', ['draft', 'open', 'partial', 'closed', 'cancelled'])
                ->default('draft');
            $table->boolean('skip_tech')->default(false);
            $table->boolean('skip_qa')->default(false);
            $table->unsignedTinyInteger('reopen_count')->default(0);
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancel_notes')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('supplier_id');
            $table->index('created_by_user_id');
            $table->index('created_at');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
