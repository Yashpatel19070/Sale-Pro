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
            $t->string('avatax_customer_id')->nullable()->after('tax_exempt');
            $t->string('tax_identification_number')->nullable()->after('avatax_customer_id');
            $t->string('exemption_certificate_number')->nullable()->after('tax_identification_number');
            $t->string('entity_use_code', 2)->nullable()->after('exemption_certificate_number');
            $t->timestamp('avatax_synced_at')->nullable()->after('entity_use_code');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->dropColumn([
                'avatax_customer_id',
                'tax_identification_number',
                'exemption_certificate_number',
                'entity_use_code',
                'avatax_synced_at',
            ]);
        });
    }
};
