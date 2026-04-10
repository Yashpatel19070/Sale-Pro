<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $department->name }}</h2>
            <div class="flex items-center gap-2">
                @can('update', $department)
                    <a href="{{ route('departments.edit', $department) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('departments.toggle-active', $department) }}">
                        @csrf
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $department->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                @endcan
                @can('delete', $department)
                    <form method="POST" action="{{ route('departments.destroy', $department) }}"
                          onsubmit="return confirm('Delete ' + {{ Js::from($department->name) }} + '?')">
                        @csrf
                        @method('DELETE')
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

            {{-- Info card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-800">
                                    {{ $department->code }}
                                </span>
                                @if ($department->is_active)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                @endif
                            </div>
                            @if ($department->description)
                                <p class="mt-2 text-sm text-gray-600">{{ $department->description }}</p>
                            @endif
                        </div>
                    </div>

                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Manager</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $department->manager?->name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Members</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $department->users->count() }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $department->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Members list --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-900">Members</h3>
                </div>
                @if ($department->users->isEmpty())
                    <p class="px-6 py-8 text-center text-sm text-gray-400">No members assigned.</p>
                @else
                    <ul class="divide-y divide-gray-100">
                        @foreach ($department->users as $member)
                            <li class="flex items-center justify-between px-6 py-3">
                                <span class="text-sm text-gray-900">{{ $member->name }}</span>
                                @php $status = $member->status instanceof \App\Enums\UserStatus ? $member->status->value : $member->status; @endphp
                                @if ($status === 'active')
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Active</span>
                                @elseif ($status === 'suspended')
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">Suspended</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inactive</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
