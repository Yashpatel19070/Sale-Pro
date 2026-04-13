<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">
                Audit Log — Activity #{{ $activity->id }}
            </h2>
            <a href="{{ route('audit-log.index') }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                &larr; Back to Log
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">

            {{-- Meta card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <dl class="divide-y divide-gray-100">
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-sm font-medium text-gray-500">When</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $activity->created_at->format('d M Y H:i:s') }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-sm font-medium text-gray-500">Who</dt>
                            <dd class="col-span-2 text-sm text-gray-900">
                                @if($activity->causer)
                                    {{ $activity->causer->name }}
                                    <span class="text-gray-400">({{ $activity->causer->email }})</span>
                                @else
                                    <span class="text-gray-400">Unknown / system</span>
                                @endif
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-sm font-medium text-gray-500">Event</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $activity->description }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 py-3">
                            <dt class="text-sm font-medium text-gray-500">Log</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $activity->log_name }}</dd>
                        </div>
                        @if($activity->subject_type)
                            <div class="grid grid-cols-3 gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-500">Model</dt>
                                <dd class="col-span-2 text-sm text-gray-900">
                                    {{ \App\Services\AuditLogService::SUBJECT_TYPES[$activity->subject_type] ?? class_basename($activity->subject_type) }}
                                    #{{ $activity->subject_id }}
                                </dd>
                            </div>
                        @endif
                        @if($activity->properties->has('ip'))
                            <div class="grid grid-cols-3 gap-4 py-3">
                                <dt class="text-sm font-medium text-gray-500">IP Address</dt>
                                <dd class="col-span-2 text-sm text-gray-900">{{ $activity->properties->get('ip') }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- Changed values (model events) --}}
            @if($activity->attribute_changes?->has('attributes') && count($activity->attribute_changes->get('attributes', [])) > 0)
                <div class="mt-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-4">
                        <h3 class="text-sm font-medium text-gray-700">Changed Values</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Field</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Before</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">After</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($activity->attribute_changes->get('attributes', []) as $field => $newValue)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-3 text-sm font-medium text-gray-900">{{ $field }}</td>
                                    <td class="px-6 py-3 text-sm text-red-600">
                                        {{ $activity->attribute_changes->get('old')[$field] ?? '—' }}
                                    </td>
                                    <td class="px-6 py-3 text-sm text-green-700">{{ $newValue }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- Auth event details --}}
            @if($activity->log_name === 'auth' && $activity->properties->isNotEmpty())
                <div class="mt-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="px-6 py-4">
                        <h3 class="text-sm font-medium text-gray-700">Details</h3>
                    </div>
                    <div class="px-6 pb-5">
                        <pre class="overflow-x-auto rounded bg-gray-50 p-4 text-sm">{{ json_encode($activity->properties, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
