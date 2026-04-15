<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Replace the single-column unique on `code` with a composite unique on
     * (code, deleted_at). MySQL treats NULL values as distinct in unique indexes,
     * so two soft-deleted rows with the same code are allowed (different timestamps),
     * but two active rows with the same code are still blocked (both NULL).
     *
     * This makes the DB constraint match the FormRequest which already uses
     * Rule::unique()->withoutTrashed() — previously the FormRequest would pass
     * but the DB would reject the insert with a constraint violation.
     */
    public function up(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->unique(['code', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('inventory_locations', function (Blueprint $table) {
            $table->dropUnique(['code', 'deleted_at']);
            $table->unique(['code']);
        });
    }
};
