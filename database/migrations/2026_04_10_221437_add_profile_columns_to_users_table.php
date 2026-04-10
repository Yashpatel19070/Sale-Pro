<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('avatar', 255)->nullable()->after('phone');
            $table->string('job_title', 100)->nullable()->after('avatar');
            $table->string('employee_id', 50)->nullable()->unique()->after('job_title');
            $table->date('hired_at')->nullable()->after('department_id');
            $table->string('timezone', 50)->default('UTC')->after('hired_at');
            $table->foreignId('created_by')->nullable()->after('timezone')
                  ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                  ->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn([
                'phone', 'avatar', 'job_title', 'employee_id',
                'hired_at', 'timezone', 'created_by', 'updated_by', 'deleted_at',
            ]);
        });
    }
};
