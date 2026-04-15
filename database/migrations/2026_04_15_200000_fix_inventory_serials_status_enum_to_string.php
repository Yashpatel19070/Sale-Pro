<?php

declare(strict_types=1);

use App\Enums\SerialStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->string('status', 50)
                ->default(SerialStatus::InStock->value)
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('inventory_serials', function (Blueprint $table) {
            $table->enum('status', array_column(SerialStatus::cases(), 'value'))
                ->default(SerialStatus::InStock->value)
                ->change();
        });
    }
};
