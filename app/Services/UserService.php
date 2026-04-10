<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;

class UserService
{
    /**
     * @param  array{search?: string, status?: string, department_id?: int, role?: string}  $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $perPage = min(max(1, $perPage), 100);

        return User::with(['department:id,name', 'roles:id,name'])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where(function ($q) use ($filters): void {
                    $q->where('name', 'like', "%{$filters['search']}%")
                        ->orWhere('email', 'like', "%{$filters['search']}%")
                        ->orWhere('employee_id', 'like', "%{$filters['search']}%");
                })
            )
            ->when(
                isset($filters['status']) && $filters['status'] !== '',
                fn ($q) => $q->where('status', $filters['status'])
            )
            ->when(
                isset($filters['department_id']) && $filters['department_id'] !== '',
                fn ($q) => $q->where('department_id', $filters['department_id'])
            )
            ->when(
                isset($filters['role']) && $filters['role'] !== '',
                fn ($q) => $q->role($filters['role'])
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param array{
     *     name: string, email: string, password: string,
     *     phone?: string, job_title?: string, employee_id?: string,
     *     department_id?: int, status?: string, hired_at?: string,
     *     timezone?: string, role: string
     * } $data
     */
    public function create(array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($data, $avatar): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'phone' => $data['phone'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'employee_id' => $data['employee_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'status' => $data['status'] ?? UserStatus::Active->value,
                'hired_at' => $data['hired_at'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

            if ($avatar) {
                $user->update(['avatar' => $this->storeAvatar($avatar, $user->id)]);
            }

            $user->assignRole($data['role']);

            return $user->load(['department', 'roles']);
        });
    }

    /**
     * @param array{
     *     name?: string, email?: string, phone?: string, job_title?: string,
     *     employee_id?: string|null, department_id?: int|null, status?: string,
     *     hired_at?: string|null, timezone?: string, role?: string
     * } $data
     */
    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar): User {
            $updates = Arr::only($data, [
                'name', 'email', 'phone', 'job_title',
                'employee_id', 'department_id', 'status', 'hired_at', 'timezone',
            ]);

            if ($avatar) {
                $this->deleteAvatar($user);
                $updates['avatar'] = $this->storeAvatar($avatar, $user->id);
            }

            $user->update($updates);

            if (isset($data['role']) && Auth::user()?->hasRole('admin')) {
                $user->syncRoles([$data['role']]);
            }

            return $user->fresh(['department', 'roles']);
        });
    }

    /**
     * @param  array{name: string, email: string, phone?: string, timezone?: string}  $data
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar): User {
            $updates = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? $user->phone,
                'timezone' => $data['timezone'] ?? $user->timezone,
            ];

            if ($data['email'] !== $user->email) {
                $updates['email_verified_at'] = null;
            }

            if ($avatar) {
                $this->deleteAvatar($user);
                $updates['avatar'] = $this->storeAvatar($avatar, $user->id);
            }

            $user->update($updates);

            return $user->fresh();
        });
    }

    /**
     * @throws \RuntimeException
     */
    public function delete(User $user): void
    {
        DB::transaction(function () use ($user): void {
            if (Department::where('manager_id', $user->id)->exists()) {
                throw new \RuntimeException(
                    "User \"{$user->name}\" is a department manager. ".
                    'Reassign the manager before deleting.'
                );
            }

            $user->delete();
        });
    }

    public function restore(User $user): User
    {
        return DB::transaction(function () use ($user): User {
            $user->restore();

            return $user;
        });
    }

    public function changeStatus(User $user, UserStatus $status): User
    {
        $user->update(['status' => $status]);

        return $user->fresh();
    }

    public function sendPasswordReset(User $user): string
    {
        return Password::sendResetLink(['email' => $user->email]);
    }

    private function storeAvatar(UploadedFile $file, int $userId): string
    {
        $path = $file->store("avatars/{$userId}", 'public');

        if ($path === false) {
            throw new \RuntimeException('Failed to store avatar. Check storage permissions.');
        }

        return $path;
    }

    private function deleteAvatar(User $user): void
    {
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
    }
}
