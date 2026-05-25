<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sequences')->insertOrIgnore([
            'name' => 'orders',
            'value' => 0,
        ]);
    }

    public function down(): void
    {
        DB::table('sequences')->where('name', 'orders')->delete();
    }
};
