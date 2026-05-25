# Order Module — Rules & ASK Protocol

> **READ THIS FIRST.** This file governs every other file in `.claude/plans/order/`.
> Apply these rules to every spec, every implementation, every test.

---

## Section 1 — STOP and ASK triggers (mandatory)

Before writing ANY code, if any of the following is true, **STOP and ask the user ONE question**.
Never guess. Never invent. Never copy from memory.

| # | Trigger | What to ask |
|---|---------|-------------|
| 1 | A column name you are about to use is **not** in [`01-schema.md`](01-schema.md) Tables section | "Column `X` is not in 01-schema.md — which real column should I use, or do you want to add it to the schema?" |
| 2 | A route name you are about to use is **not** in [`03-controllers.md`](03-controllers.md) Routes section | "Route `X` is not in 03-controllers.md — which named route should I use?" |
| 3 | An enum case you are about to use is **not** in the enum table in [`02-services.md`](02-services.md) Enums section | "Enum case `X` is not in 02-services.md — which case should I use?" |
| 4 | A permission key you are about to use is **not** in the Permission Matrix in [`05-update-cancel-delete.md`](05-update-cancel-delete.md) | "Permission `X` is not in the matrix — which permission should I use?" |
| 5 | The spec disagrees with the existing code (e.g. `app/Models/Order.php`) | "Spec says X, code says Y — which is correct?" |
| 6 | A reference pointer (`see: skills/references/foo.md`) points to a file or section that does not exist | "Reference `foo.md#section` does not exist — which pattern should I follow?" |
| 7 | A form field you are about to render is **not** in the column-map table for that view | "Field `X` is not in the column-map for `view.blade.php` — which DB column is its source?" |
| 8 | A test assertion checks a column that is **not** in [`01-schema.md`](01-schema.md) | "Assertion checks column `X` not in schema — typo or new column?" |

> **Why this protocol exists.** Memory observation S3040 (May 24 2026) diagnosed the root cause of all order-module bugs as "code blocks in plans encourage writing from plan rather than schema". Column-name drift between `01-schema.md`, the migration, the model, the service, and the views silently broke `show.blade.php`. The cure is: specs are rules, schema is source of truth, every layer cross-checks before writing.

---

## Section 2 — Spec-writing rules

These rules govern how plan files are written (and how the agent reads them).

| Rule | Detail |
|------|--------|
| **No code blocks for new content** | Plans state rules, signatures, tables, and step-lists — not code. The implementation is the code. Exception: a 1–3 line snippet is allowed only as a **pointer** to an existing reference file. |
| **Reference pointers per section** | Every spec section ends with `**Reference:** skills/references/<file>.md#<section>`. The agent reads the reference file for the pattern, then writes code from the spec + pattern — never invents. |
| **Tables over prose for any list** | Columns, routes, enum cases, permissions, validation rules, test cases — all in tables. Prose only for context. |
| **One source of truth per concept** | The schema lives in `01-schema.md` ONLY. Other files reference it; they do not restate column names. Same for enums, routes, permissions. |
| **Source-of-truth column maps for views and FormRequests** | Every view spec and every FormRequest spec includes a table mapping request key → DB column. If the map is missing the field, do not render it. |
| **Bug-fix callouts at top** | Any known bug or constraint that contradicts an obvious reading of the spec is called out in a `> **NOTE:**` block at the top of the relevant section. |

---

## Section 3 — Frontend rules (Blade + Alpine)

These rules exist because of recurring bugs (memory: `feedback_alpine_inline_xdata.md`).

| Rule | Why |
|------|-----|
| **Never put `@json($x)` inside an `x-data` attribute** | Blade `@json` is evaluated server-side and produces JSON with quotes that break the Alpine parser when the value is large or contains nested objects. Garbles the form. |
| **Pass server data via `window.__<var>` globals in a `<script>` block before `x-data`** | Alpine reads `window.__var` cleanly. Pattern: `<script>window.__orderCustomers = @json($customers);</script>` then `x-data="{ customers: window.__orderCustomers }"`. |
| **`Alpine.data()` + `alpine:init` is forbidden** | Unreliable in headless browsers (test environment). Use inline `x-data="{...}"` with `window.__var`. |
| **Destructive actions use Alpine `@submit.prevent` + `confirm()` in attribute** | No native browser `onclick="return confirm(...)"`. Pattern lives in [`05-update-cancel-delete.md`](05-update-cancel-delete.md) §7B. |
| **Tax fields are read-only in forms** | Tax is computed server-side by AvaTax. Form must display computed value, never accept user input for it. |

---

## Section 4 — Backend rules (per [CLAUDE.md](../../../CLAUDE.md))

These are project-wide rules restated here so order module specs comply.

| Rule | Detail |
|------|--------|
| `declare(strict_types=1);` | First line of every PHP file. |
| `$request->validated()` always | Never `$request->all()`. Never `$request->input()` for validated input. |
| `with()` always | Never lazy load — N+1 is a defect. |
| `DB::transaction()` for every multi-table write | One write = no transaction. Two or more writes = transaction. |
| TOCTOU status guards inside the transaction | Read status → re-check status → write. Never read outside, write inside. |
| Throw `DomainException` for expected business failures | Controller catches and returns `back()->withErrors(['error' => ...])`. |
| Snapshot columns are immutable after creation | `billing_*` and `shipping_*` on `orders` table — never updated except by `OrderService::update()` on pending orders. |
| Every controller action has a Pest feature test | Three perms: authorized actor, unauthorized actor, guest. |
| Every service method has a Pest unit test | One happy path + one failure (TOCTOU / validation / not found). |

---

## Section 5 — Reference file map

| Layer | Reference file | When to read |
|-------|----------------|--------------|
| Routes & Controllers | `skills/references/controller.md` | Before writing any controller method |
| Services & transactions | `skills/references/service.md` | Before writing any service method |
| FormRequests & validation | `skills/references/form-request.md` | Before writing any FormRequest |
| Models, casts, relationships | `skills/references/model.md` | Before writing any model |
| Admin views (Blade + Tailwind) | `skills/references/admin-views.md` | Before writing any view |
| Pest tests | `skills/references/testing.md` | Before writing any test |
| Spatie permissions | `skills/references/permissions-spatie.md` | Before writing a policy or seeder |
| Migrations & schema | `skills/references/database.md` | Before writing a migration |
| Error handling | `skills/references/error-handling.md` | Before throwing or catching exceptions |
| Code style (Pint, PSR-12) | `skills/references/code-style.md` | Before submitting any PHP file |

> Pint runs automatically on save via PostToolUse hook. Do not run Pint manually.

---

## Section 6 — Order module file map

| File | Owns |
|------|------|
| [`00-rules.md`](00-rules.md) | This file — module rules and ASK protocol |
| [`01-schema.md`](01-schema.md) | Tables, columns, indexes, status enums, snapshot rules, order number format. **Source of truth for all column names.** |
| [`02-services.md`](02-services.md) | Enums, Models, FormRequests, OrderService methods (create / recordCashPayment / ship / markDelivered) |
| [`03-controllers.md`](03-controllers.md) | Routes, OrderPermissionSeeder, OrderPolicy, OrderController methods, Views (index / create / show), Navigation, OrderSeeder |
| [`04-tests.md`](04-tests.md) | Pest tests — OrderServiceTest (unit) + OrderControllerTest (feature). Factory state spec. |
| [`05-update-cancel-delete.md`](05-update-cancel-delete.md) | Update, Cancel, Delete operations — routes, permissions, policy methods, service methods, controller methods, view additions, tests |
| `db-reference.md` | Read-only reference of related tables (customers, inventory_serials, etc.). Do not edit. |

---

## Section 7 — Known bugs to fix during implementation

These are tracked in memory observation S3040 and must be fixed when implementing or re-implementing the module.

| # | Bug | Location | Fix |
|---|-----|----------|-----|
| 1 | Column name drift — view references `fees_total`, `shipping_amount` but DB columns are `fees`, `shipping` | `resources/views/orders/show.blade.php` | Use real column names per [`01-schema.md`](01-schema.md). Source-of-truth column map mandatory in view specs. |
| 2 | Tax excluded from grand total display | `resources/views/orders/show.blade.php` | Tax is already in `subtotal` (per Tax Rule in [`02-services.md`](02-services.md) §OrderService::create). Display: `Subtotal (incl. tax)`. Do not add tax separately. |
| 3 | Billing address defaults to "same as shipping" for cash orders | `resources/views/orders/create.blade.php` | Cash orders → billing snapshot NULL. Default `billingType = 'none'`. Per snapshot rule in [`01-schema.md`](01-schema.md). |
| 4 | `core_charges` in `Order::$fillable` but not in schema | `app/Models/Order.php` | Remove `core_charges` from fillable. Not a real column. Add to schema first if needed. |
| 5 | Admin permission seeder grants `orders.delete` to admin | `OrderPermissionSeeder.php` | Only `super_admin` gets delete. Per Permission Matrix in [`05-update-cancel-delete.md`](05-update-cancel-delete.md). |

---

## Section 8 — Test workflow per CLAUDE.md

Order of work for any new feature in this module:

1. **Read** the relevant spec file(s) in this folder.
2. **Read** the referenced pattern file(s) in `skills/references/`.
3. **Verify** the actual schema in `database/migrations/` matches `01-schema.md`. If not, STOP and ask.
4. **Write tests first** (Pest) — see [`04-tests.md`](04-tests.md) for the test list.
5. **Run tests — they must fail (RED).**
6. **Implement** — controller, service, model, view (in that order).
7. **Run tests — they must pass (GREEN).**
8. **Browser-verify any view** — start dev server, open the page, click through the golden path and one edge case.
9. **Update `.claude/plans/STATUS.md`** — Order module row.

---

**Reference:** `CLAUDE.md` (project root) · `skills/laravel-system-design.md` · all files in `skills/references/`.
