# Permissions Module — Tests

## Feature Tests

File: `tests/Feature/Roles/RoleControllerTest.php`

```
it('redirects guests from roles index')
it('denies manager from roles index')
it('denies sales from roles index')
it('admin can view roles index')
it('admin can view role show page')
it('admin can view role edit page')
it('admin can update role permissions')
it('update validates permissions exist')
```

## Middleware Tests

File: `tests/Feature/Middleware/EnsureUserIsActiveTest.php`

```
it('allows active user through')
it('logs out and redirects suspended user')
it('logs out and redirects inactive user')
```

File: `tests/Feature/Middleware/EnsureIsAdminTest.php`

```
it('allows admin role through')
it('denies manager role')
it('denies sales role')
```

## Unit Tests

File: `tests/Unit/Services/RoleServiceTest.php`

```
it('syncs permissions onto role')
it('clears permission cache after sync')
it('returns refreshed role after sync')
```

## Test Setup Notes

- All feature tests use `RefreshDatabase` + `$this->seed(RoleSeeder::class)`
- Admin middleware tests hit a route protected by `['admin']` middleware
- RoleService unit test uses a real DB (permission cache needs Spatie registrar)
