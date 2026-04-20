<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Supplier: {{ $supplier->code }}
            </h2>
            <div class="flex items-center gap-3">
                @if($supplier->trashed())
                    @can('restore', $supplier)
                        <form method="POST" action="{{ route('suppliers.restore', $supplier) }}"
                              x-data @submit.prevent="if(confirm('Restore this supplier?')) $el.submit()">
                            @csrf
                            <button class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Restore
                            </button>
                        </form>
                    @endcan
                @else
                    @can('update', $supplier)
                        <a href="{{ route('suppliers.edit', $supplier) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                            Edit
                        </a>
                    @endcan
                    @can('delete', $supplier)
                        <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
                              x-data @submit.prevent="if(confirm('Deactivate this supplier?')) $el.submit()">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                Deactivate
                            </button>
                        </form>
                    @endcan
                @endif
                <a href="{{ route('suppliers.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to List
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($supplier->trashed())
                <div class="mb-4 rounded-md bg-yellow-100 px-4 py-3 text-yellow-800">
                    This supplier is inactive (deactivated).
                </div>
            @endif

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $supplier->code }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1">
                                @if($supplier->trashed())
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-medium text-red-700">Inactive</span>
                                @else
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-700">Active</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->contact_name ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Email</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->contact_email ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Contact Phone</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->contact_phone ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $supplier->created_at->format('M j, Y') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Address</dt>
                            <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $supplier->address ?? '—' }}</dd>
                        </div>
                        @if($supplier->notes)
                            <div class="sm:col-span-2">
                                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                                <dd class="mt-1 whitespace-pre-line text-sm text-gray-900">{{ $supplier->notes }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
