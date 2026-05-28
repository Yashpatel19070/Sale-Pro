<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn('avatax_certificate_id');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->unsignedBigInteger('avatax_certificate_id')->nullable()->after('avatax_synced_at');
        });
    }
};
