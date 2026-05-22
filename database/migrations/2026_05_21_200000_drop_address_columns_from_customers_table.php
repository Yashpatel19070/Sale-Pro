<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['address', 'city', 'state', 'postal_code', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('address')->after('company_name');
            $table->string('city', 100)->after('address');
            $table->string('state', 100)->after('city');
            $table->string('postal_code', 20)->after('state');
            $table->string('country', 100)->after('postal_code');
        });
    }
};
