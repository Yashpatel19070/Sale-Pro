<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Inventory Movement History</h2>
    </x-slot>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Movement History</h1>
                <p class="mt-1 text-sm text-gray-500">Immutable ledger of every serial number movement.</p>
            </div>
            @can('create', App\Models\InventoryMovement::class)
                <a href="{{ route('inventory-movements.create') }}"
                   class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm
                          font-medium rounded-lg hover:bg-indigo-700 transition">
                    Record Movement
                </a>
            @endcan
        </div>

        {{-- Flash message --}}
        @if (session('success'))
            <div class="mb-4 rounded-lg bg-green-50 px-4 py-3 text-sm text-green-800 border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        {{-- Domain error --}}
        @if ($errors->has('error'))
            <div class="mb-4 rounded-lg bg-red-50 px-4 py-3 text-sm text-red-800 border border-red-200">
                {{ $errors->first('error') }}
            </div>
        @endif

        {{-- Filters --}}
        <form method="GET" action="{{ route('inventory-movements.index') }}"
              class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">

            <div>
                <label for="serial_number" class="block text-xs font-medium text-gray-700 mb-1">
                    Serial Number
                </label>
                <input type="text"
                       id="serial_number"
                       name="serial_number"
                       value="{{ $filters['serial_number'] ?? '' }}"
                       placeholder="SN-00123"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="location_id" class="block text-xs font-medium text-gray-700 mb-1">
                    Location
                </label>
                <select id="location_id" name="location_id"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                               focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All locations</option>
                    @foreach ($locations as $location)
                        <option value="{{ $location->id }}"
                                @selected(($filters['location_id'] ?? '') == $location->id)>
                            {{ $location->code }} — {{ $location->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="type" class="block text-xs font-medium text-gray-700 mb-1">
                    Type
                </label>
                <select id="type" name="type"
                        class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                               focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All types</option>
                    @foreach ($types as $type)
                        <option value="{{ $type->value }}"
                                @selected(($filters['type'] ?? '') === $type->value)>
                            {{ $type->label() }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="date_from" class="block text-xs font-medium text-gray-700 mb-1">
                    From date
                </label>
                <input type="date"
                       id="date_from"
                       name="date_from"
                       value="{{ $filters['date_from'] ?? '' }}"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div>
                <label for="date_to" class="block text-xs font-medium text-gray-700 mb-1">
                    To date
                </label>
                <input type="date"
                       id="date_to"
                       name="date_to"
                       value="{{ $filters['date_to'] ?? '' }}"
                       class="block w-full rounded-md border-gray-300 shadow-sm text-sm
                              focus:border-indigo-500 focus:ring-indigo-500">
            </div>

            <div class="lg:col-span-5 flex gap-3">
                <button type="submit"
                        class="px-4 py-2 bg-gray-800 text-white text-sm font-medium rounded-lg
                               hover:bg-gray-700 transition">
                    Filter
                </button>
                <a href="{{ route('inventory-movements.index') }}"
                   class="px-4 py-2 bg-white text-gray-700 text-sm font-medium rounded-lg border
                          border-gray-300 hover:bg-gray-50 transition">
                    Reset
                </a>
            </div>
        </form>

        {{-- Table --}}
        <div class="bg-white shadow-sm rounded-lg overflow-hidden">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Date / Time
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Type
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Serial Number
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Product
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Direction
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Reference
                        </th>
                        <th class="px-4 py-3 text-left font-medium text-gray-500 uppercase tracking-wider text-xs">
                            Recorded by
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse ($movements as $movement)
                        @php
                            $badgeColors = [
                                'green'  => 'bg-green-100 text-green-800',
                                'blue'   => 'bg-blue-100 text-blue-800',
                                'purple' => 'bg-purple-100 text-purple-800',
                                'yellow' => 'bg-yellow-100 text-yellow-800',
                            ];
                            $badge = $badgeColors[$movement->type->badgeColor()] ?? 'bg-gray-100 text-gray-800';
                        @endphp
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                {{ $movement->created_at->format('Y-m-d H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5
                                             text-xs font-medium {{ $badge }}">
                                    {{ $movement->type->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-900">
                                {{ $movement->serial->serial_number }}
                            </td>
                            <td class="px-4 py-3 text-gray-700">
                                {{ $movement->serial->product->name }}
                                <span class="text-gray-400 text-xs ml-1">
                                    {{ $movement->serial->product->sku }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600 font-mono text-xs">
                                {{ $movement->directionLabel() }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $movement->reference ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                {{ $movement->user->name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-gray-400">
                                No movements found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($movements->hasPages())
            <div class="mt-4">
                {{ $movements->links() }}
            </div>
        @endif

    </div>
</x-app-layout>
