# ProductListing Module — Bug Tracker

**Audited:** 2026-05-11
**Auditor:** Claude Code
**Test suite at audit time:** All files present, implementation matches plan with exceptions below.

---

## Summary Table

| Bug | Severity | Status | Title |
|-----|----------|--------|-------|
| BUG-001 | High | Fixed (2026-05-11) | `UpdateProductListingRequest::authorize()` uses wrong route key |
| BUG-002 | Medium | Not a Bug | `is_active` cannot be set to `false` via edit form |
| BUG-003 | Medium | Deferred | `delete()` has no active-orders guard — controller catch is dead code |
| BUG-004 | Medium | Not a Bug | `LogsActivity` trait not in plan + namespace unverified |
| BUG-005 | Low | Not a Bug | `scopePublic()` passes enum instance instead of `->value` |
| BUG-006 | Low | Not a Bug | Extra undocumented guard migration in history |

---

## BUG-001: `UpdateProductListingRequest::authorize()` — wrong route key (null model)

**Severity:** High | **Status:** Fixed (2026-05-11)
**File:** `app/Http/Requests/ProductListing/UpdateProductListingRequest.php`

**Problem:** `authorize()` calls `$this->route('product_listing')` (snake_case) but the route parameter is `{productListing}` (camelCase). `$this->route('product_listing')` returns `null`. Calling `->can('update', null)` passes a null model to the policy — behavior is undefined and authorization logic is bypassed in the FormRequest layer.

The update action still works because `ProductListingController::update()` calls `$this->authorize('update', $productListing)` explicitly (belt-and-suspenders). But the FormRequest `authorize()` is effectively dead code returning a wrong result.

**Draft Fix:**
```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('productListing'));
    //                                              ↑ camelCase — matches {productListing}
}
```

**Resolution:** Changed `$this->route('product_listing')` → `$this->route('productListing')`.

---

## BUG-002: `is_active` cannot be set to `false` via edit form

**Severity:** Medium | **Status:** Not a Bug
**File:** `app/Http/Requests/ProductListing/UpdateProductListingRequest.php` + `resources/views/product_listings/_form.blade.php`

**Problem:** Both `StoreProductListingRequest` and `UpdateProductListingRequest` define `is_active` as `['nullable', 'boolean']`. When an HTML checkbox is unchecked, the browser sends no field — not `false`, not `0`, nothing. Laravel treats an absent `nullable` field as not present in `$request->validated()`. So `$listing->update($data)` never receives `is_active` when the checkbox is unchecked — the column value never changes to `false`.

Result: once a listing is active, it cannot be deactivated through the edit form. The "Active" checkbox is a one-way switch.

**Resolution:** Not a bug — `_form.blade.php` already has a hidden input at line 90 before the checkbox: `<input type="hidden" name="is_active" value="0" />`. Browser always sends `0` or `1`, so the checkbox works correctly in both directions.

**Draft Fix (option A — hidden input approach):**
```blade
{{-- _form.blade.php — add hidden input before the checkbox: --}}
<input type="hidden" name="is_active" value="0">
<input type="checkbox" name="is_active" value="1" {{ old('is_active', $listing?->is_active ?? true) ? 'checked' : '' }}>
```
Keep validation rule as `['nullable', 'boolean']` — the hidden input ensures a value is always sent.

**Draft Fix (option B — required boolean):**
```php
'is_active' => ['required', 'boolean'],
```
And ensure the form always sends a value (same hidden input trick).

Option A is simpler — no request class change needed.

---

## BUG-003: `delete()` missing active-orders guard — `catch (\RuntimeException)` in controller is dead code

**Severity:** Medium | **Status:** Deferred — depends on Orders module
**File:** `app/Services/ProductListingService.php` + `app/Http/Controllers/ProductListingController.php`

**Problem:** The plan specifies "soft delete; block if listing has active orders". The service `delete()` has a comment `"Throws if it has active orders (future guard)"` but throws nothing — it just calls `$listing->delete()`. The controller wraps the call in `try { } catch (\RuntimeException $e)` — this catch block will never execute.

When the Orders module ships, the guard must be added to the service. Until then the catch block is dead code and the plan's block-on-active-orders rule is unimplemented.

**Resolution:** Deferred. Orders module does not exist yet — guard cannot be implemented. Added `TODO(orders)` comment with the exact stub in `ProductListingService::delete()` so future implementor knows exactly where to wire it. Controller `catch (\RuntimeException)` left in place — will activate automatically once the guard throws.

---

## BUG-004: `LogsActivity` trait not in plan + namespace unverified

**Severity:** Medium | **Status:** Not a Bug
**File:** `app/Models/ProductListing.php`

**Problem:** The model uses:
```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
```

Two concerns:
1. `LogsActivity` is not in the plan's model spec — added during implementation without documentation.
2. In `spatie/laravel-activitylog` v4+, the correct trait namespace is `Spatie\Activitylog\Traits\LogsActivity`. The `Models\Concerns` path is the old v3 location.

**Resolution:** Verified via `find vendor/spatie/laravel-activitylog/src -name "LogsActivity*"` — the file exists at `Models/Concerns/LogsActivity.php`. Namespace is correct for the installed version. Plan's `02-model.md` updated to document `LogsActivity` usage.

---

## BUG-005: `scopePublic()` passes enum instance instead of `->value` to `where()`

**Severity:** Low | **Status:** Not a Bug
**File:** `app/Models/ProductListing.php`

**Problem:**
```php
public function scopePublic(Builder $query): Builder
{
    return $query->where('visibility', ListingVisibility::Public)   // enum instance
                 ->where('is_active', true);
}
```

The plan specifies `ListingVisibility::Public->value` (the string `'public'`). Passing the enum instance works in practice because Eloquent's backed enum casting converts it during query building.

**Resolution:** Project always uses Eloquent — never raw `DB::table()`. Eloquent handles enum instances correctly. No change needed.

---

## BUG-006: Extra undocumented guard migration

**Severity:** Low | **Status:** Not a Bug
**File:** `database/migrations/2026_04_13_230827_add_slug_and_visibility_to_product_listings_table.php`

**Problem:** A third migration not in the plan. Re-adds `slug` and `visibility` columns with `hasColumn()` guards. On fresh install it's a no-op.

**Resolution:** Migration already has an inline comment explaining why it exists (original stub ran on dev DB before columns were added to the create migration). No change needed.
