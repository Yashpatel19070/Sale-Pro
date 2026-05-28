<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerAddress;
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
    public function calculateTax(array $lines, array $shipTo, string $customerCode, ?string $entityUseCode = null): array
    {
        $zeros = array_map(fn () => ['tax_rate' => 0, 'tax_amount' => 0], $lines);

        if (! $this->isEnabled()) {
            return $zeros;
        }

        $shipFrom = $this->config['ship_from'];
        if (empty($shipFrom['street']) || empty($shipFrom['city']) || empty($shipFrom['state']) || empty($shipFrom['zip'])) {
            return $zeros;
        }

        if (empty($shipTo['address_line1']) || empty($shipTo['city']) || empty($shipTo['state']) || empty($shipTo['postal_code']) || empty($shipTo['country'])) {
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
            if ($entityUseCode !== null) {
                $model->entityUseCode = $entityUseCode;
            }

            $response = $this->makeClient()->createTransaction('', $model);

            if (! is_object($response) || ! isset($response->lines) || ! is_array($response->lines)) {
                Log::warning('AvaTax calculateTax received unexpected response shape', [
                    'response_type' => gettype($response),
                    'response_preview' => is_string($response) ? substr($response, 0, 500) : null,
                    'ship_to_zone' => ($shipTo['postal_code'] ?? '').' '.($shipTo['country'] ?? ''),
                    'customer_code' => $customerCode,
                ]);

                return $zeros;
            }

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
                'ship_to_zone' => ($shipTo['postal_code'] ?? '').' '.($shipTo['country'] ?? ''),
                'customer_code' => $customerCode,
                'line_count' => count($lines),
            ]);

            return $zeros;
        }
    }

    /**
     * AvaTax canonical exemption reason names (uppercase, from
     * /api/v2/definitions/certificateexemptreasons). Single source of truth —
     * mapped from the entity_use_code letters validated by StoreCustomerRequest.
     */
    public const EXEMPTION_REASON_NAMES = [
        'A' => 'FEDERAL GOV',
        'B' => 'STATE GOVERNMENT',
        'C' => 'TRIBAL GOVERNMENT',
        'D' => 'FOREIGN DIPLOMAT',
        'E' => 'CHARITABLE/EXEMPT ORG',
        'F' => 'RELIGIOUS/EDUCATIONAL ORG',
        'G' => 'RESALE',
        'H' => 'AGRICULTURE',
        'I' => 'INDUSTRIAL PROD/MANUFACTURERS',
        'J' => 'DIRECT PAY',
        'K' => 'DIRECT MAIL',
        'L' => 'OTHER/CUSTOM',
        'M' => 'EDUCATIONAL ORG',
        'N' => 'LOCAL GOVERNMENT',
    ];

    /**
     * Resolve the customer's address to send to AvaTax — default first, else
     * the earliest one on file. Shared by upsertCustomer + upsertCertificate.
     */
    private function defaultAddressFor(Customer $customer): ?CustomerAddress
    {
        return $customer->addresses()->where('is_default', true)->first()
            ?? $customer->addresses()->first();
    }

    /**
     * Register (or update) a Customer in AvaTax. Returns the AvaTax customer code
     * on success, or null on any failure (disabled, bad config, SDK error).
     * Never throws — failures log a warning and return null so the queued job
     * can retry without disrupting the local Customer row.
     */
    public function upsertCustomer(Customer $customer): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $companyId = $this->config['company_id'] ?? null;
        if (empty($companyId)) {
            return null;
        }

        $address = $this->defaultAddressFor($customer);

        if ($address === null) {
            Log::warning('AvaTax upsertCustomer skipped: customer has no address', [
                'customer_id' => $customer->id,
            ]);

            return null;
        }

        $customerCode = (string) $customer->id;
        $isUpdate = ! empty($customer->avatax_customer_id);

        try {
            $payload = new \stdClass;
            $payload->customerCode = $customerCode;
            $payload->name = (string) $customer->name;
            $payload->emailAddress = (string) $customer->email;
            $payload->phoneNumber = $customer->phone;
            $payload->line1 = (string) $address->address_line1;
            $payload->line2 = $address->address_line2;
            $payload->city = (string) $address->city;
            $payload->region = (string) $address->state;
            $payload->postalCode = (string) $address->postal_code;
            $payload->country = (string) $address->country;
            $payload->taxpayerIdNumber = $customer->tax_identification_number;

            $response = $isUpdate
                ? $this->makeClient()->updateCustomer((int) $companyId, $customerCode, $payload)
                : $this->makeClient()->createCustomers((int) $companyId, [$payload]);

            // DuplicateEntry on create = already exists, treat as success.
            if (! $isUpdate && is_string($response) && str_contains($response, 'DuplicateEntry')) {
                Log::info('AvaTax customer already exists, treating as synced', [
                    'customer_id' => $customer->id,
                ]);

                return $customerCode;
            }

            // Update returns the updated customer object directly.
            if ($isUpdate && is_object($response) && isset($response->customerCode)) {
                Log::info('AvaTax customer updated', [
                    'customer_id' => $customer->id,
                    'avatax_customer_code' => $response->customerCode,
                ]);

                return (string) $response->customerCode;
            }

            // Create returns an array of customer objects, or {value: [...]}.
            $first = null;
            if (is_array($response) && isset($response[0])) {
                $first = $response[0];
            } elseif (is_object($response) && isset($response->value[0])) {
                $first = $response->value[0];
            }

            if ($first !== null) {
                Log::info('AvaTax customer created', [
                    'customer_id' => $customer->id,
                    'avatax_customer_code' => $first->customerCode ?? $customerCode,
                ]);

                return isset($first->customerCode) ? (string) $first->customerCode : $customerCode;
            }

            // No response_preview here — the request echoes taxpayerIdNumber, which
            // must not land in logs (security.md). customer_id is enough to trace.
            Log::warning('AvaTax upsertCustomer received unexpected response shape', [
                'customer_id' => $customer->id,
                'operation' => $isUpdate ? 'update' : 'create',
                'response_type' => gettype($response),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('AvaTax upsertCustomer failed', [
                'message' => $e->getMessage(),
                'customer_id' => $customer->id,
                'operation' => $isUpdate ? 'update' : 'create',
            ]);

            return null;
        }
    }

    /**
     * Register an exemption certificate in AvaTax for a synced customer.
     * Uses `filename` as a placeholder string (no actual PDF upload required).
     * Returns the AvaTax certificate id (int) on success, or null on failure or
     * when cert data is incomplete. Never throws.
     */
    public function upsertCertificate(Customer $customer): ?int
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $companyId = $this->config['company_id'] ?? null;
        if (empty($companyId)) {
            return null;
        }

        if (empty($customer->avatax_customer_id)) {
            Log::warning('AvaTax upsertCertificate skipped: customer not synced', [
                'customer_id' => $customer->id,
            ]);

            return null;
        }

        if (
            empty($customer->exemption_signed_date)
            || empty($customer->exemption_expires_at)
            || empty($customer->exemption_exposure_zone)
        ) {
            return null;
        }

        $address = $this->defaultAddressFor($customer);

        if ($address === null) {
            Log::warning('AvaTax upsertCertificate skipped: customer has no address', [
                'customer_id' => $customer->id,
            ]);

            return null;
        }

        $isUpdate = ! empty($customer->avatax_certificate_id);

        try {
            $customerOnCert = new \stdClass;
            $customerOnCert->customerCode = (string) $customer->id;
            $customerOnCert->name = (string) $customer->name;
            $customerOnCert->emailAddress = (string) $customer->email;
            $customerOnCert->line1 = (string) $address->address_line1;
            $customerOnCert->city = (string) $address->city;
            $customerOnCert->region = (string) $address->state;
            $customerOnCert->postalCode = (string) $address->postal_code;
            $customerOnCert->country = (string) $address->country;

            $cert = new \stdClass;
            // `filename` placeholder satisfies AvaTax's "filename OR pdf OR pages" requirement
            // without actually uploading a PDF. documentExists will be false on the response.
            $cert->filename = 'exemption-cert-'.$customer->id.'.pdf';
            $cert->signedDate = $customer->exemption_signed_date->toDateString();
            $cert->expirationDate = $customer->exemption_expires_at->toDateString();
            $cert->exemptionNumber = $customer->exemption_certificate_number;
            $cert->exemptionReason = (object) ['name' => self::EXEMPTION_REASON_NAMES[$customer->entity_use_code] ?? 'OTHER/CUSTOM'];
            $cert->exposureZone = (object) ['name' => $customer->exemption_exposure_zone];
            $cert->customers = [$customerOnCert];

            // preValidatedExemptionReason=true skips Avalara's human verification step.
            $response = $isUpdate
                ? $this->makeClient()->updateCertificate((int) $companyId, (int) $customer->avatax_certificate_id, $cert)
                : $this->makeClient()->createCertificates((int) $companyId, true, [$cert]);

            // Update returns the cert object directly; create returns an array (or {value:[]}).
            $first = null;
            if ($isUpdate && is_object($response) && isset($response->id)) {
                $first = $response;
            } elseif (is_array($response) && isset($response[0])) {
                $first = $response[0];
            } elseif (is_object($response) && isset($response->value[0])) {
                $first = $response->value[0];
            }

            if ($first !== null && isset($first->id)) {
                Log::info($isUpdate ? 'AvaTax certificate updated' : 'AvaTax certificate created', [
                    'customer_id' => $customer->id,
                    'avatax_certificate_id' => (int) $first->id,
                ]);

                return (int) $first->id;
            }

            // No response_preview here — the request echoes exemptionNumber +
            // taxpayerIdNumber, which must not land in logs (security.md).
            Log::warning('AvaTax upsertCertificate received unexpected response shape', [
                'customer_id' => $customer->id,
                'operation' => $isUpdate ? 'update' : 'create',
                'response_type' => gettype($response),
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('AvaTax upsertCertificate failed', [
                'message' => $e->getMessage(),
                'customer_id' => $customer->id,
                'operation' => $isUpdate ? 'update' : 'create',
            ]);

            return null;
        }
    }

    /**
     * Commit a SalesInvoice transaction to AvaTax (recorded in their ledger for tax filing).
     * Called after a payment is recorded. Best-effort: returns false on any failure
     * (disabled, bad config, SDK error) — never throws, so a transient AvaTax outage
     * cannot roll back the payment that has already been persisted.
     *
     * @param  array<int, array{unit_price: float, sku: string}>  $lines
     * @param  array{address_line1: string, city: string, state: string, postal_code: string, country: string}  $shipTo
     */
    public function commitInvoice(array $lines, array $shipTo, string $customerCode, string $documentCode, ?string $entityUseCode = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $shipFrom = $this->config['ship_from'];
        if (empty($shipFrom['street']) || empty($shipFrom['city']) || empty($shipFrom['state']) || empty($shipFrom['zip'])) {
            return false;
        }

        try {
            $avataxLines = [];
            foreach ($lines as $line) {
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
            }

            if (empty($avataxLines)) {
                return false;
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
            $model->type = 'SalesInvoice';
            $model->commit = true;
            $model->code = $documentCode;
            $model->companyCode = $this->config['company_code'];
            $model->date = now()->toDateString();
            $model->customerCode = $customerCode;
            $model->addresses = $addresses;
            $model->lines = $avataxLines;
            if ($entityUseCode !== null) {
                $model->entityUseCode = $entityUseCode;
            }

            $response = $this->makeClient()->createTransaction('', $model);

            if (! is_object($response) || ! isset($response->id)) {
                Log::warning('AvaTax commitInvoice received unexpected response shape', [
                    'response_type' => gettype($response),
                    'response_preview' => is_string($response) ? substr($response, 0, 500) : null,
                    'document_code' => $documentCode,
                    'customer_code' => $customerCode,
                ]);

                return false;
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('AvaTax commitInvoice failed', [
                'message' => $e->getMessage(),
                'document_code' => $documentCode,
                'customer_code' => $customerCode,
                'line_count' => count($lines),
            ]);

            return false;
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

    protected function makeClient(): AvaTaxClient
    {
        $env = $this->config['environment'] === 'production' ? 'production' : 'sandbox';

        return (new AvaTaxClient('sale-pro', '1.0', gethostname(), $env))
            ->withSecurity($this->config['account'], $this->config['license_key']);
    }
}
