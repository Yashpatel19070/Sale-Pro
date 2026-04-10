<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->service = new DepartmentService();
});

it('creates a department with uppercased code', function (): void {
    $dept = $this->service->create(['name' => 'Finance', 'code' => 'fin']);

    expect($dept->code)->toBe('FIN');
    expect($dept->is_active)->toBeTrue();
});

it('sets is_active true by default', function (): void {
    $dept = $this->service->create(['name' => 'Test', 'code' => 'TST']);

    expect($dept->is_active)->toBeTrue();
});

it('updates a department', function (): void {
    $dept    = Department::factory()->create(['name' => 'Old', 'code' => 'OLD']);
    $updated = $this->service->update($dept, ['name' => 'New']);

    expect($updated->name)->toBe('New');
    expect($updated->code)->toBe('OLD');
});

it('throws when deleting department with active users', function (): void {
    $dept = Department::factory()->create();
    User::factory()->create(['department_id' => $dept->id, 'status' => 'active']);

    expect(fn () => $this->service->delete($dept))
        ->toThrow(\RuntimeException::class);
});

it('allows deleting department with only inactive users', function (): void {
    $dept = Department::factory()->create();
    User::factory()->create(['department_id' => $dept->id, 'status' => 'inactive']);

    $this->service->delete($dept);

    $this->assertSoftDeleted('departments', ['id' => $dept->id]);
});

it('allows deleting department with no users', function (): void {
    $dept = Department::factory()->create();

    $this->service->delete($dept);

    $this->assertSoftDeleted('departments', ['id' => $dept->id]);
});

it('toggles is_active from true to false', function (): void {
    $dept    = Department::factory()->create(['is_active' => true]);
    $updated = $this->service->toggleActive($dept);

    expect($updated->is_active)->toBeFalse();
});

it('toggles is_active from false to true', function (): void {
    $dept    = Department::factory()->create(['is_active' => false]);
    $updated = $this->service->toggleActive($dept);

    expect($updated->is_active)->toBeTrue();
});

it('restores a soft-deleted department', function (): void {
    $dept = Department::factory()->create();
    $dept->delete();

    $trashed  = Department::onlyTrashed()->findOrFail($dept->id);
    $restored = $this->service->restore($trashed);

    expect($restored->deleted_at)->toBeNull();
});

it('returns only active departments in dropdown', function (): void {
    Department::factory()->create(['is_active' => true]);
    Department::factory()->create(['is_active' => true]);
    Department::factory()->inactive()->create();

    $list = $this->service->dropdown();

    expect($list)->toHaveCount(2);
});

it('dropdown returns id name code only', function (): void {
    Department::factory()->create(['is_active' => true]);

    $item = $this->service->dropdown()->first();

    expect($item->getAttributes())->toHaveKeys(['id', 'name', 'code']);
});
