# Customer Portal — Profile Module — Schema

## No Migration Required

Profile module adds no new database columns.

All auth columns were added by `portal-foundation/01-schema.md`:
- `password` (nullable)
- `remember_token`
- `email_verified_at`

The `user_id` column was **dropped** by `portal-foundation/01-schema.md`.
Do NOT re-add it — Customer is its own auth record with no link to `users`.

---

## Columns This Module Reads/Writes

All already exist in `customers` table from the base customer module migration:

| Column | Operation | Notes |
|--------|-----------|-------|
| `name` | read + write | updateProfile() |
| `email` | read only | admin-only field — never in FormRequest |
| `phone` | read + write | updateProfile() |
| `company_name` | read + write | updateProfile(), nullable |
| `password` | write only | changePassword() via Hash::make — never exposed directly |
| `status` | read only | admin-only field — never in FormRequest |

---

## What Customer Cannot Change

| Column | Enforced by |
|--------|------------|
| `email` | Not in `UpdatePortalProfileRequest::rules()` |
| `status` | Not in `UpdatePortalProfileRequest::rules()` |
| `password` directly | Must go through `changePassword()` — verifies current password first |

Addresses managed via customer-addresses module — not this module.
