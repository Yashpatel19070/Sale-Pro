<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table) {
            // Guard: create migration was later updated to include these columns and
            // indexes directly, so they may already exist on fresh installs.
            if (! Schema::hasColumn('inventory_movements', 'purchase_price')) {
                $table->decimal('purchase_price', 10, 2)->nullable()->after('to_location_id');
            }
        });
    }

    public function down(): void
    {
        // Intentionally empty — create migration owns these columns and indexes.
    }
};
