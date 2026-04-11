<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class CustomerService
{
    // ── Read ──────────────────────────────────────────────────────────────────

    /**
     * Paginated, filtered customer list scoped to the requesting user's access.
     *
     * Role priority (checked in order): admin > manager > sales
     * A user with multiple roles always gets the highest-privilege scope.
     *
     * @param array{
     *   search?: string,
     *   status?: string,
     *   source?: string,
     *   assigned_to?: int,
     *   department_id?: int,
     * } $filters
     */
    public function list(User $actor, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Customer::with(['assignedTo:id,name', 'department:id,name']);

        // Scope by role priority — admin sees all, manager sees dept, sales sees assigned
        if ($actor->hasRole('admin')) {
            // No additional scope — admin sees everything
        } elseif ($actor->hasRole('manager') && $actor->department_id !== null) {
            $query->inDepartment($actor->department_id);
        } else {
            // Sales (or manager without dept) — scope to assigned only
            $query->assignedTo($actor->id);
        }

        // Apply filters
        $query
            ->when(isset($filters['search']), fn ($q) => $q->search($filters['search']))
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['source']), fn ($q) => $q->where('source', $filters['source']))
            ->when(isset($filters['assigned_to']), fn ($q) => $q->where('assigned_to', $filters['assigned_to']))
            ->when(isset($filters['department_id']), fn ($q) => $q->inDepartment($filters['department_id']))
            ->latest();

        return $query->paginate($perPage)->withQueryString();
    }

    // ── Write ─────────────────────────────────────────────────────────────────

    public function create(array $data): Customer
    {
        // created_by / updated_by stamped by CustomerObserver.
        // No defaults applied here — status and source are required fields validated
        // by StoreCustomerRequest. Country is nullable and stored as-is.
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        // updated_by stamped by CustomerObserver
        $customer->update($data);

        return $customer->refresh();
    }

    public function changeStatus(Customer $customer, CustomerStatus $status): Customer
    {
        // updated_by stamped by CustomerObserver
        $customer->update(['status' => $status]);

        return $customer->refresh();
    }

    public function assign(Customer $customer, ?int $userId): Customer
    {
        // updated_by stamped by CustomerObserver
        $customer->update(['assigned_to' => $userId]);

        return $customer->refresh();
    }

    public function delete(Customer $customer): void
    {
        $customer->delete();
    }

    public function restore(Customer $customer): Customer
    {
        $customer->restore();

        return $customer->refresh();
    }

    // ── Future: Import / Export ───────────────────────────────────────────────
    // CSV import and export will be added as a dedicated ImportExport module.
    // Do NOT add importCsv() or exportCsv() here.
}
