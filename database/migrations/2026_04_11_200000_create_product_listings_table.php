<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->string('title', 200);
            $table->string('slug', 220)->unique();
            $table->string('visibility', 20)->default('draft');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('product_id');
            $table->index('visibility');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listings');
    }
};
