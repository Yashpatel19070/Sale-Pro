<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Movement Timeline — {{ $inventorySerial->serial_number }}
        </h2>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">

        <div class="mb-6">
            <a href="{{ route('inventory-serials.show', $inventorySerial) }}"
               class="text-sm text-indigo-600 hover:underline">&larr; Back to serial</a>
            <h1 class="mt-2 text-2xl font-bold text-gray-900">Movement Timeline</h1>
            <p class="text-sm text-gray-500">
                Serial: <span class="font-mono font-medium">{{ $inventorySerial->serial_number }}</span>
                &mdash; {{ $inventorySerial->product->name }}
                ({{ $inventorySerial->product->sku }})
            </p>
        </div>

        {{-- Timeline --}}
        <ol class="relative border-l border-gray-200 ml-4">
            @forelse ($movements as $movement)
                @php
                    $dotColor = match($movement->type->badgeColor()) {
                        'green'  => 'bg-green-500',
                        'blue'   => 'bg-blue-500',
                        'purple' => 'bg-purple-500',
                        'yellow' => 'bg-yellow-500',
                        default  => 'bg-gray-400',
                    };
                @endphp
                <li class="mb-8 ml-6">
                    <span class="absolute -left-3 flex h-6 w-6 items-center justify-center
                                 rounded-full {{ $dotColor }} ring-4 ring-white"></span>
                    <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                        <div class="flex items-start justify-between mb-1">
                            <span class="text-sm font-semibold text-gray-900">
                                {{ $movement->type->label() }}
                            </span>
                            <time class="text-xs text-gray-400">
                                {{ $movement->created_at->format('Y-m-d H:i') }}
                            </time>
                        </div>
                        <p class="text-sm text-gray-600 font-mono">
                            {{ $movement->directionLabel() }}
                        </p>
                        @if ($movement->reference)
                            <p class="mt-1 text-xs text-gray-500">
                                Reference: {{ $movement->reference }}
                            </p>
                        @endif
                        @if ($movement->notes)
                            <p class="mt-1 text-xs text-gray-500 italic">
                                {{ $movement->notes }}
                            </p>
                        @endif
                        <p class="mt-1 text-xs text-gray-400">
                            Recorded by {{ $movement->user->name }}
                        </p>
                    </div>
                </li>
            @empty
                <li class="ml-6 text-sm text-gray-400">No movements recorded yet.</li>
            @endforelse
        </ol>

    </div>
</x-app-layout>
