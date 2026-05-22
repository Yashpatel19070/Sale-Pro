# Customer Portal — Profile Module — Permissions

## No Spatie Permissions for Customers

Customers do NOT use Spatie roles or permissions.
Guard separation replaces role-based access control for the portal.

| Old (removed) | New (correct) |
|---------------|--------------|
| `role:customer` middleware | `auth:customer` guard |
| `CustomerRoleSeeder` | Deleted — do not recreate |
| `assignRole('customer')` in service | Not needed — guard handles this |
| `hasRole('customer')` check | Not needed — guard enforces customer-only |

## How Access Is Controlled

```
auth:customer middleware
  → checks customers table via customer guard
  → staff Users never match (different table, different model)
  → no Spatie role check needed
```

## Profile Module Specifically

All profile routes sit inside the authenticated portal route group defined in `portal-foundation/00-overview.md`.
That group carries `['auth:customer', 'verified:portal.verification.notice', 'customer.active']`.

Profile controller does no `$this->authorize()` call — customer acts on their own data only.
Ownership is implicit: `$request->user('customer')` IS the customer being viewed/edited.

## CustomerRoleSeeder

**Deleted.** Do not reference it, do not recreate it.
Removed from `DatabaseSeeder` and `E2ESeeder` as part of portal-foundation implementation.
