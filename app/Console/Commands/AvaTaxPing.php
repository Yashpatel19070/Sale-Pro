<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Avalara\AvaTaxClient;
use Illuminate\Console\Command;

class AvaTaxPing extends Command
{
    protected $signature = 'avatax:ping';

    protected $description = 'Test the Avalara AvaTax connection using configured credentials';

    public function handle(): int
    {
        $config = config('services.avatax');

        $this->info('AvaTax connection test');
        $this->line('  Environment : '.($config['environment'] ?? 'sandbox'));
        $this->line('  Account     : '.($config['account_number'] ? '***'.substr((string) $config['account_number'], -4) : '<not set>'));
        $this->line('  Company     : '.($config['company_code'] ?? '<not set>'));
        $this->newLine();

        if (empty($config['account_number']) || empty($config['license_key'])) {
            $this->error('AVATAX_ACCOUNT_NUMBER or AVATAX_LICENSE_KEY is not set in .env');

            return self::FAILURE;
        }

        try {
            $client = new AvaTaxClient('sale-pro', '1.0', gethostname(), $config['environment']);
            $client->withSecurity($config['account_number'], $config['license_key']);

            $this->line('Pinging AvaTax API...');
            $result = $client->ping();

            if (isset($result->authenticated) && $result->authenticated === true) {
                $this->info('Connected and authenticated successfully.');
                $this->line('  Version     : '.($result->version ?? 'n/a'));

                return self::SUCCESS;
            }

            $this->warn('Connected but NOT authenticated.');
            $this->line('Response: '.json_encode($result, JSON_PRETTY_PRINT));

            return self::FAILURE;
        } catch (\Exception $e) {
            $this->error('Connection failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
