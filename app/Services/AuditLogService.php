<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Customer;
use App\Models\Department;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductListing;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Activitylog\Models\Activity;

class AuditLogService
{
    /**
     * Human-readable labels for subject_type filter dropdown.
     *
     * @var array<string, string>
     */
    public const SUBJECT_TYPES = [
        User::class => 'User',
        Customer::class => 'Customer',
        Department::class => 'Department',
        Product::class => 'Product',
        ProductListing::class => 'Product Listing',
        ProductCategory::class => 'Product Category',
    ];

    /**
     * All possible event descriptions for the event filter dropdown.
     *
     * @var array<string, string>
     */
    public const EVENTS = [
        'created' => 'Created',
        'updated' => 'Updated',
        'deleted' => 'Deleted',
        'restored' => 'Restored',
        'login' => 'Login',
        'logout' => 'Logout',
        'login-failed' => 'Login Failed',
    ];

    /**
     * Paginated activity log with filters.
     *
     * @param array{
     *   log_name?: string,
     *   subject_type?: string,
     *   causer_id?: int|string,
     *   event?: string,
     *   date_from?: string,
     *   date_to?: string
     * } $filters
     */
    public function list(array $filters = [], int $perPage = 30): LengthAwarePaginator
    {
        return Activity::with(['causer'])
            ->when(
                isset($filters['log_name']) && in_array($filters['log_name'], ['default', 'auth'], true),
                fn ($q) => $q->where('log_name', $filters['log_name'])
            )
            ->when(
                isset($filters['subject_type']) && array_key_exists($filters['subject_type'], self::SUBJECT_TYPES),
                fn ($q) => $q->where('subject_type', $filters['subject_type'])
            )
            ->when(
                isset($filters['causer_id']) && $filters['causer_id'] !== '',
                fn ($q) => $q->where('causer_id', $filters['causer_id'])
                    ->where('causer_type', User::class)
            )
            ->when(
                isset($filters['event']) && array_key_exists($filters['event'], self::EVENTS),
                fn ($q) => $q->where('description', $filters['event'])
            )
            ->when(
                isset($filters['date_from']) && $filters['date_from'] !== '',
                fn ($q) => $q->whereDate('created_at', '>=', $filters['date_from'])
            )
            ->when(
                isset($filters['date_to']) && $filters['date_to'] !== '',
                fn ($q) => $q->whereDate('created_at', '<=', $filters['date_to'])
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Users who have caused at least one activity (for the filter dropdown).
     *
     * @return Collection<int, User>
     */
    public function causers(): Collection
    {
        return User::orderBy('name')->get(['id', 'name']);
    }
}
