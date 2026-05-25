<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sequences')->insertOrIgnore([
            ['name' => 'complaints',    'value' => 0],
            ['name' => 'replacements',  'value' => 0],
            ['name' => 'refunds',       'value' => 0],
            ['name' => 'core_returns',  'value' => 0],
        ]);
    }

    public function down(): void
    {
        DB::table('sequences')
            ->whereIn('name', ['complaints', 'replacements', 'refunds', 'core_returns'])
            ->delete();
    }
};
