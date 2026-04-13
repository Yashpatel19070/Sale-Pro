# Audit Log Module — Auth Events

## Listener
`app/Listeners/LogAuthActivity.php`

```php
<?php

declare(strict_types=1);

namespace App\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

class LogAuthActivity
{
    public function handleLogin(Login $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->log('login');
    }

    public function handleLogout(Logout $event): void
    {
        activity('auth')
            ->causedBy($event->user)
            ->withProperties(['ip' => request()->ip()])
            ->log('logout');
    }

    public function handleFailed(Failed $event): void
    {
        // causer is unknown on failed — log email attempt + IP only
        activity('auth')
            ->withProperties([
                'ip'    => request()->ip(),
                'email' => $event->credentials['email'] ?? null,
            ])
            ->log('login-failed');
    }
}
```

---

## Register in AppServiceProvider

`app/Providers/AppServiceProvider.php`

```php
// Add to imports:
use App\Listeners\LogAuthActivity;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;

// Add inside boot():
Event::listen(Login::class,   [LogAuthActivity::class, 'handleLogin']);
Event::listen(Logout::class,  [LogAuthActivity::class, 'handleLogout']);
Event::listen(Failed::class,  [LogAuthActivity::class, 'handleFailed']);
```

---

## What a login log entry looks like

```
activity_log row:
  log_name:     'auth'
  description:  'login'
  event:        null           ← auth events have no Eloquent event
  subject_type: null           ← no subject model
  subject_id:   null
  causer_type:  'App\Models\User'
  causer_id:    2
  properties:   { "ip": "127.0.0.1" }
```

```
Failed login row:
  log_name:     'auth'
  description:  'login-failed'
  causer_type:  null           ← unknown user
  causer_id:    null
  properties:   { "ip": "127.0.0.1", "email": "attacker@example.com" }
```

---

## Notes

- Both admin side (`/admin/login`) and portal side (`/login`) fire the same Laravel auth events — both get logged automatically
- `login-failed` has no causer (user not found or wrong password) — properties hold the attempted email
- `log_name = 'auth'` separates auth events from model events in queries

---

## Checklist

- [ ] `LogAuthActivity` listener created
- [ ] `handleLogin`, `handleLogout`, `handleFailed` methods implemented
- [ ] `Event::listen` registered for all 3 events in `AppServiceProvider::boot()`
- [ ] Auth events use `log_name = 'auth'`
- [ ] Failed login records attempted email in properties (not as causer)
