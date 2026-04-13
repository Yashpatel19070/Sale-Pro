<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Audit Log</h2>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            {{-- Filters --}}
            <form method="GET" action="{{ route('audit-log.index') }}" class="mb-6 flex flex-wrap gap-3">

                <select name="log_name"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All logs</option>
                    <option value="default" @selected(request('log_name') === 'default')>Model events</option>
                    <option value="auth"    @selected(request('log_name') === 'auth')>Auth events</option>
                </select>

                <select name="subject_type"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All models</option>
                    @foreach($subjectTypes as $class => $label)
                        <option value="{{ $class }}" @selected(request('subject_type') === $class)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="event"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All events</option>
                    @foreach($events as $value => $label)
                        <option value="{{ $value }}" @selected(request('event') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="causer_id"
                        class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All users</option>
                    @foreach($causers as $user)
                        <option value="{{ $user->id }}" @selected(request('causer_id') == $user->id)>{{ $user->name }}</option>
                    @endforeach
                </select>

                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">

                <button type="submit"
                        class="rounded-md bg-gray-800 px-4 py-2 text-sm text-white hover:bg-gray-700">
                    Filter
                </button>
                <a href="{{ route('audit-log.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Clear
                </a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                @if($activities->isEmpty())
                    <div class="py-16 text-center text-gray-500">No activity found.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">When</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Who</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Event</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Model</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Record #</th>
                                <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($activities as $activity)
                                <tr>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ $activity->created_at->format('d M Y H:i') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-900">
                                        {{ $activity->causer?->name ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        @php
                                            $eventColors = [
                                                'created'      => 'bg-green-100 text-green-800',
                                                'updated'      => 'bg-blue-100 text-blue-800',
                                                'deleted'      => 'bg-red-100 text-red-800',
                                                'restored'     => 'bg-yellow-100 text-yellow-800',
                                                'login'        => 'bg-indigo-100 text-indigo-800',
                                                'logout'       => 'bg-gray-100 text-gray-800',
                                                'login-failed' => 'bg-red-100 text-red-800',
                                            ];
                                            $color = $eventColors[$activity->description] ?? 'bg-gray-100 text-gray-700';
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $color }}">
                                            {{ $activity->description }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
                                        {{ $subjectTypes[$activity->subject_type] ?? ($activity->subject_type ? class_basename($activity->subject_type) : '—') }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500">
                                        {{ $activity->subject_id ?? '—' }}
                                    </td>
                                    <td class="whitespace-nowrap px-6 py-4 text-sm">
                                        <a href="{{ route('audit-log.show', $activity) }}"
                                           class="text-indigo-600 hover:text-indigo-900">View</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="border-t border-gray-200 px-6 py-4">
                        {{ $activities->links() }}
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
