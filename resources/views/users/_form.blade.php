{{-- $user (optional) = existing model on edit, null on create --}}
{{-- $departments, $roles, $statuses, $timezones required --}}
<div class="space-y-6">

    {{-- Personal --}}
    <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Personal Information</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <div>
                <x-input-label for="name" value="Full Name *" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                              value="{{ old('name', $user?->name ?? '') }}" required />
                <x-input-error :messages="$errors->get('name')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="email" value="Email *" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                              value="{{ old('email', $user?->email ?? '') }}" required />
                <x-input-error :messages="$errors->get('email')" class="mt-1" />
            </div>

            @if (!isset($user))
                <div>
                    <x-input-label for="password" value="Password *" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" required />
                    <x-input-error :messages="$errors->get('password')" class="mt-1" />
                </div>
                <div>
                    <x-input-label for="password_confirmation" value="Confirm Password *" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" required />
                </div>
            @endif

            <div>
                <x-input-label for="phone" value="Phone" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                              value="{{ old('phone', $user?->phone ?? '') }}" />
                <x-input-error :messages="$errors->get('phone')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="timezone" value="Timezone *" />
                <select id="timezone" name="timezone"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($timezones as $tz)
                        <option value="{{ $tz }}" @selected(old('timezone', $user?->timezone ?? 'UTC') === $tz)>
                            {{ $tz }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('timezone')" class="mt-1" />
            </div>

        </div>
    </div>

    {{-- Job Details --}}
    <div class="border-b border-gray-200 pb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Job Details</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <div>
                <x-input-label for="job_title" value="Job Title" />
                <x-text-input id="job_title" name="job_title" type="text" class="mt-1 block w-full"
                              value="{{ old('job_title', $user?->job_title ?? '') }}" />
                <x-input-error :messages="$errors->get('job_title')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="employee_id" value="Employee ID" />
                <x-text-input id="employee_id" name="employee_id" type="text" class="mt-1 block w-full"
                              value="{{ old('employee_id', $user?->employee_id ?? '') }}" />
                <x-input-error :messages="$errors->get('employee_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="department_id" value="Department" />
                <select id="department_id" name="department_id"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    <option value="">— None —</option>
                    @foreach ($departments as $dept)
                        <option value="{{ $dept->id }}"
                            @selected(old('department_id', $user?->department_id ?? null) == $dept->id)>
                            {{ $dept->name }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('department_id')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="hired_at" value="Hire Date" />
                <x-text-input id="hired_at" name="hired_at" type="date" class="mt-1 block w-full"
                              value="{{ old('hired_at', isset($user) && $user->hired_at ? $user->hired_at->format('Y-m-d') : '') }}" />
                <x-input-error :messages="$errors->get('hired_at')" class="mt-1" />
            </div>

        </div>
    </div>

    {{-- Account --}}
    <div class="pb-2">
        <h3 class="text-sm font-semibold text-gray-700 mb-4">Account</h3>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

            <div>
                <x-input-label for="role" value="Role *" />
                <select id="role" name="role"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($roles as $role)
                        <option value="{{ $role }}"
                            @selected(old('role', $user?->roles->first()?->name ?? '') === $role)>
                            {{ ucfirst($role) }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('role')" class="mt-1" />
            </div>

            <div>
                <x-input-label for="status" value="Status *" />
                <select id="status" name="status"
                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                    @foreach ($statuses as $status)
                        <option value="{{ $status->value }}"
                            @selected(old('status', $user?->status?->value ?? 'active') === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-1" />
            </div>

        </div>
    </div>

    {{-- Avatar --}}
    <div>
        <x-input-label for="avatar" value="Avatar" />
        @if (isset($user) && $user->avatar)
            <div class="mb-2 flex items-center gap-3">
                <img src="{{ $user->avatar_url }}" class="w-12 h-12 rounded-full object-cover" alt="Current avatar" />
                <span class="text-xs text-gray-500">Upload new image to replace.</span>
            </div>
        @endif
        <input type="file" id="avatar" name="avatar" accept="image/*"
               class="mt-1 block text-sm text-gray-600" />
        <x-input-error :messages="$errors->get('avatar')" class="mt-1" />
    </div>

</div>
