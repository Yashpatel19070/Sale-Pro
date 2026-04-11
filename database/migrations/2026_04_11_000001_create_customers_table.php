<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();

            // Name
            $table->string('first_name', 100);
            $table->string('last_name', 100);

            // Contact
            $table->string('email', 255)->nullable()->unique();
            $table->string('phone', 30)->nullable();

            // Company
            $table->string('company_name', 255)->nullable();
            $table->string('job_title', 100)->nullable();

            // Lifecycle — string, not enum(): works on MySQL, MariaDB, AND SQLite (test suite)
            // Valid values enforced by Rule::enum() in FormRequests, not at DB level.
            $table->string('status', 20)->default('lead');

            // Source — same reasoning as status
            $table->string('source', 30)->default('other');

            // Assignment
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Department scoping
            $table->foreignId('department_id')
                ->nullable()
                ->constrained('departments')
                ->nullOnDelete();

            // Address
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->string('country', 100)->nullable();

            // Notes
            $table->text('notes')->nullable();

            // Audit
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            // Additional indexes
            $table->index('status');
            $table->index('source');
            $table->index(['last_name', 'first_name']);
            $table->index('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
