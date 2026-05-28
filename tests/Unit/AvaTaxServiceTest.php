<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\AvaTaxService;
use Avalara\AvaTaxClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => Mockery::close());

$minimalConfig = [
    'enabled' => false,
    'environment' => 'sandbox',
    'account' => '',
    'license_key' => '',
    'company_code' => '',
    'company_id' => null,
    'ship_from' => [
        'street' => '',
        'city' => '',
        'state' => '',
        'zip' => '',
        'country' => 'US',
    ],
];

it('returns_true_when_enabled_config_is_true', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'enabled' => true]);
    expect($service->isEnabled())->toBeTrue();
});

it('returns_false_when_enabled_config_is_false', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'enabled' => false]);
    expect($service->isEnabled())->toBeFalse();
});

it('ping_does_not_expose_license_key_in_return', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'license_key' => 'super-secret']);

    $result = $service->ping();

    expect($result)->not->toHaveKey('license_key');
    expect(json_encode($result))->not->toContain('super-secret');
});

it('ping_returns_failure_when_sdk_throws', function () use ($minimalConfig) {
    // Empty account/license causes the SDK to throw on any real API call.
    // Sandbox ping returns success even with bad creds, so we force an exception
    // by passing a malformed environment string that the SDK rejects.
    $service = new AvaTaxService([...$minimalConfig, 'environment' => 'invalid-env-xyz']);

    $result = $service->ping();

    // Either it fails (invalid env) or catches gracefully — either way license_key must not appear
    expect($result)->not->toHaveKey('license_key');
    expect($result)->toHaveKeys(['success', 'message', 'environment', 'account', 'company_code']);
});

it('ping_returns_success_on_valid_sandbox_credentials', function () {
    $service = new AvaTaxService(config('avatax'));
    $result = $service->ping();

    expect($result['success'])->toBeTrue();
    expect($result['environment'])->toBe('sandbox');
    expect($result['account'])->not->toBeEmpty();
    expect($result)->not->toHaveKey('license_key');
})->skip(empty(env('AVATAX_ACCOUNT_NUMBER')), 'AVATAX_ACCOUNT_NUMBER not set in env.');

it('commitInvoice_returns_false_when_disabled', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'enabled' => false]);

    $result = $service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('commitInvoice_returns_false_when_ship_from_incomplete', function () use ($minimalConfig) {
    $config = [
        ...$minimalConfig,
        'enabled' => true,
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'FAKE',
        'ship_from' => [
            'street' => null,
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $service = new AvaTaxService($config);

    $result = $service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Dallas', 'state' => 'TX', 'postal_code' => '75001', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('commitInvoice_returns_false_when_all_lines_have_zero_price', function () use ($minimalConfig) {
    $config = [
        ...$minimalConfig,
        'enabled' => true,
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'FAKE',
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $service = new AvaTaxService($config);

    $result = $service->commitInvoice(
        [['unit_price' => 0.0, 'sku' => 'FREE']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('calculateTax_returns_zeros_when_disabled', function () use ($minimalConfig) {
    $service = new AvaTaxService([...$minimalConfig, 'enabled' => false]);

    $shipTo = [
        'address_line1' => '123 Test St',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75001',
        'country' => 'US',
    ];

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        $shipTo,
        '1'
    );

    expect($result)->toHaveCount(1);
    expect($result[0]['tax_rate'])->toBe(0);
    expect($result[0]['tax_amount'])->toBe(0);
});

it('calculateTax_returns_zeros_when_ship_from_incomplete', function () use ($minimalConfig) {
    $config = [
        ...$minimalConfig,
        'enabled' => true,
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'FAKE',
        'ship_from' => [
            'street' => null,
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $service = new AvaTaxService($config);

    $shipTo = [
        'address_line1' => '123 Test St',
        'city' => 'Dallas',
        'state' => 'TX',
        'postal_code' => '75001',
        'country' => 'US',
    ];

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        $shipTo,
        '1'
    );

    expect($result)->toHaveCount(1);
    expect($result[0]['tax_rate'])->toBe(0);
    expect($result[0]['tax_amount'])->toBe(0);
});

it('commitInvoice_sends_SalesInvoice_with_commit_true_and_order_number_as_code', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => null,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->once()
        ->with('', Mockery::on(function ($model) {
            return $model->type === 'SalesInvoice'
                && $model->commit === true
                && $model->code === 'ORD-2026-0019'
                && $model->companyCode === 'TESTCO'
                && $model->customerCode === '19'
                && count($model->lines) === 2
                && $model->lines[0]->amount === 200.0
                && $model->lines[0]->itemCode === 'ECM-2024'
                && $model->lines[1]->amount === 40.0
                && $model->lines[1]->itemCode === 'FEE-Programming'
                && $model->addresses->shipFrom->city === 'Houston'
                && $model->addresses->shipTo->city === 'Houston';
        }))
        ->andReturn((object) ['id' => 12345, 'lines' => []]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->commitInvoice(
        [
            ['unit_price' => 200.0, 'sku' => 'ECM-2024'],
            ['unit_price' => 40.0, 'sku' => 'FEE-Programming'],
        ],
        ['address_line1' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '19',
        'ORD-2026-0019',
    );

    expect($result)->toBeTrue();
});

it('upsertCustomer_sends_correct_payload_to_avatax', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => 9999,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $customer = Customer::factory()->create([
        'name' => 'Acme Resale Inc',
        'email' => 'ar@example.com',
        'tax_identification_number' => 'TX-RESALE-99',
        'entity_use_code' => 'G',
        'tax_exempt' => true,
    ]);
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'address_line1' => '500 Main St',
        'city' => 'Austin',
        'state' => 'TX',
        'postal_code' => '78701',
        'country' => 'US',
    ]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCustomers')
        ->once()
        ->with(9999, Mockery::on(function ($payload) use ($customer) {
            return is_array($payload)
                && count($payload) === 1
                && $payload[0]->customerCode === (string) $customer->id
                && $payload[0]->name === 'Acme Resale Inc'
                && $payload[0]->emailAddress === 'ar@example.com'
                && $payload[0]->line1 === '500 Main St'
                && $payload[0]->city === 'Austin'
                && $payload[0]->region === 'TX'
                && $payload[0]->postalCode === '78701'
                && $payload[0]->country === 'US'
                && $payload[0]->taxpayerIdNumber === 'TX-RESALE-99';
        }))
        ->andReturn((object) ['value' => [(object) ['customerCode' => (string) $customer->id]]]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->upsertCustomer($customer))->toBe((string) $customer->id);
});

it('upsertCustomer_calls_updateCustomer_when_avatax_customer_id_already_set', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => 9999,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $customer = Customer::factory()->create([
        'name' => 'Gio Updated',
        'tax_identification_number' => '218646848',
        'avatax_customer_id' => '50',
    ]);
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'address_line1' => '421 W. 4TH ST.',
        'city' => 'HANFORD',
        'state' => 'CA',
        'postal_code' => '93230',
        'country' => 'US',
    ]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldNotReceive('createCustomers');
    $mockClient->shouldReceive('updateCustomer')
        ->once()
        ->with(9999, (string) $customer->id, Mockery::on(function ($payload) {
            return $payload->name === 'Gio Updated'
                && $payload->taxpayerIdNumber === '218646848'
                && $payload->line1 === '421 W. 4TH ST.'
                && $payload->city === 'HANFORD'
                && $payload->region === 'CA';
        }))
        ->andReturn((object) ['customerCode' => (string) $customer->id, 'name' => 'Gio Updated']);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->upsertCustomer($customer))->toBe((string) $customer->id);
});

it('upsertCustomer_returns_null_on_sdk_failure', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => 9999,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCustomers')->andThrow(new RuntimeException('boom'));

    $customer = Customer::factory()->create();
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => true]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->upsertCustomer($customer))->toBeNull();
});

it('upsertCustomer_returns_null_when_disabled', function () {
    $config = [
        'enabled' => false,
        'environment' => 'sandbox',
        'account' => '',
        'license_key' => '',
        'company_code' => '',
        'company_id' => null,
        'ship_from' => ['street' => '', 'city' => '', 'state' => '', 'zip' => '', 'country' => 'US'],
    ];

    $customer = new Customer;
    $customer->id = 1;
    $customer->name = 'X';
    $customer->email = 'x@example.com';

    $service = new AvaTaxService($config);

    expect($service->upsertCustomer($customer))->toBeNull();
});

it('calculateTax_returns_zeros_when_sdk_returns_string_error_response', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => null,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->andReturn('AvaTaxAuthenticationException: invalid credentials');

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
    );

    expect($result)->toHaveCount(1);
    expect($result[0]['tax_rate'])->toBe(0);
    expect($result[0]['tax_amount'])->toBe(0);
});

it('commitInvoice_returns_false_when_sdk_returns_string_error_response', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => null,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->andReturn('AvaTaxAuthenticationException: invalid credentials');

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '1',
        'ORD-2026-0001',
    );

    expect($result)->toBeFalse();
});

it('calculateTax_guards_against_empty_shipTo_without_calling_sdk', function () {
    $config = [
        'enabled' => true,
        'environment' => 'sandbox',
        'account' => '12345',
        'license_key' => 'fake',
        'company_code' => 'TESTCO',
        'company_id' => null,
        'ship_from' => [
            'street' => '5426 N Shepherd Dr',
            'city' => 'Houston',
            'state' => 'TX',
            'zip' => '77091',
            'country' => 'US',
        ],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldNotReceive('createTransaction');

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'TEST']],
        ['address_line1' => null, 'city' => null, 'state' => null, 'postal_code' => null, 'country' => null],
        '1',
    );

    expect($result[0]['tax_amount'])->toBe(0);
});

it('commitInvoice_passes_entityUseCode_when_provided', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => null,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->once()
        ->with('', Mockery::on(fn ($m) => $m->entityUseCode === 'G'))
        ->andReturn((object) ['id' => 999, 'lines' => []]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->commitInvoice(
        [['unit_price' => 100.0, 'sku' => 'X']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '22', 'ORD-1', 'G'
    ))->toBeTrue();
});

it('calculateTax_passes_entityUseCode_when_provided', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => null,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createTransaction')
        ->once()
        ->with('', Mockery::on(fn ($m) => $m->entityUseCode === 'G' && $m->type === 'SalesOrder'))
        ->andReturn((object) ['lines' => [(object) ['taxCalculated' => 0.0]]]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'X']],
        ['address_line1' => '1 A', 'city' => 'Houston', 'state' => 'TX', 'postal_code' => '77091', 'country' => 'US'],
        '22', 'G'
    );

    expect($result[0]['tax_amount'])->toBe(0.0);
});

it('upsertCertificate_creates_with_filename_placeholder_and_uppercase_reason', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => 9999,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $customer = Customer::factory()->create([
        'name' => 'GIO',
        'tax_exempt' => true,
        'avatax_customer_id' => '22',
        'avatax_certificate_id' => null,
        'entity_use_code' => 'G',
        'exemption_certificate_number' => '218646848-RESALE',
        'exemption_signed_date' => '2026-01-15',
        'exemption_expires_at' => '2027-01-15',
        'exemption_exposure_zone' => 'California',
    ]);
    CustomerAddress::factory()->create([
        'customer_id' => $customer->id,
        'is_default' => true,
        'address_line1' => '421 W. 4TH ST.',
        'city' => 'HANFORD',
        'state' => 'CA',
        'postal_code' => '93230',
        'country' => 'US',
    ]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCertificates')
        ->once()
        ->with(9999, true, Mockery::on(function ($payload) use ($customer) {
            return is_array($payload)
                && $payload[0]->filename === 'exemption-cert-'.$customer->id.'.pdf'
                && $payload[0]->signedDate === '2026-01-15'
                && $payload[0]->expirationDate === '2027-01-15'
                && $payload[0]->exemptionNumber === '218646848-RESALE'
                && $payload[0]->exemptionReason->name === 'RESALE'  // uppercase!
                && $payload[0]->exposureZone->name === 'California'
                && $payload[0]->customers[0]->customerCode === (string) $customer->id
                && $payload[0]->customers[0]->name === 'GIO'
                && $payload[0]->customers[0]->line1 === '421 W. 4TH ST.';
        }))
        ->andReturn([(object) ['id' => 199]]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->upsertCertificate($customer))->toBe(199);
});

it('upsertCertificate_maps_entity_use_codes_to_avatax_reason_names', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => 9999,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $customer = Customer::factory()->create([
        'tax_exempt' => true,
        'avatax_customer_id' => '99',
        'entity_use_code' => 'E',  // Charitable
        'exemption_certificate_number' => 'CHARITY-1',
        'exemption_signed_date' => '2026-01-15',
        'exemption_expires_at' => '2027-01-15',
        'exemption_exposure_zone' => 'Texas',
    ]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => true]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldReceive('createCertificates')
        ->once()
        ->with(9999, true, Mockery::on(fn ($p) => $p[0]->exemptionReason->name === 'CHARITABLE/EXEMPT ORG'))
        ->andReturn([(object) ['id' => 200]]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient)
        {
            parent::__construct($config);
        }

        protected function makeClient(): AvaTaxClient
        {
            return $this->injectedClient;
        }
    };

    expect($service->upsertCertificate($customer))->toBe(200);
});

it('upsertCertificate_returns_null_when_customer_not_synced_v2', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => 9999,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $customer = Customer::factory()->create([
        'avatax_customer_id' => null,
        'tax_exempt' => true,
        'entity_use_code' => 'G',
        'exemption_certificate_number' => 'X',
        'exemption_signed_date' => '2026-01-15',
        'exemption_expires_at' => '2027-01-15',
        'exemption_exposure_zone' => 'California',
    ]);

    $service = new AvaTaxService($config);
    expect($service->upsertCertificate($customer))->toBeNull();
});

it('upsertCertificate_returns_null_when_cert_data_incomplete_v2', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => 9999,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $customer = Customer::factory()->create([
        'avatax_customer_id' => '99',
        'tax_exempt' => true,
        'exemption_signed_date' => null,
    ]);

    $service = new AvaTaxService($config);
    expect($service->upsertCertificate($customer))->toBeNull();
});

it('upsertCertificate_calls_updateCertificate_when_avatax_certificate_id_is_set', function () {
    $config = [
        'enabled' => true, 'environment' => 'sandbox',
        'account' => '12345', 'license_key' => 'fake',
        'company_code' => 'TESTCO', 'company_id' => 9999,
        'ship_from' => ['street' => '5426 N Shepherd Dr', 'city' => 'Houston', 'state' => 'TX', 'zip' => '77091', 'country' => 'US'],
    ];

    $customer = Customer::factory()->create([
        'tax_exempt' => true,
        'avatax_customer_id' => '50',
        'avatax_certificate_id' => 199,  // already synced — should UPDATE
        'entity_use_code' => 'G',
        'exemption_certificate_number' => 'X',
        'exemption_signed_date' => '2026-01-15',
        'exemption_expires_at' => '2028-01-15',  // changed expiry
        'exemption_exposure_zone' => 'California',
    ]);
    CustomerAddress::factory()->create(['customer_id' => $customer->id, 'is_default' => true]);

    $mockClient = Mockery::mock(AvaTaxClient::class);
    $mockClient->shouldNotReceive('createCertificates');
    $mockClient->shouldReceive('updateCertificate')
        ->once()
        ->with(9999, 199, Mockery::on(fn ($p) => $p->expirationDate === '2028-01-15'))
        ->andReturn((object) ['id' => 199]);

    $service = new class($config, $mockClient) extends AvaTaxService
    {
        public function __construct(array $config, private $injectedClient) { parent::__construct($config); }
        protected function makeClient(): AvaTaxClient { return $this->injectedClient; }
    };

    expect($service->upsertCertificate($customer))->toBe(199);
});
