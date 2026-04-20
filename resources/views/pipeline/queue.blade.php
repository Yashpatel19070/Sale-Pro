<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Pipeline Queue</h2>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            {{-- Filter --}}
            <form method="GET" action="{{ route('pipeline.queue') }}" class="mb-6 flex flex-wrap gap-3">
                <input type="number" name="purchase_order_id" value="{{ request('purchase_order_id') }}"
                       placeholder="PO ID…"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 w-32">
                <button type="submit"
                        class="rounded-md bg-gray-100 px-4 py-2 text-sm text-gray-700 hover:bg-gray-200">Filter</button>
                <a href="{{ route('pipeline.queue') }}"
                   class="rounded-md px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500">
                        <tr>
                            <th class="px-4 py-3">Job #</th>
                            <th class="px-4 py-3">PO Number</th>
                            <th class="px-4 py-3">Product</th>
                            <th class="px-4 py-3">Stage</th>
                            <th class="px-4 py-3">Serial</th>
                            <th class="px-4 py-3">Queued</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($jobs as $job)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 font-mono text-gray-700">#{{ $job->id }}</td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('purchase-orders.show', $job->purchaseOrder) }}"
                                       class="text-indigo-600 hover:underline">
                                        {{ $job->purchaseOrder->po_number }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-gray-700">{{ $job->poLine->product->name }}</td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                        {{ $job->current_stage->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 font-mono text-gray-500">
                                    {{ $job->pending_serial_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $job->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-3 text-right">
                                    @can('start', $job)
                                        <form method="POST" action="{{ route('pipeline.start', $job) }}">
                                            @csrf
                                            <button type="submit"
                                                    class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                                                Take
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('pipeline.show', $job) }}"
                                           class="text-xs text-gray-500 hover:underline">View</a>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-8 text-center text-gray-400">No pending jobs in your queue.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $jobs->links() }}</div>

        </div>
    </div>
</x-app-layout>
