<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('value')->default(0);
        });

        // Seed the serial_number sequence at 0.
        // value = last used sequence number. Next batch starts at value + 1.
        DB::table('sequences')->insert(['name' => 'serial_number', 'value' => 0]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
