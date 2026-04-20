<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('po_unit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('po_unit_job_id')
                ->constrained('po_unit_jobs')
                ->cascadeOnDelete();
            $table->string('stage', 20);
            $table->string('action', 20);
            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('po_unit_job_id');
            $table->index(['po_unit_job_id', 'stage']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('po_unit_events');
    }
};
