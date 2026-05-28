<?php

declare(strict_types=1);

use App\Jobs\SyncCustomerToAvaTaxJob;
use App\Models\Customer;
use App\Services\AvaTaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

afterEach(fn () => Mockery::close());

it('stores avatax_customer_id and avatax_synced_at on success', function () {
    $customer = Customer::factory()->create(['avatax_customer_id' => null]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')
        ->once()
        ->with(Mockery::on(fn ($c) => $c->id === $customer->id))
        ->andReturn('AVATAX-CODE-X');
    $svc->shouldReceive('upsertCertificate')->andReturn(null);

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    $fresh = $customer->fresh();
    expect($fresh->avatax_customer_id)->toBe('AVATAX-CODE-X');
    expect($fresh->avatax_synced_at)->not->toBeNull();
});

it('leaves avatax_customer_id null on failure', function () {
    $customer = Customer::factory()->create(['avatax_customer_id' => null]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')->once()->andReturn(null);

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    $fresh = $customer->fresh();
    expect($fresh->avatax_customer_id)->toBeNull();
    expect($fresh->avatax_synced_at)->toBeNull();
});

it('also calls upsertCertificate when customer has cert data and saves cert id', function () {
    $customer = Customer::factory()->create([
        'tax_exempt' => true,
        'entity_use_code' => 'G',
        'exemption_certificate_number' => 'CERT-1',
        'exemption_signed_date' => '2026-01-15',
        'exemption_expires_at' => '2027-01-15',
        'exemption_exposure_zone' => 'California',
    ]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')->once()->andReturn('AVA-CODE');
    $svc->shouldReceive('upsertCertificate')->once()->andReturn(199);

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    $fresh = $customer->fresh();
    expect($fresh->avatax_customer_id)->toBe('AVA-CODE');
    expect($fresh->avatax_certificate_id)->toBe(199);
});

it('does not save cert id when upsertCertificate returns null', function () {
    $customer = Customer::factory()->create(['tax_exempt' => false]);

    $svc = Mockery::mock(AvaTaxService::class);
    $svc->shouldReceive('upsertCustomer')->once()->andReturn('AVA-CODE');
    $svc->shouldReceive('upsertCertificate')->once()->andReturn(null);

    (new SyncCustomerToAvaTaxJob($customer))->handle($svc);

    expect($customer->fresh()->avatax_certificate_id)->toBeNull();
});

it('failed() logs the exhausted job', function () {
    Illuminate\Support\Facades\Log::spy();

    $customer = Customer::factory()->create();
    (new SyncCustomerToAvaTaxJob($customer))->failed(new RuntimeException('boom'));

    Illuminate\Support\Facades\Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn ($msg, $ctx) => str_contains($msg, 'SyncCustomerToAvaTaxJob')
            && $ctx['customer_id'] === $customer->id);
});
