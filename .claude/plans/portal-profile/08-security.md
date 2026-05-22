# Customer Portal — Profile Module — Security

## Security Is Handled by the Foundation

All middleware, guard setup, rate limiting, session security, and headers are
documented and implemented in `portal-foundation/09-middleware.md`.

This module adds no new middleware. It relies entirely on what the foundation provides.

---

## What the Foundation Guarantees for Every Profile Request

By the time a request reaches `ProfileController`, the middleware stack has already verified:

| Check | Middleware | Result if fails |
|-------|-----------|----------------|
| Customer is authenticated | `auth:customer` | 302 → `portal.login` |
| Email is verified | `verified:portal.verification.notice` | 302 → `portal.verification.notice` |
| Account is active | `customer.active` | logout + 302 → `portal.login` |
| Request is not forged | `VerifyCsrfToken` (web) | 419 |
| Security headers set | `SecurityHeaders` (prepended) | Always fires |

---

## Profile Module's Own Security Contract

### Data isolation — no policy needed

Customer edits only their own record. `$request->user('customer')` IS the customer.
No `$this->authorize()` call — there is no other customer's data to accidentally access.

### Fields locked from customer

`UpdatePortalProfileRequest` rules contain only: `name`, `phone`, `company_name`.
Even if a malicious request sends `email` or `status`, FormRequest ignores them via `$request->validated()`.

```php
// Attacker POSTs: email=attacker@evil.com, status=active, name=Hacked
// $request->validated() returns only: ['name' => ..., 'phone' => ..., 'company_name' => ...]
// email and status never reach the service — they are not in rules()
```

### Password change — current password required

`changePassword()` calls `Hash::check($currentPassword, $customer->password)` before updating.
Throws `ValidationException` on mismatch — no password change without knowing the current one.
A stolen session alone is not enough to change the password.

---

## Security Checklist — Profile Module

Before marking profile module complete:

- [ ] All profile routes inside the authenticated portal group (not guest group)
- [ ] `$request->user('customer')` used in every action — never `auth()->user()`
- [ ] `$request->validated()` used — never `$request->all()`
- [ ] `email` and `status` absent from `UpdatePortalProfileRequest::rules()`
- [ ] `changePassword()` verifies current password before updating
- [ ] All forms have `@csrf`
- [ ] PUT forms have `@method('PUT')`
- [ ] Validation errors displayed under each field
