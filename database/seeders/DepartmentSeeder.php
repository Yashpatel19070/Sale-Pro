<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            ['name' => 'Sales',            'code' => 'SALES', 'description' => 'Outbound and inbound sales team'],
            ['name' => 'Marketing',        'code' => 'MKT',   'description' => 'Lead generation and brand'],
            ['name' => 'Customer Support', 'code' => 'CS',    'description' => 'Post-sale customer success'],
            ['name' => 'Finance',          'code' => 'FIN',   'description' => 'Billing, invoicing, reporting'],
            ['name' => 'Operations',       'code' => 'OPS',   'description' => 'Internal ops and administration'],
            ['name' => 'Human Resources',  'code' => 'HR',    'description' => 'Hiring, onboarding, compliance'],
            ['name' => 'Management',       'code' => 'MGMT',  'description' => 'Executive and management staff'],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(
                ['code' => $data['code']],
                $data,
            );
        }
    }
}
