# Customer Module — Tests

## Feature Tests: `tests/Feature/Customers/CustomerControllerTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
});
```

### Auth / guest guard
```
it('redirects guests from customers index')
it('redirects guests from customer create page')
```

### Index — role visibility
```
it('admin can view customers index')
it('manager can view customers index')
it('sales can view customers index')
```

### Index — scoped data
```
it('admin sees all customers on index')
it('manager sees only dept customers on index')
it('sales sees only assigned customers on index')
it('manager without department sees no customers')
```

### Create / Store
```
it('admin can access create page')
it('manager can access create page')
it('sales cannot access create page')       // 403
it('admin can create a customer')
it('manager can create a customer')
it('sales cannot create a customer')        // 403
it('store fails validation when first_name missing')
it('store fails validation when duplicate email')
```

### Show
```
it('admin can view any customer')
it('manager can view own-dept customer')
it('manager cannot view out-of-dept customer')   // 403
it('sales can view assigned customer')
it('sales cannot view unassigned customer')      // 403
```

### Edit / Update
```
it('admin can access edit page for any customer')
it('sales can access edit page for assigned customer')
it('sales cannot access edit page for unassigned customer')  // 403
it('admin can update a customer')
it('sales can update assigned customer')
it('sales cannot update unassigned customer')    // 403
it('update ignores own email uniqueness check')
it('update fails validation with invalid status enum')
```

### Delete / Restore
```
it('admin can delete a customer')
it('manager cannot delete a customer')           // 403
it('sales cannot delete a customer')             // 403
it('delete soft-deletes the customer')
it('admin can restore a deleted customer')
it('manager cannot restore a customer')          // 403
```

### Assign
```
it('admin can assign customer to sales rep')
it('manager can assign customer to sales rep')
it('sales cannot assign a customer')             // 403
it('assign with null clears the assignment')
it('assign rejects non-existent user id')
```

### Change Status
```
it('admin can change customer status')
it('manager can change own-dept customer status')
it('sales cannot change customer status')        // 403 — even on assigned customer
it('change-status rejects invalid status value')
```

---

## Unit Tests: `tests/Unit/Services/CustomerServiceTest.php`

```php
<?php

declare(strict_types=1);

use App\Enums\CustomerStatus;
use App\Models\Customer;
use App\Models\Department;
use App\Models\User;
use App\Services\CustomerService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RoleSeeder::class);
    $this->service = new CustomerService;
    $this->admin   = User::factory()->create()->assignRole('admin');
});
```

### list() — role scoping
```
it('admin list returns all customers')
it('manager list scoped to own department')
it('manager without department returns assigned-only customers')
it('sales list scoped to assigned customers only')
it('admin with manager role uses admin scope (priority check)')
```

### list() — filters
```
it('list filters by search term on name')
it('list filters by search term on email')
it('list filters by search term on company_name')
it('list filters by status')
it('list filters by source')
it('list filters by assigned_to')
```

### create()
```
it('creates a customer record')
it('observer stamps created_by from authenticated user')
it('observer stamps updated_by from authenticated user')
```

### update()
```
it('updates customer fields')
it('observer stamps updated_by on update')
it('returns refreshed customer after update')
```

### changeStatus()
```
it('transitions status to prospect')
it('transitions status to active')
it('transitions status to churned')
it('observer stamps updated_by on status change')
```

### assign()
```
it('assigns customer to a user')
it('clears assignment when null passed')
it('observer stamps updated_by on assign')
```

### delete() / restore()
```
it('soft-deletes the customer')
it('deleted customer not found in normal queries')
it('restores a soft-deleted customer')
it('restored customer appears in normal queries')
```

---

## Observer Tests: `tests/Unit/Observers/CustomerObserverTest.php`

```
it('stamps created_by and updated_by when authenticated user creates customer')
it('stamps updated_by when authenticated user updates customer')
it('does not stamp when no authenticated user (e.g. seeder)')
```

---

## Test Helpers

```php
// Create a manager with a department
$dept    = Department::factory()->create();
$manager = User::factory()->create(['department_id' => $dept->id])->assignRole('manager');

// Create a customer in that department
$customer = Customer::factory()->inDepartment($dept->id)->create();

// Create an assigned customer for a sales user
$sales    = User::factory()->create()->assignRole('sales');
$customer = Customer::factory()->assignedTo($sales->id)->create();
```

## Coverage Targets
- Feature tests: every public controller action
- Unit tests: every CustomerService method + CustomerObserver
- Minimum: 80% line coverage on Customer module files
