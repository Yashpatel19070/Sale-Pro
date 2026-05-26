<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AvaTaxService;
use Illuminate\Console\Command;

class AvaTaxPing extends Command
{
    protected $signature = 'avatax:ping';

    protected $description = 'Test the AvaTax API connection and display credential info.';

    public function handle(AvaTaxService $service): int
    {
        $result = $service->ping();

        $this->line('AvaTax Ping');
        $this->line('───────────────────────────────');
        $this->line("Environment : {$result['environment']}");
        $this->line("Account     : {$result['account']}");
        $this->line("Company     : {$result['company_code']}");
        $this->line('───────────────────────────────');

        if ($result['success']) {
            $this->line('✓  Connection successful.');

            return Command::SUCCESS;
        }

        $this->line("✗  Connection failed: {$result['message']}");

        return Command::FAILURE;
    }
}
