# Permissions Module — Bootstrap / App Registration

## `bootstrap/app.php`

Register custom middleware aliases so routes can reference them by short name.
Also register Spatie's middleware (not auto-registered in Laravel 12).

```php
->withMiddleware(function (Middleware $middleware) {

    $middleware->alias([
        // Spatie — must register manually in Laravel 12
        'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,

        // Custom
        'active'       => \App\Http\Middleware\EnsureUserIsActive::class,
        'load_perms'   => \App\Http\Middleware\LoadUserPermissions::class,
        'admin'        => \App\Http\Middleware\EnsureIsAdmin::class,
        'superadmin'   => \App\Http\Middleware\EnsureSuperAdmin::class,
    ]);

    $middleware->redirectGuestsTo('/login');
    $middleware->redirectUsersTo('/dashboard');
})
```

## Notes

- `load_perms` runs immediately after `auth` in every protected route group.
- `active` blocks suspended/inactive users before they reach any controller.
- `admin` gates the entire roles management section.
- Spatie's `permission:xxx` middleware is used per-route for fine-grained control.
