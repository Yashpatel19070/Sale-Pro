<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guard: original stub migration already ran on the dev DB without slug/visibility.
        // The create migration has since been updated, so tests build with both columns already
        // present. Only run this migration when the columns are genuinely missing.
        if (Schema::hasColumn('product_listings', 'slug')) {
            return;
        }

        Schema::table('product_listings', function (Blueprint $table) {
            $table->string('slug', 220)->unique()->after('title');
            $table->string('visibility', 20)->default('draft')->after('slug');
            $table->index('visibility');
            $table->index('is_active');
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('product_listings', function (Blueprint $table) {
            $table->dropIndex(['visibility']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['deleted_at']);
            $table->dropColumn(['slug', 'visibility']);
        });
    }
};
