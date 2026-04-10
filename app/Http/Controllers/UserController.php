<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserStatus;
use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\DepartmentService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly DepartmentService $departments,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $filters = $request->only(['search', 'status', 'department_id', 'role']);

        if (auth()->user()->hasRole('manager')) {
            $filters['department_id'] = auth()->user()->department_id;
        }

        $users = $this->users->list($filters);
        $departments = $this->departments->dropdown();
        $roles = Role::orderBy('name')->pluck('name');
        $statuses = UserStatus::cases();

        return view('users.index', compact('users', 'departments', 'roles', 'statuses'));
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        $user = null;
        $departments = $this->departments->dropdown();
        $roles = Role::orderBy('name')->pluck('name');
        $statuses = UserStatus::cases();
        $timezones = \DateTimeZone::listIdentifiers();

        return view('users.create', compact('user', 'departments', 'roles', 'statuses', 'timezones'));
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $user = $this->users->create(
            data: $request->validated(),
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
        $roles = Role::orderBy('name')->pluck('name');
        $statuses = UserStatus::cases();
        $timezones = \DateTimeZone::listIdentifiers();

        return view('users.edit', compact('user', 'departments', 'roles', 'statuses', 'timezones'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $this->users->update(
            user: $user,
            data: $request->validated(),
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

    public function restore(User $trashedUser): RedirectResponse
    {
        $this->authorize('restore', $trashedUser);

        $this->users->restore($trashedUser);

        return redirect()
            ->route('users.show', $trashedUser)
            ->with('success', "User \"{$trashedUser->name}\" restored.");
    }

    public function changeStatus(Request $request, User $user): RedirectResponse
    {
        $this->authorize('changeStatus', $user);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $this->users->changeStatus($user, UserStatus::from($validated['status']));

        return back()->with('success', "Status updated to {$validated['status']}.");
    }

    public function sendPasswordReset(User $user): RedirectResponse
    {
        $this->authorize('resetPassword', $user);

        $this->users->sendPasswordReset($user);

        return back()->with('success', "Password reset email sent to {$user->email}.");
    }
}
