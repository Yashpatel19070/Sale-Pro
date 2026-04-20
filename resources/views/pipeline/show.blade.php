<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Pipeline Job #{{ $unitJob->id }}
            </h2>
            <a href="{{ route('pipeline.queue') }}"
               class="text-sm text-indigo-600 hover:underline">← Back to Queue</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="rounded-md bg-green-50 p-4 text-sm text-green-700">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="rounded-md bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>
            @endif

            {{-- Job Info --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Job Details</h3>
                <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs text-gray-500">PO Number</dt>
                        <dd class="mt-1">
                            <a href="{{ route('purchase-orders.show', $unitJob->purchaseOrder) }}"
                               class="font-medium text-indigo-600 hover:underline">
                                {{ $unitJob->purchaseOrder->po_number }}
                            </a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Supplier</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $unitJob->purchaseOrder->supplier->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Product</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $unitJob->poLine->product->name }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Current Stage</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                {{ $unitJob->current_stage->label() }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Status</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                                {{ $unitJob->status->value === 'in_progress' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                {{ $unitJob->status->value === 'pending' ? 'bg-gray-100 text-gray-700' : '' }}
                                {{ $unitJob->status->value === 'passed' ? 'bg-green-100 text-green-800' : '' }}
                                {{ $unitJob->status->value === 'failed' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $unitJob->status->value === 'skipped' ? 'bg-purple-100 text-purple-800' : '' }}
                            ">
                                {{ $unitJob->status->value }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-gray-500">Assigned To</dt>
                        <dd class="mt-1 font-medium text-gray-900">{{ $unitJob->assignedTo?->name ?? '—' }}</dd>
                    </div>
                    @if ($unitJob->pending_serial_number)
                        <div>
                            <dt class="text-xs text-gray-500">Serial Number</dt>
                            <dd class="mt-1 font-mono font-medium text-gray-900">{{ $unitJob->pending_serial_number }}</dd>
                        </div>
                    @endif
                    @if ($unitJob->inventorySerial)
                        <div>
                            <dt class="text-xs text-gray-500">Inventory Serial</dt>
                            <dd class="mt-1 font-mono font-medium text-gray-900">
                                <a href="{{ route('inventory-serials.show', $unitJob->inventorySerial) }}"
                                   class="text-indigo-600 hover:underline">
                                    {{ $unitJob->inventorySerial->serial_number }}
                                </a>
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Pass / Fail actions (only when in_progress and assigned to current user) --}}
            @if ($unitJob->status->value === 'in_progress' && $unitJob->assigned_to_user_id === auth()->id())
                <div class="grid gap-4 sm:grid-cols-2">
                    {{-- Pass --}}
                    <div class="rounded-lg border border-green-200 bg-green-50 p-5">
                        <h3 class="mb-3 text-sm font-semibold text-green-800">Pass this stage</h3>
                        <form method="POST" action="{{ route('pipeline.pass', $unitJob) }}">
                            @csrf
                            @if ($unitJob->current_stage === \App\Enums\PipelineStage::SerialAssign)
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-700">Serial Number <span class="text-red-500">*</span></label>
                                    <input type="text" name="serial_number" value="{{ old('serial_number') }}" required
                                           class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                </div>
                            @endif
                            @if ($unitJob->current_stage === \App\Enums\PipelineStage::Shelf)
                                <div class="mb-3">
                                    <label class="block text-xs font-medium text-gray-700">Shelf Location <span class="text-red-500">*</span></label>
                                    <select name="inventory_location_id" required
                                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        <option value="">Select location…</option>
                                        @foreach ($locations as $loc)
                                            <option value="{{ $loc->id }}" @selected(old('inventory_location_id') == $loc->id)>{{ $loc->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-700">Notes</label>
                                <textarea name="notes" rows="2"
                                          class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Pass
                            </button>
                        </form>
                    </div>

                    {{-- Fail --}}
                    <div class="rounded-lg border border-red-200 bg-red-50 p-5">
                        <h3 class="mb-3 text-sm font-semibold text-red-800">Fail this unit</h3>
                        <form method="POST" action="{{ route('pipeline.fail', $unitJob) }}">
                            @csrf
                            <div class="mb-3">
                                <label class="block text-xs font-medium text-gray-700">Reason <span class="text-red-500">*</span></label>
                                <textarea name="notes" rows="3" required
                                          class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('notes') }}</textarea>
                            </div>
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                Fail Unit
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Event Timeline --}}
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <h3 class="mb-4 text-sm font-semibold uppercase tracking-wide text-gray-500">Event History</h3>
                @forelse ($unitJob->events as $event)
                    <div class="flex gap-3 py-2 border-b border-gray-100 last:border-0">
                        <div class="mt-0.5 flex-shrink-0">
                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full
                                bg-{{ $event->action->badgeColor() }}-100 text-{{ $event->action->badgeColor() }}-700
                                text-xs font-bold">
                                {{ strtoupper(substr($event->action->value, 0, 1)) }}
                            </span>
                        </div>
                        <div class="flex-1 text-sm">
                            <span class="font-medium text-gray-900">{{ $event->stage->label() }}</span>
                            <span class="text-gray-500"> — {{ $event->action->label() }}</span>
                            <span class="text-gray-400"> by {{ $event->user->name }}</span>
                            @if ($event->notes)
                                <p class="mt-0.5 text-gray-600 italic">{{ $event->notes }}</p>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 whitespace-nowrap">
                            {{ $event->created_at->diffForHumans() }}
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No events yet.</p>
                @endforelse
            </div>

        </div>
    </div>
</x-app-layout>
