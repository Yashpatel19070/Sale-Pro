<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(): View
    {
        $roles = Role::with('permissions')->orderBy('name')->get();

        return view('roles.index', compact('roles'));
    }

    public function show(Role $role): View
    {
        $role->load('permissions', 'users');

        return view('roles.show', compact('role'));
    }

    public function edit(Role $role): View
    {
        $role->load('permissions');
        $allPermissions = Permission::orderBy('name')->get()
            ->groupBy(fn ($p) => explode('.', $p->name)[0]);

        return view('roles.edit', compact('role', 'allPermissions'));
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $validated = $request->validate([
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->roleService->syncPermissions($role, $validated['permissions'] ?? []);

        return redirect()->route('roles.show', $role)
            ->with('success', "Permissions for \"{$role->name}\" updated.");
    }
}
