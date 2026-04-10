<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $user->name }}</h2>
            <div class="flex items-center gap-2">
                @can('update', $user)
                    <a href="{{ route('users.edit', $user) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                @endcan
                @can('resetPassword', $user)
                    <form method="POST" action="{{ route('users.send-password-reset', $user) }}">
                        @csrf
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            Reset Password
                        </button>
                    </form>
                @endcan
                @can('delete', $user)
                    <form method="POST" action="{{ route('users.destroy', $user) }}"
                          onsubmit="return confirm('Delete ' + {{ Js::from($user->name) }} + '?')">
                        @csrf @method('DELETE')
                        <button class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            @include('partials.flash')

            {{-- Profile card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <div class="flex items-start gap-5">
                        <img src="{{ $user->avatar_url }}" alt="{{ $user->name }}"
                             class="w-20 h-20 rounded-full object-cover shadow" />
                        <div>
                            <h3 class="text-lg font-bold text-gray-900">{{ $user->name }}</h3>
                            <p class="text-sm text-gray-500">{{ $user->job_title ?? 'No title' }}</p>
                            <p class="text-sm text-gray-400">{{ $user->email }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $user->status->badgeClass() }}">
                                    {{ $user->status->label() }}
                                </span>
                                @foreach ($user->roles as $role)
                                    <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-xs font-medium text-indigo-800">
                                        {{ $role->name }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        @can('changeStatus', $user)
                            <form method="POST" action="{{ route('users.change-status', $user) }}" class="ml-auto">
                                @csrf
                                <select name="status" onchange="this.form.submit()"
                                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                    @foreach (\App\Enums\UserStatus::cases() as $status)
                                        <option value="{{ $status->value }}" @selected($user->status === $status)>
                                            {{ $status->label() }}
                                        </option>
                                    @endforeach
                                </select>
                            </form>
                        @endcan
                    </div>

                    <dl class="mt-6 grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Department</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->department?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Employee ID</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->employee_id ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Hired</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->hired_at?->format('M d, Y') ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Timezone</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->timezone }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Created by</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $user->createdBy?->name ?? 'System' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
