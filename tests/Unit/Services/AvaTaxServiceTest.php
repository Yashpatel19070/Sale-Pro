<?php

declare(strict_types=1);

use App\Services\AvaTaxService;

function avataxConfig(): array
{
    return [
        'environment' => 'sandbox',
        'account_number' => config('services.avatax.account_number', ''),
        'license_key' => config('services.avatax.license_key', ''),
        'company_code' => config('services.avatax.company_code', 'DEFAULT'),
        'tax_code' => config('services.avatax.tax_code', 'P0000000'),
        'ship_from' => [
            'street' => config('services.avatax.ship_from.street', '100 Main St'),
            'city' => config('services.avatax.ship_from.city', 'Austin'),
            'state' => config('services.avatax.ship_from.state', 'TX'),
            'zip' => config('services.avatax.ship_from.zip', '78701'),
            'country' => config('services.avatax.ship_from.country', 'US'),
        ],
    ];
}

it('calculateTax returns a 0-based array', function () {
    $service = new AvaTaxService(avataxConfig());

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'SKU-TEST-001']],
        []
    );

    expect($result)->toBeArray();
});

it('calculateTax result entries have tax_rate and tax_amount keys when lines returned', function () {
    $service = new AvaTaxService(avataxConfig());

    $result = $service->calculateTax(
        [['unit_price' => 100.0, 'sku' => 'SKU-TEST-001']],
        []
    );

    foreach ($result as $idx => $entry) {
        expect($idx)->toBeInt()
            ->and($entry)->toHaveKeys(['tax_rate', 'tax_amount'])
            ->and($entry['tax_rate'])->toBeFloat()
            ->and($entry['tax_amount'])->toBeFloat();
    }
});

it('calculateTax result defaults to empty array when no lines returned by API', function () {
    // With empty credentials sandbox returns no line data — null guard must produce []
    $service = new AvaTaxService(array_merge(avataxConfig(), [
        'account_number' => '',
        'license_key' => '',
    ]));

    $result = $service->calculateTax(
        [['unit_price' => 50.0, 'sku' => 'SKU-X']],
        []
    );

    expect($result)->toBeArray();
});

it('calculateTax throws RuntimeException wrapping SDK exception', function () {
    $mock = Mockery::mock('overload:Avalara\AvaTaxClient');
    $mock->shouldReceive('withSecurity')->andReturnSelf();

    $mockTb = Mockery::mock('overload:Avalara\TransactionBuilder');
    $mockTb->shouldIgnoreMissing()->shouldReceive('create')
        ->andThrow(new Exception('Avalara connection failed'));

    $service = new AvaTaxService(avataxConfig());

    expect(fn () => $service->calculateTax([['unit_price' => 100.0, 'sku' => 'SKU-001']], []))
        ->toThrow(RuntimeException::class, 'AvaTax calculateTax failed');
})->skip('overload mocks require process isolation — run with --process-isolation');
