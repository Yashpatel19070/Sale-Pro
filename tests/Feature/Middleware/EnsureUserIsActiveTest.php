<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});

it('allows active user through', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Active])->assignRole('sales');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('logs out and redirects suspended user', function (): void {
    $user = User::factory()->create(['status' => UserStatus::Suspended])->assignRole('sales');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('logs out and redirects inactive user', function (): void {
    $user = User::factory()->inactive()->create()->assignRole('sales');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
