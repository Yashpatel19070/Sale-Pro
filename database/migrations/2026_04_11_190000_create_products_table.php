<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')
                ->nullable()
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->string('sku', 64)->unique();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->unsignedDecimal('purchase_price', 10, 2)->nullable();
            $table->unsignedDecimal('regular_price', 10, 2);
            $table->unsignedDecimal('sale_price', 10, 2)->nullable();
            $table->string('notes', 500)->nullable();      // internal staff notes
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('category_id');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
