# User Module — Controller

## UserController

File: `app/Http/Controllers/UserController.php`

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\Department;
use App\Models\User;
use App\Services\DepartmentService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService       $users,
        private readonly DepartmentService $departments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = $this->users->list(
            filters: $request->only(['search', 'status', 'department_id', 'role']),
        );

        $departments = $this->departments->dropdown();
        $roles       = Role::orderBy('name')->pluck('name');
        $statuses    = UserStatus::cases();

        return view('users.index', compact('users', 'departments', 'roles', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $departments = $this->departments->dropdown();
        $roles       = Role::orderBy('name')->pluck('name');
        $statuses    = UserStatus::cases();
        $timezones   = \DateTimeZone::listIdentifiers();

        return view('users.create', compact('departments', 'roles', 'statuses', 'timezones'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create(
            data:   $request->validated(),
            avatar: $request->file('avatar'),
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', "User \"{$user->name}\" created.");
    }

    public function show(User $user): View
    {
        $this->authorize('view', $user);

        $user->load(['department:id,name', 'roles:name', 'createdBy:id,name', 'updatedBy:id,name']);

        return view('users.show', compact('user'));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $departments = $this->departments->dropdown();
        $roles       = Role::orderBy('name')->pluck('name');
        $statuses    = UserStatus::cases();
        $timezones   = \DateTimeZone::listIdentifiers();

        return view('users.edit', compact('user', 'departments', 'roles', 'statuses', 'timezones'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update(
            user:   $user,
            data:   $request->validated(),
            avatar: $request->file('avatar'),
        );

        return redirect()
            ->route('users.show', $user)
            ->with('success', 'User updated.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        try {
            $this->users->delete($user);
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('users.index')
            ->with('success', "User \"{$user->name}\" deleted.");
    }

    public function restore(int $id): RedirectResponse
    {
        $this->authorize('restore', User::class);

        $user = $this->users->restore($id);

        return redirect()
            ->route('users.show', $user)
            ->with('success', "User \"{$user->name}\" restored.");
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeStatus', $user);

        $validated = $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::enum(UserStatus::class)],
        ]);

        $this->users->changeStatus($user, UserStatus::from($validated['status']));

        return back()->with('success', "User status updated to {$validated['status']}.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $this->users->sendPasswordReset($user);

        return back()->with('success', "Password reset email sent to {$user->email}.");
    }
}
```

## Routes

In `routes/web.php`, inside the `auth` + `verified` middleware group:

```php
use App\Http\Controllers\UserController;

Route::resource('users', UserController::class);
Route::post('users/{user}/change-status',       [UserController::class, 'changeStatus'])
    ->name('users.change-status');
Route::post('users/{user}/send-password-reset', [UserController::class, 'sendPasswordReset'])
    ->name('users.send-password-reset');
Route::post('users/{id}/restore',               [UserController::class, 'restore'])
    ->name('users.restore');
```

## Named Routes Summary

| Name                          | Method | URI                                          |
|-------------------------------|--------|----------------------------------------------|
| users.index                   | GET    | /users                                       |
| users.create                  | GET    | /users/create                                |
| users.store                   | POST   | /users                                       |
| users.show                    | GET    | /users/{user}                                |
| users.edit                    | GET    | /users/{user}/edit                           |
| users.update                  | PUT    | /users/{user}                                |
| users.destroy                 | DELETE | /users/{user}                                |
| users.change-status           | POST   | /users/{user}/change-status                  |
| users.send-password-reset     | POST   | /users/{user}/send-password-reset            |
| users.restore                 | POST   | /users/{id}/restore                          |

## Profile Controller Changes

The existing `ProfileController` (Breeze) handles self-service profile edits.
Update `update()` to delegate to `UserService::updateProfile()`:

```php
public function update(ProfileUpdateRequest $request): RedirectResponse
{
    $this->userService->updateProfile(
        user:   $request->user(),
        data:   $request->validated(),
        avatar: $request->file('avatar'),
    );

    return redirect()->route('profile.edit')->with('success', 'Profile updated.');
}
```
