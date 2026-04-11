# Dev Toolchain Reference

All tools are `--dev` dependencies — never loaded in production.
Install once at project start before any feature work.

---

## Laravel Telescope — Request / Query / Job Inspector

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

Access at: `http://localhost/telescope`

Auto-disabled when `APP_ENV != local` — confirm gate in `config/telescope.php`:
```php
'gate' => function (Request $request) {
    return app()->environment('local');
},
```

**Use it to:**
- Catch N+1 queries — if query count > (1 + number of relations) → add `with()`
- Inspect queued jobs and their payloads
- Debug mail and notifications
- View exceptions with full stack traces
- Monitor slow queries

**Maintenance:**
```bash
# Clear old Telescope data periodically
php artisan telescope:prune --hours=48
```

---

## Laravel Debugbar — Inline Page Profiler

```bash
composer require barryvdh/laravel-debugbar --dev
```

Auto-disabled when `APP_DEBUG=false` — no config needed.

Shows per-page:
- Query count and query time
- Memory usage
- Views rendered
- Route matched

Use it to spot slow pages before they reach production.

---

## PHPStan via Larastan — Static Analysis (Level 8)

```bash
composer require nunomaduro/larastan --dev
```

Config file `phpstan.neon` in project root:
```neon
includes:
    - vendor/nunomaduro/larastan/extension.neon
parameters:
    paths:
        - app
    level: 8
```

Run:
```bash
./vendor/bin/phpstan analyse
```

**Must pass with zero errors before any PR merge.**

PHPStan catches:
- Undefined variables
- Wrong return types
- Missing null checks
- Wrong method signatures
- Type mismatches

---

## Laravel Pint — Code Style Auto-Fixer

Already installed with Laravel — just run it:
```bash
./vendor/bin/pint
```

Enforces PSR-12 automatically. Run before every commit.

Config file `pint.json` in project root — Laravel default preset:
```json
{
    "preset": "laravel"
}
```

---

## Laravel IDE Helper — Autocomplete for Models & Facades

```bash
composer require barryvdh/laravel-ide-helper --dev

php artisan ide-helper:generate          # Facades → _ide_helper.php
php artisan ide-helper:models --nowrite  # Model PHPDocs — inspect output first
php artisan ide-helper:meta              # PhpStorm/Cursor meta file
```

Re-run `ide-helper:models` after every new migration.

Add generated files to `.gitignore` — they are local dev artifacts:
```
_ide_helper.php
_ide_helper_models.php
.phpstorm.meta.php
```

---

## Rules

- Never remove `--dev` flag — these must not load in production
- `APP_ENV=local` in `.env` for Telescope to activate
- `APP_DEBUG=false` in production — disables Debugbar automatically
- CI pipeline runs PHPStan + Pint + tests — all must pass before merge
- No `dd()`, `var_dump()`, or `print_r()` in committed code — use Telescope instead

---

## Quick Reference

```bash
# Static analysis — zero errors required
./vendor/bin/phpstan analyse

# Code style — run before every commit
./vendor/bin/pint

# IDE autocomplete — run after every migration
php artisan ide-helper:models --nowrite

# Clear Telescope data
php artisan telescope:prune --hours=48

# Access Telescope
http://localhost/telescope
```
