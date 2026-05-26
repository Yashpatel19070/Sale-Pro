# AvaTax — Phase 1: Setup & Ping

## Files to create/edit

| File | Action |
|------|--------|
| `config/avatax.php` | Create — reads all AVATAX_* env vars |
| `app/Services/AvaTaxService.php` | Create — SDK wrapper |
| `app/Console/Commands/AvaTaxPing.php` | Create — Artisan ping command |
| `app/Providers/AppServiceProvider.php` | Edit — bind AvaTaxService as singleton |
| `.env.example` | Edit — add AVATAX_* keys with empty values |
| `tests/Unit/AvaTaxServiceTest.php` | Create — unit tests |
| `tests/Feature/AvaTaxPingCommandTest.php` | Create — artisan command tests |

---

## `config/avatax.php`

```php
return [
    'enabled'      => env('AVATAX_ENABLED', false),
    'environment'  => env('AVATAX_ENVIRONMENT', 'sandbox'),
    'account'      => env('AVATAX_ACCOUNT_NUMBER'),
    'license_key'  => env('AVATAX_LICENSE_KEY'),   // NEVER log or output this value
    'company_code' => env('AVATAX_COMPANY_CODE'),
    'company_id'   => env('AVALARA_COMPANY_ID'),
    'ship_from' => [
        'street'  => env('AVATAX_SHIP_FROM_STREET'),
        'city'    => env('AVATAX_SHIP_FROM_CITY'),
        'state'   => env('AVATAX_SHIP_FROM_STATE'),
        'zip'     => env('AVATAX_SHIP_FROM_ZIP'),
        'country' => env('AVATAX_SHIP_FROM_COUNTRY', 'US'),
    ],
];
```

---

## `app/Services/AvaTaxService.php`

### Constructor
```php
public function __construct(private readonly array $config) {}
```
Receives `config('avatax')` — injected via AppServiceProvider singleton binding.

### `isEnabled(): bool`
```php
return (bool) $this->config['enabled'];
```

### `ping(): array`
Wraps the SDK call in try/catch. Returns a plain array — **never includes `license_key`**.

```php
public function ping(): array
{
    try {
        $client   = $this->makeClient();
        $response = $client->ping();

        return [
            'success'      => true,
            'environment'  => $this->config['environment'],
            'account'      => $this->config['account'],
            'company_code' => $this->config['company_code'],
            'version'      => $response->version ?? 'unknown',
            'message'      => 'AvaTax connection successful.',
        ];
    } catch (\Throwable $e) {
        return [
            'success'      => false,
            'environment'  => $this->config['environment'],
            'account'      => $this->config['account'],
            'company_code' => $this->config['company_code'],
            'version'      => null,
            'message'      => $e->getMessage(),
        ];
    }
}
```

> **Security rule:** `license_key` is NEVER returned, logged, or output anywhere.
> Only `account` and `company_code` are safe to display (non-secret identifiers).

### `makeClient(): \Avalara\AvaTaxClient` *(private)*
```php
private function makeClient(): \Avalara\AvaTaxClient
{
    $env = $this->config['environment'] === 'production' ? 'production' : 'sandbox';

    return (new \Avalara\AvaTaxClient('sale-pro', '1.0', gethostname(), $env))
        ->withSecurity($this->config['account'], $this->config['license_key']);
}
```

---

## `app/Console/Commands/AvaTaxPing.php`

Signature: `avatax:ping`
Description: `Test the AvaTax API connection and display credential info.`

### Flow
1. Resolve `AvaTaxService` from the container
2. Call `$service->ping()`
3. Always print the credential header (environment, account, company)
4. Print success line or failure line
5. Return `Command::SUCCESS` (0) or `Command::FAILURE` (1)

### Output — success
```
AvaTax Ping
───────────────────────────────
Environment : sandbox
Account     : 2006805610
Company     : NPCTEST
───────────────────────────────
✓  Connection successful.
```

### Output — failure
```
AvaTax Ping
───────────────────────────────
Environment : sandbox
Account     : 2006805610
Company     : NPCTEST
───────────────────────────────
✗  Connection failed: <exception message>
```

> License key is **never printed** — only account and company code.

---

## `AppServiceProvider` binding

```php
use App\Services\AvaTaxService;

$this->app->singleton(AvaTaxService::class, function () {
    return new AvaTaxService(config('avatax'));
});
```

---

## `.env.example` additions

```
# Avalara AvaTax
AVATAX_ENABLED=false
AVATAX_ENVIRONMENT=sandbox
AVATAX_ACCOUNT_NUMBER=
AVATAX_LICENSE_KEY=
AVATAX_COMPANY_CODE=
AVALARA_COMPANY_ID=
AVATAX_SHIP_FROM_STREET=
AVATAX_SHIP_FROM_CITY=
AVATAX_SHIP_FROM_STATE=
AVATAX_SHIP_FROM_ZIP=
AVATAX_SHIP_FROM_COUNTRY=US
```

---

## Unit Tests — `tests/Unit/AvaTaxServiceTest.php`

### `it_returns_true_when_enabled_config_is_true`
- Build `new AvaTaxService(['enabled' => true, ...minimal config...])`
- `isEnabled()` returns `true`

### `it_returns_false_when_enabled_config_is_false`
- Build `new AvaTaxService(['enabled' => false, ...minimal config...])`
- `isEnabled()` returns `false`

### `it_ping_returns_success_on_valid_sandbox_credentials` *(needs network)*
- Build service using `config('avatax')` (reads from `.env` / `.env.testing`)
- Call `ping()`
- Assert `['success'] === true`
- Assert `['environment'] === 'sandbox'`
- Assert `['account']` is not empty
- Assert array does **not** have key `'license_key'`
- Skip with `test()->skip(...)` if `AVATAX_ACCOUNT_NUMBER` is empty in env

### `it_ping_returns_failure_on_bad_credentials`
- Build service with wrong account/license: `['account' => 'bad', 'license_key' => 'bad', ...]`
- Call `ping()`
- Assert `['success'] === false`
- Assert `['message']` is not empty string
- Assert array does **not** have key `'license_key'`

---

## Feature Tests — `tests/Feature/AvaTaxPingCommandTest.php`

Uses `$this->mock(AvaTaxService::class, ...)` — no real API call.

### `it_outputs_credential_info_and_success_message`
- Mock returns: `['success' => true, 'environment' => 'sandbox', 'account' => '1234', 'company_code' => 'TEST', 'message' => 'AvaTax connection successful.']`
- `$this->artisan('avatax:ping')`
  - `->expectsOutputToContain('sandbox')`
  - `->expectsOutputToContain('1234')`
  - `->expectsOutputToContain('TEST')`
  - `->expectsOutputToContain('Connection successful')`
  - `->assertExitCode(0)`

### `it_outputs_failure_message_and_exits_1`
- Mock returns: `['success' => false, 'environment' => 'sandbox', 'account' => '1234', 'company_code' => 'TEST', 'message' => 'Unauthorized']`
- `$this->artisan('avatax:ping')`
  - `->expectsOutputToContain('Connection failed')`
  - `->expectsOutputToContain('Unauthorized')`
  - `->assertExitCode(1)`

### `it_does_not_output_license_key`
- Mock returns success array (no license_key key)
- `$this->artisan('avatax:ping')`
  - Assert output does NOT contain the actual license key value from config
  - `->assertExitCode(0)`
