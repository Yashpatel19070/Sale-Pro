<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->unsignedInteger('qty_passed')->nullable()->after('qty_received');
            $table->unsignedInteger('qty_failed')->nullable()->after('qty_passed');
            $table->timestamp('qc_inspected_at')->nullable()->after('qty_failed');
            $table->foreignId('qc_inspected_by')->nullable()->after('qc_inspected_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('goods_receipt_lines', function (Blueprint $table) {
            $table->dropForeign(['qc_inspected_by']);
            $table->dropColumn(['qty_passed', 'qty_failed', 'qc_inspected_at', 'qc_inspected_by']);
        });
    }
};
