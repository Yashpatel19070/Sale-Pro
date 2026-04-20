<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">PO Returns</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            {{-- Filters --}}
            <form method="GET" action="{{ route('po-returns.index') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="PO number or supplier…"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <select name="status" class="rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option value="open" @selected(request('status') === 'open')>Open</option>
                    <option value="closed" @selected(request('status') === 'closed')>Closed</option>
                </select>
                <button type="submit"
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">Filter</button>
                <a href="{{ route('po-returns.index') }}"
                   class="rounded-md px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Parent PO</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Created</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse ($returns as $return)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium">
                                    <a href="{{ route('po-returns.show', $return) }}"
                                       class="text-indigo-600 hover:text-indigo-900">{{ $return->po_number }}</a>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">
                                    @if ($return->parentPo)
                                        <a href="{{ route('purchase-orders.show', $return->parentPo) }}"
                                           class="text-indigo-600 hover:text-indigo-900">{{ $return->parentPo->po_number }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">{{ $return->supplier->name }}</td>
                                <td class="px-6 py-4 text-sm">
                                    @include('purchase-orders._status_badge', ['status' => $return->status])
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $return->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-sm">
                                    <a href="{{ route('po-returns.show', $return) }}"
                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-400">No return POs found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $returns->links() }}</div>
        </div>
    </div>
</x-app-layout>
