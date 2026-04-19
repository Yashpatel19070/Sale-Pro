# Supplier Module — Tests

## Feature Tests: `SupplierControllerTest`

File: `tests/Feature/SupplierControllerTest.php`

### Setup
```php
beforeEach(function () {
    $this->seed(SupplierPermissionSeeder::class);

    $this->admin       = User::factory()->create()->assignRole('admin');
    $this->manager     = User::factory()->create()->assignRole('manager');
    $this->procurement = User::factory()->create()->assignRole('procurement');
    $this->warehouse   = User::factory()->create()->assignRole('warehouse');
});
```

### Index (GET /admin/suppliers)
- `index returns 200 for admin` — admin sees supplier list
- `index returns 200 for manager`
- `index returns 200 for procurement`
- `index returns 403 for warehouse` — no viewAny permission
- `index filters by search` — supplier with matching name appears in results
- `index filters by status active` — only active suppliers returned
- `index filters by status inactive` — only inactive (soft-deleted) suppliers returned

### Show (GET /admin/suppliers/{id})
- `show returns 200 for admin`
- `show returns 200 for procurement`
- `show returns 403 for warehouse`
- `show resolves soft-deleted supplier` — withTrashed on route

### Create / Store (GET + POST /admin/suppliers)
- `create returns 200 for admin`
- `create returns 403 for procurement`
- `store creates supplier and redirects` — admin can create, code auto-generated, redirect to show
- `store requires name` — validation error on missing name
- `store rejects duplicate name` — unique name validation
- `store allows name of soft-deleted supplier` — withoutTrashed unique rule
- `store auto-generates sequential code` — first supplier gets SUP-0001, second gets SUP-0002
- `store returns 403 for procurement`

### Edit / Update (GET + PATCH /admin/suppliers/{id})
- `edit returns 200 for admin`
- `edit returns 403 for procurement`
- `update saves changes and redirects to show`
- `update cannot change code` — code stays the same after update
- `update rejects duplicate name` — unique excluding self
- `update returns 403 for procurement`

### Destroy (DELETE /admin/suppliers/{id})
- `destroy deactivates supplier` — soft-deleted, is_active=false
- `destroy redirects to index with success message`
- `destroy returns 403 for procurement`
- `destroy returns error when supplier has open POs` — DomainException from service becomes back with error (add after PO module)

### Restore (POST /admin/suppliers/{id}/restore)
- `restore reactivates supplier` — deleted_at=null, is_active=true
- `restore redirects to show with success message`
- `restore returns 403 for procurement`
- `restore resolves soft-deleted supplier via withTrashed`

---

## Unit Tests: `SupplierServiceTest`

File: `tests/Unit/Services/SupplierServiceTest.php`

### Setup
```php
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(SupplierService::class);
});
```

### list()
- `list() returns paginated suppliers` — 30 suppliers → paginator total=30, count=25
- `list() filters by search on name` — partial name match
- `list() filters by search on code` — partial code match
- `list() filters by status active` — only non-deleted
- `list() filters by status inactive` — only soft-deleted
- `list() includes soft-deleted in results` — withTrashed, all appear without status filter

### create()
- `create() persists supplier with correct fields`
- `create() auto-generates code SUP-0001 for first supplier`
- `create() auto-generates sequential codes` — 3 suppliers get SUP-0001, 0002, 0003
- `create() sets is_active true`
- `create() allows nullable optional fields`

### update()
- `update() saves name and contact fields`
- `update() does not change code`
- `update() allows clearing optional fields` — pass null, field becomes null

### deactivate()
- `deactivate() soft-deletes and sets is_active false`
- `deactivate() sets deleted_at`

### restore()
- `restore() clears deleted_at and sets is_active true`

### generateCode()
- `generateCode() returns SUP-0001 when no suppliers exist`
- `generateCode() increments based on max id` — supplier with id=5 → next is SUP-0006
- `generateCode() includes soft-deleted in max count` — deleted supplier counts toward sequence
