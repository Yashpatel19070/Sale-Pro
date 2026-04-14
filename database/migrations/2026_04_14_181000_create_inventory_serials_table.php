<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_serials', function (Blueprint $table) {
            $table->id();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            $table->foreignId('inventory_location_id')
                ->nullable()
                ->constrained('inventory_locations')
                ->nullOnDelete();

            $table->string('serial_number', 100)->unique();

            $table->decimal('purchase_price', 10, 2);

            $table->date('received_at');

            $table->string('supplier_name', 150)->nullable();

            $table->foreignId('received_by_user_id')
                ->constrained('users')
                ->restrictOnDelete();

            $table->enum('status', array_column(SerialStatus::cases(), 'value'))
                ->default(SerialStatus::InStock->value);

            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_id', 'status']);
            $table->index(['inventory_location_id', 'status']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_serials');
    }
};
