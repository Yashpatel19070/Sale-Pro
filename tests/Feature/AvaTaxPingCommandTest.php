<?php

declare(strict_types=1);

use App\Services\AvaTaxService;

it('outputs_credential_info_and_success_message', function () {
    $stub = Mockery::mock(AvaTaxService::class);
    $stub->shouldReceive('ping')->andReturn([
        'success' => true,
        'environment' => 'sandbox',
        'account' => '1234567890',
        'company_code' => 'TESTCO',
        'version' => '26.5.0',
        'message' => 'AvaTax connection successful.',
    ]);
    $this->app->instance(AvaTaxService::class, $stub);

    $this->artisan('avatax:ping')
        ->expectsOutputToContain('sandbox')
        ->expectsOutputToContain('1234567890')
        ->expectsOutputToContain('TESTCO')
        ->expectsOutputToContain('Connection successful')
        ->assertExitCode(0);
});

it('outputs_failure_message_and_exits_1', function () {
    $stub = Mockery::mock(AvaTaxService::class);
    $stub->shouldReceive('ping')->andReturn([
        'success' => false,
        'environment' => 'sandbox',
        'account' => '1234567890',
        'company_code' => 'TESTCO',
        'version' => null,
        'message' => 'Unauthorized',
    ]);
    $this->app->instance(AvaTaxService::class, $stub);

    $this->artisan('avatax:ping')
        ->expectsOutputToContain('Connection failed')
        ->assertExitCode(1);
});

it('does_not_output_license_key', function () {
    $stub = Mockery::mock(AvaTaxService::class);
    $stub->shouldReceive('ping')->andReturn([
        'success' => true,
        'environment' => 'sandbox',
        'account' => '1234567890',
        'company_code' => 'TESTCO',
        'version' => '26.5.0',
        'message' => 'AvaTax connection successful.',
    ]);
    $this->app->instance(AvaTaxService::class, $stub);

    $this->artisan('avatax:ping')
        ->expectsOutputToContain('Account')
        ->doesntExpectOutputToContain('license')
        ->assertExitCode(0);
});
