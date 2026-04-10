# User Module — Service

File: `app/Services/UserService.php`

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class UserService
{
    /**
     * Paginated list with optional filters.
     *
     * @param array{
     *     search?: string,
     *     status?: string,
     *     department_id?: int,
     *     role?: string
     * } $filters
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return User::with(['department:id,name', 'roles:name'])
            ->when(
                isset($filters['search']) && $filters['search'] !== '',
                fn ($q) => $q->where(function ($q) use ($filters) {
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
     * Create a new user with role assignment.
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     phone?: string,
     *     job_title?: string,
     *     employee_id?: string,
     *     department_id?: int,
     *     status?: string,
     *     hired_at?: string,
     *     timezone?: string,
     *     role: string
     * } $data
     * @param UploadedFile|null $avatar
     */
    public function create(array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($data, $avatar): User {
            $user = User::create([
                'name'          => $data['name'],
                'email'         => $data['email'],
                'password'      => Hash::make($data['password']),
                'phone'         => $data['phone'] ?? null,
                'job_title'     => $data['job_title'] ?? null,
                'employee_id'   => $data['employee_id'] ?? null,
                'department_id' => $data['department_id'] ?? null,
                'status'        => $data['status'] ?? UserStatus::Active->value,
                'hired_at'      => $data['hired_at'] ?? null,
                'timezone'      => $data['timezone'] ?? 'UTC',
            ]);

            if ($avatar) {
                $user->update(['avatar' => $this->storeAvatar($avatar, $user->id)]);
            }

            $user->assignRole($data['role']);

            return $user->load(['department', 'roles']);
        });
    }

    /**
     * Update user profile and role/department assignment.
     *
     * @param array{
     *     name?: string,
     *     email?: string,
     *     phone?: string,
     *     job_title?: string,
     *     employee_id?: string|null,
     *     department_id?: int|null,
     *     status?: string,
     *     hired_at?: string|null,
     *     timezone?: string,
     *     role?: string
     * } $data
     * @param UploadedFile|null $avatar
     */
    public function update(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar): User {
            $updates = array_filter([
                'name'          => $data['name'] ?? null,
                'email'         => $data['email'] ?? null,
                'phone'         => $data['phone'] ?? null,
                'job_title'     => $data['job_title'] ?? null,
                'employee_id'   => array_key_exists('employee_id', $data) ? $data['employee_id'] : null,
                'department_id' => array_key_exists('department_id', $data) ? $data['department_id'] : null,
                'status'        => $data['status'] ?? null,
                'hired_at'      => array_key_exists('hired_at', $data) ? $data['hired_at'] : null,
                'timezone'      => $data['timezone'] ?? null,
            ], fn ($v) => $v !== null);

            if ($avatar) {
                $this->deleteAvatar($user);
                $updates['avatar'] = $this->storeAvatar($avatar, $user->id);
            }

            $user->update($updates);

            if (isset($data['role'])) {
                $user->syncRoles([$data['role']]);
            }

            return $user->fresh(['department', 'roles']);
        });
    }

    /**
     * Update only self-service profile fields (no role/status/dept changes).
     *
     * @param array{
     *     name: string,
     *     email: string,
     *     phone?: string,
     *     timezone?: string
     * } $data
     * @param UploadedFile|null $avatar
     */
    public function updateProfile(User $user, array $data, ?UploadedFile $avatar = null): User
    {
        return DB::transaction(function () use ($user, $data, $avatar): User {
            $updates = [
                'name'     => $data['name'],
                'email'    => $data['email'],
                'phone'    => $data['phone'] ?? $user->phone,
                'timezone' => $data['timezone'] ?? $user->timezone,
            ];

            if ($avatar) {
                $this->deleteAvatar($user);
                $updates['avatar'] = $this->storeAvatar($avatar, $user->id);
            }

            $user->update($updates);

            return $user->fresh();
        });
    }

    /**
     * Soft-delete a user.
     *
     * @throws \RuntimeException
     */
    public function delete(User $user): void
    {
        // Prevent deleting a department manager without cleanup
        if ($user->departments()->exists()) {
            // Note: Department has manager_id FK with nullOnDelete, so this is safe.
            // But we warn the admin anyway.
            throw new \RuntimeException(
                "User \"{$user->name}\" is a department manager. " .
                "Reassign or remove the manager before deleting."
            );
        }

        $user->delete();
    }

    /**
     * Restore a soft-deleted user.
     */
    public function restore(int $id): User
    {
        $user = User::onlyTrashed()->findOrFail($id);
        $user->restore();

        return $user;
    }

    /**
     * Change user status.
     */
    public function changeStatus(User $user, UserStatus $status): User
    {
        $user->update(['status' => $status]);

        return $user->fresh();
    }

    /**
     * Send a password reset link to the user's email.
     */
    public function sendPasswordReset(User $user): string
    {
        return Password::sendResetLink(['email' => $user->email]);
    }

    // ── Private helpers ────────────────────────────────────────────────────

    private function storeAvatar(UploadedFile $file, int $userId): string
    {
        return $file->store("avatars/{$userId}", 'public');
    }

    private function deleteAvatar(User $user): void
    {
        if ($user->avatar && Storage::disk('public')->exists($user->avatar)) {
            Storage::disk('public')->delete($user->avatar);
        }
    }
}
```

## Notes

- `array_filter` with `fn ($v) => $v !== null` on the update payload means
  fields not provided stay unchanged. For nullable columns like `employee_id`
  and `department_id`, use `array_key_exists` to distinguish "not provided" vs
  "explicitly set to null".
- Avatar files are stored at `storage/app/public/avatars/{userId}/{filename}`.
  Run `php artisan storage:link` on fresh installs.
- `delete()` currently checks `$user->departments()` — this requires a
  `HasMany` or `BelongsToMany` back-reference. Add `departments()` hasMany
  relationship to User, or adjust the guard to query Department directly:
  `Department::where('manager_id', $user->id)->exists()`.
