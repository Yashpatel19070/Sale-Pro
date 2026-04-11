<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('allows admin through admin-gated route', function (): void {
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)
        ->get(route('roles.index'))
        ->assertOk();
});

it('denies manager from admin-gated route', function (): void {
    $manager = User::factory()->create()->assignRole('manager');

    $this->actingAs($manager)
        ->get(route('roles.index'))
        ->assertForbidden();
});

it('denies sales from admin-gated route', function (): void {
    $sales = User::factory()->create()->assignRole('sales');

    $this->actingAs($sales)
        ->get(route('roles.index'))
        ->assertForbidden();
});
