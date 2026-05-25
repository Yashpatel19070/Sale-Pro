<?php

declare(strict_types=1);

namespace App\Services;

use Avalara\AvaTaxClient;
use Avalara\DocumentType;
use Avalara\TransactionBuilder;
use RuntimeException;

class AvaTaxService
{
    public function __construct(private readonly array $config) {}

    /**
     * @param  array<int, array{unit_price: float, sku: string}>  $lines
     * @param  array<string, string>  $shipTo
     * @return array<int, array{tax_rate: float, tax_amount: float}>
     */
    public function calculateTax(array $lines, array $shipTo): array
    {
        $client = new AvaTaxClient('sale-pro', '1.0', (string) gethostname(), $this->config['environment']);
        $client->withSecurity($this->config['account_number'], $this->config['license_key']);

        $tb = new TransactionBuilder(
            $client,
            $this->config['company_code'],
            DocumentType::C_SALESORDER,
            'sale-pro'
        );

        $tb->withAddress(
            'ShipFrom',
            $this->config['ship_from']['street'], null, null,
            $this->config['ship_from']['city'],
            $this->config['ship_from']['state'],
            $this->config['ship_from']['zip'],
            $this->config['ship_from']['country']
        );

        if (! empty($shipTo)) {
            $tb->withAddress(
                'ShipTo',
                $shipTo['line1'], null, null,
                $shipTo['city'],
                $shipTo['state'],
                $shipTo['postal_code'],
                $shipTo['country']
            );
        }

        foreach ($lines as $i => $line) {
            $tb->withLine(
                (float) $line['unit_price'],
                1,
                $line['sku'],
                $this->config['tax_code'],
                (string) ($i + 1)
            );
        }

        try {
            $transaction = $tb->create();
        } catch (\Exception $e) {
            throw new RuntimeException('AvaTax calculateTax failed: '.$e->getMessage());
        }

        $result = [];

        foreach ($transaction->lines ?? [] as $txLine) {
            $idx = (int) $txLine->lineNumber - 1;
            $result[$idx] = [
                'tax_rate' => round((float) ($txLine->details[0]?->rate ?? 0.0), 4),
                'tax_amount' => round((float) $txLine->tax, 2),
            ];
        }

        return $result;
    }
}
