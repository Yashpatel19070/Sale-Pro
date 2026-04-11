<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('customers.index') }}" class="text-sm text-gray-400 hover:text-gray-600">Customers</a>
                <span class="text-gray-300">/</span>
                <span class="text-sm font-medium text-gray-700">{{ $customer->full_name }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @can('update', $customer)
                    <a href="{{ route('customers.edit', $customer) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                @endcan
                @if($customer->trashed())
                    @can('restore', $customer)
                        <form method="POST" action="{{ route('customers.restore', $customer->id) }}">
                            @csrf
                            <button class="inline-flex items-center rounded-md bg-green-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-green-700">
                                Restore
                            </button>
                        </form>
                    @endcan
                @else
                    @can('delete', $customer)
                        <form method="POST" action="{{ route('customers.destroy', $customer) }}"
                              onsubmit="return confirm('Delete {{ addslashes($customer->full_name) }}?')">
                            @csrf @method('DELETE')
                            <button class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                    @endcan
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            @if($customer->trashed())
                <div class="mb-4 rounded-md bg-yellow-50 px-4 py-3 text-sm text-yellow-800 border border-yellow-200">
                    This customer has been soft-deleted.
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- ── Left column: Identity ────────────────────────────── --}}
                <div class="space-y-5 lg:col-span-1">

                    {{-- Identity card --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="p-5">
                            <h3 class="text-xl font-bold text-gray-900">{{ $customer->full_name }}</h3>
                            @if($customer->company_name || $customer->job_title)
                                <p class="mt-1 text-sm text-gray-500">
                                    {{ implode(' · ', array_filter([$customer->company_name, $customer->job_title])) }}
                                </p>
                            @endif

                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @php $color = $customer->status->color(); @endphp
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold
                                    {{ $color === 'blue'   ? 'bg-blue-100 text-blue-800'     : '' }}
                                    {{ $color === 'yellow' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                    {{ $color === 'green'  ? 'bg-green-100 text-green-800'   : '' }}
                                    {{ $color === 'gray'   ? 'bg-gray-100 text-gray-700'     : '' }}">
                                    {{ $customer->status->label() }}
                                </span>
                                <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-semibold text-gray-700">
                                    {{ $customer->source->label() }}
                                </span>
                            </div>
                        </div>

                        <dl class="divide-y divide-gray-50 border-t border-gray-100">
                            <div class="flex items-center gap-2 px-5 py-3 text-sm">
                                <span class="text-gray-400">&#9993;</span>
                                @if($customer->email)
                                    <a href="mailto:{{ $customer->email }}" class="text-indigo-600 hover:underline truncate">{{ $customer->email }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-2 px-5 py-3 text-sm">
                                <span class="text-gray-400">&#9742;</span>
                                @if($customer->phone)
                                    <a href="tel:{{ $customer->phone }}" class="text-indigo-600 hover:underline">{{ $customer->phone }}</a>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Assigned To</dt>
                                <dd class="font-medium text-gray-900">
                                    @if($customer->assignedTo)
                                        <a href="{{ route('users.show', $customer->assignedTo) }}"
                                           class="text-indigo-600 hover:underline">{{ $customer->assignedTo->name }}</a>
                                    @else
                                        <span class="text-gray-400">Unassigned</span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Department</dt>
                                <dd class="font-medium text-gray-900">
                                    @if($customer->department)
                                        <a href="{{ route('departments.show', $customer->department) }}"
                                           class="text-indigo-600 hover:underline">{{ $customer->department->name }}</a>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Audit --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-5 py-3">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500">Audit</p>
                        </div>
                        <dl class="divide-y divide-gray-50">
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Created by</dt>
                                <dd class="font-medium text-gray-900">{{ $customer->createdBy?->name ?? 'System' }}</dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Created at</dt>
                                <dd class="font-medium text-gray-900">{{ $customer->created_at->format('M d, Y') }}</dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Updated by</dt>
                                <dd class="font-medium text-gray-900">{{ $customer->updatedBy?->name ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between px-5 py-3 text-sm">
                                <dt class="text-gray-500">Last updated</dt>
                                <dd class="font-medium text-gray-900">{{ $customer->updated_at->format('M d, Y') }}</dd>
                            </div>
                        </dl>
                    </div>

                </div>

                {{-- ── Right column: Details ────────────────────────────── --}}
                <div class="space-y-5 lg:col-span-2">

                    {{-- Address --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-700">Address</h3>
                        </div>
                        <div class="px-6 py-4 text-sm text-gray-700">
                            @if($customer->address_line1 || $customer->city || $customer->state || $customer->country)
                                <address class="not-italic leading-relaxed">
                                    @if($customer->address_line1)<div>{{ $customer->address_line1 }}</div>@endif
                                    @if($customer->address_line2)<div>{{ $customer->address_line2 }}</div>@endif
                                    @php
                                        $cityLine = implode(', ', array_filter([$customer->city, $customer->state, $customer->postcode]));
                                    @endphp
                                    @if($cityLine)<div>{{ $cityLine }}</div>@endif
                                    @if($customer->country)<div>{{ $customer->country }}</div>@endif
                                </address>
                            @else
                                <p class="text-gray-400">No address on file.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="overflow-hidden rounded-xl bg-white shadow">
                        <div class="border-b border-gray-100 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-700">Notes</h3>
                        </div>
                        <div class="px-6 py-4 text-sm text-gray-700">
                            @if($customer->notes)
                                <p class="whitespace-pre-line">{{ $customer->notes }}</p>
                            @else
                                <p class="text-gray-400">No notes.</p>
                            @endif
                        </div>
                    </div>

                    {{-- Assign & Status (admin + manager only) --}}
                    @can('assign', $customer)
                        <div class="overflow-hidden rounded-xl bg-white shadow">
                            <div class="border-b border-gray-100 px-6 py-4">
                                <h3 class="text-sm font-semibold text-gray-700">Assign &amp; Status</h3>
                            </div>
                            <div class="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2">

                                {{-- Assign form --}}
                                <form method="POST" action="{{ route('customers.assign', $customer) }}" class="space-y-3">
                                    @csrf
                                    <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500">Assign To</label>
                                    <select name="assigned_to"
                                            class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">— Unassigned —</option>
                                        @foreach($salesUsers ?? \App\Models\User::role('sales')->orderBy('name')->get(['id','name']) as $rep)
                                            <option value="{{ $rep->id }}" @selected($customer->assigned_to === $rep->id)>
                                                {{ $rep->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <button type="submit"
                                            class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                        Assign
                                    </button>
                                </form>

                                {{-- Change status form --}}
                                @can('changeStatus', $customer)
                                    <form method="POST" action="{{ route('customers.change-status', $customer) }}" class="space-y-3">
                                        @csrf
                                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-500">Change Status</label>
                                        <select name="status"
                                                class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                            @foreach(\App\Enums\CustomerStatus::cases() as $status)
                                                <option value="{{ $status->value }}" @selected($customer->status === $status)>
                                                    {{ $status->label() }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="submit"
                                                class="inline-flex w-full items-center justify-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                                            Update
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endcan

                </div>

            </div>
        </div>
    </div>
</x-app-layout>
