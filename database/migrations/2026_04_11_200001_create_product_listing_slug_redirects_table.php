<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_listing_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')
                ->constrained('product_listings')
                ->cascadeOnDelete();
            $table->string('old_slug', 220)->unique();
            $table->timestamp('created_at')->useCurrent();
            // No updated_at — records are append-only, never modified

            $table->index('listing_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_listing_slug_redirects');
    }
};
