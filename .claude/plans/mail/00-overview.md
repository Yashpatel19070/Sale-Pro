# Mail System Plan

## Current Setup

- Provider: Mailtrap SMTP (credentials already in `.env`)
- Mailer: `smtp` (built into Laravel — no extra packages needed)
- Port: 587 (requires TLS)
- Audit log: Spatie activitylog (already installed)

---

## What Emails Go Out

| Mail | Trigger | Status |
|------|---------|--------|
| Email verification | Register / Resend | Now |
| Password reset | Forgot password | Now |
| Welcome | After email verified | Soon |
| Account deactivated | Admin marks inactive | Soon |
| Order confirmed | Customer places order | Future |
| Order shipped | Admin marks shipped | Future |
| Invoice ready | Admin creates invoice | Future |
| Password changed | After reset completes | Future |

---

## Required Changes

### 1. Fix `.env` — wrong encryption value

Current:
```
MAIL_ENCRYPTION=null
```

Must be:
```
MAIL_ENCRYPTION=tls
```

Port 587 requires TLS. Without this, mail connection fails.

### 2. Fix resend status string mismatch

`verify-email.blade.php` checks:
```blade
session('status') === 'verification-link-sent'
```

`EmailVerificationController::resend()` sends:
```php
back()->with('status', 'Verification link sent.')   ← wrong string
```

Fix controller to:
```php
return back()->with('status', 'verification-link-sent');
```

---

## Mail Templates — Option B (Blade Markdown)

Blade markdown mails with a shared branded layout. One layout built once,
new mails drop in as features grow. No external dependencies.

### Directory Structure

```
resources/views/mail/
├── layout.blade.php                  ← shared header/footer/branding
└── customer/
    ├── verify-email.blade.php        ← Now
    ├── reset-password.blade.php      ← Now
    ├── welcome.blade.php             ← Soon
    ├── account-deactivated.blade.php ← Soon
    ├── order-confirmed.blade.php     ← Future
    ├── order-shipped.blade.php       ← Future
    └── invoice-ready.blade.php       ← Future
```

### Mailable Classes

```
app/Mail/Customer/
├── VerifyEmailMail.php               ← Now
├── ResetPasswordMail.php             ← Now
├── WelcomeMail.php                   ← Soon
├── AccountDeactivatedMail.php        ← Soon
├── OrderConfirmedMail.php            ← Future
├── OrderShippedMail.php              ← Future
└── InvoiceReadyMail.php              ← Future
```

### How It Works

Each `Mailable` class points to its Blade template.
All templates extend `mail.layout` for consistent branding (logo, footer, colors).

```php
// app/Mail/Customer/VerifyEmailMail.php
class VerifyEmailMail extends Mailable
{
    public function __construct(
        public readonly Customer $customer,
        public readonly string $verificationUrl,
    ) {}

    public function content(): Content
    {
        return new Content(markdown: 'mail.customer.verify-email');
    }
}
```

### Override Laravel's Default Notifications

`Customer` model overrides built-in notification methods to use custom Mailables:

```php
// app/Models/Customer.php

public function sendEmailVerificationNotification(): void
{
    $url = URL::temporarySignedRoute(
        'portal.verification.verify',
        now()->addMinutes(60),
        ['id' => $this->id, 'hash' => sha1($this->email)],
    );

    $this->notify(new \App\Notifications\Customer\VerifyEmailNotification($url));
}

public function sendPasswordResetNotification(string $token): void
{
    $url = route('portal.password.reset', [
        'token' => $token,
        'email' => $this->email,
    ]);

    $this->notify(new \App\Notifications\Customer\ResetPasswordNotification($url));
}
```

### Notification Classes

```
app/Notifications/Customer/
├── VerifyEmailNotification.php
└── ResetPasswordNotification.php
```

Each notification uses the matching `Mailable` to send the email.

---

## Audit Log — Mail Events

All mail events logged to Spatie `activity_log` table under log name `mail`.

| Event | Where logged | Message |
|-------|-------------|---------|
| Verification email sent (register) | `RegisteredUserController::store()` | `verification-sent` |
| Verification email resent | `EmailVerificationController::resend()` | `verification-resent` |
| Email verified | `EmailVerificationController::verify()` | `email-verified` |
| Password reset requested | `PasswordResetLinkController::store()` | `password-reset-requested` |
| Password reset completed | `NewPasswordController::store()` | `password-reset-completed` |

Direct logging in controllers via `activity()` helper — no new listeners or events.

```php
activity('mail')
    ->causedBy($customer)
    ->withProperties(['ip' => request()->ip()])
    ->log('verification-sent');
```

---

## File Map

| File | Change | Phase |
|------|--------|-------|
| `.env` | `MAIL_ENCRYPTION=tls` | Now |
| `Portal/Auth/EmailVerificationController.php` | Fix status string + log 3 events | Now |
| `Portal/Auth/RegisteredUserController.php` | Log `verification-sent` | Now |
| `Portal/Auth/PasswordResetLinkController.php` | Log `password-reset-requested` | Now |
| `Portal/Auth/NewPasswordController.php` | Log `password-reset-completed` | Now |
| `app/Models/Customer.php` | Override 2 notification methods | Now |
| `resources/views/mail/layout.blade.php` | Shared branded layout | Now |
| `resources/views/mail/customer/verify-email.blade.php` | Verification template | Now |
| `resources/views/mail/customer/reset-password.blade.php` | Reset password template | Now |
| `app/Mail/Customer/VerifyEmailMail.php` | Mailable class | Now |
| `app/Mail/Customer/ResetPasswordMail.php` | Mailable class | Now |
| `app/Notifications/Customer/VerifyEmailNotification.php` | Notification class | Now |
| `app/Notifications/Customer/ResetPasswordNotification.php` | Notification class | Now |
| `resources/views/mail/customer/welcome.blade.php` | Welcome template | Soon |
| `app/Mail/Customer/WelcomeMail.php` | Mailable class | Soon |
| `resources/views/mail/customer/account-deactivated.blade.php` | Deactivated template | Soon |
| `app/Mail/Customer/AccountDeactivatedMail.php` | Mailable class | Soon |
| `app/Http/Controllers/MailTestController.php` | Admin mail tester controller | Done |
| `resources/views/mail-test/index.blade.php` | Form + send log view | Done |
| `routes/web.php` | Add 2 mail-test routes under admin group | Done |
| `resources/views/layouts/navigation.blade.php` | Nav link — admin role only | Done |

---

## Implementation Checklist

### Phase 1 — Mail Working ✅ Done
- [x] Update `.env`: `MAIL_ENCRYPTION=tls`
- [x] Create `resources/views/mail/layout.blade.php`
- [x] Create `resources/views/mail/customer/verify-email.blade.php`
- [x] Create `resources/views/mail/customer/reset-password.blade.php`
- [x] Create `app/Mail/Customer/VerifyEmailMail.php`
- [x] Create `app/Mail/Customer/ResetPasswordMail.php`
- [x] Create `app/Notifications/Customer/VerifyEmailNotification.php`
- [x] Create `app/Notifications/Customer/ResetPasswordNotification.php`
- [x] Override `sendEmailVerificationNotification()` on `Customer` model
- [x] Override `sendPasswordResetNotification()` on `Customer` model
- [x] Fix `EmailVerificationController::resend()` status string

### Phase 2 — Audit Logging ✅ Done
- [x] `RegisteredUserController::store()` — log `verification-sent`
- [x] `EmailVerificationController::resend()` — log `verification-resent`
- [x] `EmailVerificationController::verify()` — log `email-verified`
- [x] `PasswordResetLinkController::store()` — log `password-reset-requested`
- [x] `NewPasswordController::store()` — log `password-reset-completed`

### Phase 2b — Admin Mail Tester ✅ Done
- [x] `MailTestController` — `index()` show form + last 20 log entries, `send()` send + log
- [x] `resources/views/mail-test/index.blade.php` — form + log table
- [x] Add routes to `web.php` admin group
- [x] Nav link in Admin dropdown — admin role only (desktop + mobile)

### Phase 3 — Welcome Mail ✅ Done
- [x] `app/Mail/Customer/WelcomeMail.php` — Mailable, `to` set in Envelope
- [x] `resources/views/mail/customer/welcome.blade.php` — Blade markdown template
- [x] `app/Listeners/SendWelcomeMail.php` — handles `Verified` event, guards against non-Customer
- [x] `AppServiceProvider` — `Event::listen(Verified::class, [SendWelcomeMail::class, 'handle'])`

### Phase 4 — Account Deactivated (Later)
- [ ] Create `AccountDeactivatedMail` + template — send when admin marks customer Inactive/Blocked

### Smoke Tests
- [ ] Register → verification email in Mailtrap (branded template) + audit log entry
- [ ] Resend → new email + green banner on page + audit log entry
- [ ] Click link → dashboard + `email-verified` audit log entry
- [ ] Forgot password → reset email in Mailtrap (branded template) + audit log entry
- [ ] Reset password → login works + audit log entry

---

## Admin Mail Tester (Debug Tool)

Internal admin-only page to send a test email and view the send log.
No customer-facing. No queue. Admin role only.

### Permission Matrix

| Role | Can access |
|------|-----------|
| admin | Yes |
| manager | No |
| sales | No |

Nav: shown inside the "Admin" dropdown, guarded by `hasRole('admin')`.
Route: sits inside the existing `['auth', 'load_perms', 'verified', 'active']` middleware group — no extra gate needed since the nav guard and auth middleware already protect it.

### Route

```
GET  /admin/mail-test        → show form + log
POST /admin/mail-test        → send test mail
```

Named: `mail-test.index`, `mail-test.send`

### Form Fields

| Field | Type | Required |
|-------|------|---------|
| To email | text input | yes |
| Subject | text input | yes |
| Message body | textarea | yes |

### Log Table

Shows last 20 test sends from `activity_log` where `log_name = 'mail-test'`:

| Column | Value |
|--------|-------|
| Sent at | `created_at` |
| To | `properties.to` |
| Subject | `properties.subject` |
| Sent by | `causer.name` |
| Status | success / failed |
| IP | `properties.ip` |

### Files

| File | Purpose |
|------|---------|
| `app/Http/Controllers/MailTestController.php` | show form+log, handle send |
| `resources/views/mail-test/index.blade.php` | form + log table |
| `resources/views/layouts/navigation.blade.php` | nav link (admin role only) |

No Mailable class — use `Mail::raw()` for simplicity. No service needed.

```php
// Controller — send action
Mail::raw($request->message, function ($m) use ($request) {
    $m->to($request->to)->subject($request->subject);
});

activity('mail-test')
    ->causedBy($request->user())
    ->withProperties([
        'to'      => $request->to,
        'subject' => $request->subject,
    ])
    ->log('test-sent');
```

---

## No Packages to Install

`symfony/mailer` bundled with Laravel. SMTP works out of the box.
Spatie activitylog already installed and configured.
