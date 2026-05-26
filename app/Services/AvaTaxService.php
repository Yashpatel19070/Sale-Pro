<?php

declare(strict_types=1);

namespace App\Services;

use Avalara\AddressesModel;
use Avalara\AddressLocationInfo;
use Avalara\AvaTaxClient;
use Avalara\CreateTransactionModel;
use Avalara\LineItemModel;
use Illuminate\Support\Facades\Log;

class AvaTaxService
{
    public function __construct(private readonly array $config) {}

    public function isEnabled(): bool
    {
        return (bool) $this->config['enabled'];
    }

    /**
     * Calculate tax for multiple order lines in a single AvaTax SalesOrder transaction.
     * Returns an array of ['tax_rate' => float, 'tax_amount' => float] in the same order/count as $lines.
     * Returns zeros on any failure, when disabled, or when unit_price <= 0.
     *
     * @param  array<int, array{unit_price: float, sku: string}>  $lines
     * @param  array{address_line1: string, city: string, state: string, postal_code: string, country: string}  $shipTo
     */
    public function calculateTax(array $lines, array $shipTo, string $customerCode): array
    {
        $zeros = array_map(fn () => ['tax_rate' => 0, 'tax_amount' => 0], $lines);

        if (! $this->isEnabled()) {
            return $zeros;
        }

        try {
            $validIndexes = [];
            $avataxLines = [];

            foreach ($lines as $i => $line) {
                $unitPrice = (float) ($line['unit_price'] ?? 0);
                if ($unitPrice <= 0) {
                    continue;
                }

                $lineModel = new LineItemModel;
                $lineModel->number = (string) (count($avataxLines) + 1);
                $lineModel->amount = $unitPrice;
                $lineModel->quantity = 1;
                $lineModel->itemCode = $line['sku'] ?? '';

                $avataxLines[] = $lineModel;
                $validIndexes[] = $i;
            }

            if (empty($avataxLines)) {
                return $zeros;
            }

            $shipFromAddr = new AddressLocationInfo;
            $shipFromAddr->line1 = $this->config['ship_from']['street'];
            $shipFromAddr->city = $this->config['ship_from']['city'];
            $shipFromAddr->region = $this->config['ship_from']['state'];
            $shipFromAddr->postalCode = $this->config['ship_from']['zip'];
            $shipFromAddr->country = $this->config['ship_from']['country'];

            $shipToAddr = new AddressLocationInfo;
            $shipToAddr->line1 = $shipTo['address_line1'];
            $shipToAddr->city = $shipTo['city'];
            $shipToAddr->region = $shipTo['state'];
            $shipToAddr->postalCode = $shipTo['postal_code'];
            $shipToAddr->country = $shipTo['country'];

            $addresses = new AddressesModel;
            $addresses->shipFrom = $shipFromAddr;
            $addresses->shipTo = $shipToAddr;

            $model = new CreateTransactionModel;
            $model->type = 'SalesOrder';
            $model->companyCode = $this->config['company_code'];
            $model->date = now()->toDateString();
            $model->customerCode = $customerCode;
            $model->addresses = $addresses;
            $model->lines = $avataxLines;

            $response = $this->makeClient()->createTransaction('', $model);

            $result = $zeros;
            foreach ($response->lines as $j => $responseLine) {
                $idx = $validIndexes[$j] ?? null;
                if ($idx === null) {
                    continue;
                }
                $taxAmount = (float) ($responseLine->taxCalculated ?? 0);
                $unitPrice = (float) ($lines[$idx]['unit_price'] ?? 0);
                $taxRate = $unitPrice > 0 ? round(($taxAmount / $unitPrice) * 100, 4) : 0;
                $result[$idx] = [
                    'tax_rate' => $taxRate,
                    'tax_amount' => round($taxAmount, 2),
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            Log::warning('AvaTax calculateTax failed', [
                'message' => $e->getMessage(),
                'ship_to' => $shipTo,
                'customer_code' => $customerCode,
                'line_count' => count($lines),
            ]);

            return $zeros;
        }
    }

    public function ping(): array
    {
        try {
            $response = $this->makeClient()->ping();

            return [
                'success' => true,
                'environment' => $this->config['environment'],
                'account' => $this->config['account'],
                'company_code' => $this->config['company_code'],
                'version' => $response->version ?? 'unknown',
                'message' => 'AvaTax connection successful.',
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'environment' => $this->config['environment'],
                'account' => $this->config['account'],
                'company_code' => $this->config['company_code'],
                'version' => null,
                'message' => $e->getMessage(),
            ];
        }
    }

    private function makeClient(): AvaTaxClient
    {
        $env = $this->config['environment'] === 'production' ? 'production' : 'sandbox';

        return (new AvaTaxClient('sale-pro', '1.0', gethostname(), $env))
            ->withSecurity($this->config['account'], $this->config['license_key']);
    }
}
