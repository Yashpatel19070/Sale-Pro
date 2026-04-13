<?php

declare(strict_types=1);

use App\Models\Customer;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = new AuditLogService;
});

it('list returns all activities when no filters given', function () {
    activity()->log('created');
    activity()->log('updated');

    expect($this->service->list()->total())->toBe(2);
});

it('list filters by event description', function () {
    activity()->log('created');
    activity()->log('deleted');

    $result = $this->service->list(['event' => 'created']);

    expect($result->total())->toBe(1);
    expect($result->first()->description)->toBe('created');
});

it('list filters by log name', function () {
    activity('auth')->log('login');
    activity()->log('created');

    expect($this->service->list(['log_name' => 'auth'])->total())->toBe(1);
});

it('list filters by subject type', function () {
    $customer = Customer::withoutEvents(fn () => Customer::factory()->create());

    activity()->performedOn($customer)->log('created');
    activity()->log('login');

    expect($this->service->list(['subject_type' => Customer::class])->total())->toBe(1);
});

it('list filters by causer_id', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    activity()->causedBy($userA)->log('updated');
    activity()->causedBy($userB)->log('updated');

    expect($this->service->list(['causer_id' => $userA->id])->total())->toBe(1);
});

it('list orders newest first', function () {
    activity()->log('first');
    activity()->log('second');

    expect($this->service->list()->first()->description)->toBe('second');
});

it('list ignores empty string filters', function () {
    activity()->log('login');
    activity()->log('created');

    expect($this->service->list(['event' => '', 'log_name' => ''])->total())->toBe(2);
});
