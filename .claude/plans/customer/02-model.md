# Customer Module — Model

## File: `app/Models/Customer.php`

```php
<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

// Observer registered in AppServiceProvider::boot() — NOT via #[ObservedBy] attribute.
// Reason: existing codebase pattern (UserObserver, DepartmentObserver) uses AppServiceProvider only.
// Using both would fire the observer twice on every save.
class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'company_name',
        'job_title',
        'status',
        'source',
        'assigned_to',
        'department_id',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postcode',
        'country',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'source' => CustomerSource::class,
        ];
    }

    // ── Computed ──────────────────────────────────────────────────────────────

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeAssignedTo(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeInDepartment(Builder $query, int $departmentId): Builder
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeWithStatus(Builder $query, CustomerStatus $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term): void {
            $q->where('first_name', 'like', "%{$term}%")
              ->orWhere('last_name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('company_name', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }
}
```

## Observer: `app/Observers/CustomerObserver.php`

Mirrors `UserObserver` — stamps `created_by` and `updated_by` automatically.
The service does **not** set these manually; the observer handles it for every write.

```php
<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    public function creating(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->created_by = Auth::id();
            $customer->updated_by = Auth::id();
        }
    }

    public function updating(Customer $customer): void
    {
        if (Auth::check()) {
            $customer->updated_by = Auth::id();
        }
    }
}
```

## Factory: `database/factories/CustomerFactory.php`

```php
<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'first_name'    => $this->faker->firstName(),
            'last_name'     => $this->faker->lastName(),
            'email'         => $this->faker->unique()->safeEmail(),
            'phone'         => $this->faker->phoneNumber(),
            'company_name'  => $this->faker->company(),
            'job_title'     => $this->faker->jobTitle(),
            'status'        => CustomerStatus::Lead,
            'source'        => $this->faker->randomElement(CustomerSource::cases()),
            'assigned_to'   => null,
            'department_id' => null,
            'address_line1' => $this->faker->streetAddress(),
            'city'          => $this->faker->city(),
            'state'         => $this->faker->state(),
            'postcode'      => $this->faker->postcode(),
            'country'       => 'Australia',
            'notes'         => null,
        ];
    }

    public function lead(): static
    {
        return $this->state(['status' => CustomerStatus::Lead]);
    }

    public function prospect(): static
    {
        return $this->state(['status' => CustomerStatus::Prospect]);
    }

    public function active(): static
    {
        return $this->state(['status' => CustomerStatus::Active]);
    }

    public function churned(): static
    {
        return $this->state(['status' => CustomerStatus::Churned]);
    }

    public function assignedTo(int $userId): static
    {
        return $this->state(['assigned_to' => $userId]);
    }

    public function inDepartment(int $departmentId): static
    {
        return $this->state(['department_id' => $departmentId]);
    }

    public function withoutEmail(): static
    {
        return $this->state(['email' => null]);
    }
}
```

## AppServiceProvider Registration

Add all three lines to `AppServiceProvider::boot()`. These must sit alongside the existing
`User::observe(...)`, `Route::bind('trashedUser', ...)`, and `Gate::policy(User::class, ...)` calls.

```php
// New imports to add at top of AppServiceProvider.php:
use App\Models\Customer;
use App\Observers\CustomerObserver;
use App\Policies\CustomerPolicy;

// Inside boot():
Gate::policy(Customer::class, CustomerPolicy::class);
Customer::observe(CustomerObserver::class);
Route::bind('trashedCustomer', fn ($id) => Customer::onlyTrashed()->findOrFail($id));
```

**Full `boot()` after Customer additions:**

```php
public function boot(): void
{
    Gate::policy(Department::class, DepartmentPolicy::class);
    Gate::policy(User::class, UserPolicy::class);
    Gate::policy(Customer::class, CustomerPolicy::class);     // ← new

    User::observe(UserObserver::class);
    Customer::observe(CustomerObserver::class);               // ← new

    Route::bind('trashedDepartment', fn ($id) => Department::onlyTrashed()->findOrFail($id));
    Route::bind('trashedUser',       fn ($id) => User::onlyTrashed()->findOrFail($id));
    Route::bind('trashedCustomer',   fn ($id) => Customer::onlyTrashed()->findOrFail($id)); // ← new

    // Superadmin Gate::before() ... (unchanged)
}
```
