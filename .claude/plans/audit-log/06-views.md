# Audit Log Module — Views

## index
`resources/views/audit_log/index.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">Audit Log</x-slot>

    {{-- Filters --}}
    <form method="GET" action="{{ route('audit-log.index') }}" class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
        {{-- Log type --}}
        <select name="log_name" class="input">
            <option value="">All logs</option>
            <option value="default" @selected(request('log_name') === 'default')>Model events</option>
            <option value="auth"    @selected(request('log_name') === 'auth')>Auth events</option>
        </select>

        {{-- Subject type --}}
        <select name="subject_type" class="input">
            <option value="">All models</option>
            @foreach($subjectTypes as $class => $label)
                <option value="{{ $class }}" @selected(request('subject_type') === $class)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Event --}}
        <select name="event" class="input">
            <option value="">All events</option>
            @foreach($events as $value => $label)
                <option value="{{ $value }}" @selected(request('event') === $value)>{{ $label }}</option>
            @endforeach
        </select>

        {{-- Causer --}}
        <select name="causer_id" class="input">
            <option value="">All users</option>
            @foreach($causers as $user)
                <option value="{{ $user->id }}" @selected(request('causer_id') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </select>

        {{-- Date range --}}
        <input type="date" name="date_from" value="{{ request('date_from') }}" class="input" placeholder="From">
        <input type="date" name="date_to"   value="{{ request('date_to') }}"   class="input" placeholder="To">

        <div class="col-span-full flex gap-2">
            <button type="submit" class="btn-primary">Filter</button>
            <a href="{{ route('audit-log.index') }}" class="btn-secondary">Reset</a>
        </div>
    </form>

    {{-- Table --}}
    <div class="overflow-x-auto">
        <table class="table w-full">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Who</th>
                    <th>Event</th>
                    <th>Model</th>
                    <th>Record</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($activities as $activity)
                    <tr>
                        <td class="whitespace-nowrap text-sm text-gray-500">
                            {{ $activity->created_at->format('d M Y H:i') }}
                        </td>
                        <td>
                            @if($activity->causer)
                                {{ $activity->causer->name }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            <span @class([
                                'badge',
                                'badge-green'  => $activity->description === 'created',
                                'badge-blue'   => $activity->description === 'updated',
                                'badge-red'    => in_array($activity->description, ['deleted', 'login-failed']),
                                'badge-gray'   => in_array($activity->description, ['logout', 'restored']),
                                'badge-yellow' => $activity->description === 'login',
                            ])>
                                {{ $activity->description }}
                            </span>
                        </td>
                        <td>
                            @if($activity->subject_type)
                                {{ $subjectTypes[$activity->subject_type] ?? class_basename($activity->subject_type) }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="text-sm text-gray-500">
                            @if($activity->subject_id)
                                #{{ $activity->subject_id }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('audit-log.show', $activity) }}" class="link">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-gray-400 py-8">No activity found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $activities->links() }}
</x-app-layout>
```

---

## show
`resources/views/audit_log/show.blade.php`

```blade
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-4">
            <a href="{{ route('audit-log.index') }}" class="link">&larr; Audit Log</a>
            <span>Activity #{{ $activity->id }}</span>
        </div>
    </x-slot>

    <div class="card max-w-2xl">
        {{-- Meta --}}
        <dl class="divide-y">
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">When</dt>
                <dd class="col-span-2">{{ $activity->created_at->format('d M Y H:i:s') }}</dd>
            </div>
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">Who</dt>
                <dd class="col-span-2">
                    @if($activity->causer)
                        {{ $activity->causer->name }} ({{ $activity->causer->email }})
                    @else
                        <span class="text-gray-400">Unknown / system</span>
                    @endif
                </dd>
            </div>
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">Event</dt>
                <dd class="col-span-2">{{ $activity->description }}</dd>
            </div>
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">Model</dt>
                <dd class="col-span-2">
                    @if($activity->subject_type)
                        {{ $subjectTypes[$activity->subject_type] ?? class_basename($activity->subject_type) }}
                        #{{ $activity->subject_id }}
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </dd>
            </div>
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">Log</dt>
                <dd class="col-span-2">{{ $activity->log_name }}</dd>
            </div>
            @if($activity->properties->has('ip'))
            <div class="py-3 grid grid-cols-3">
                <dt class="font-medium text-gray-500">IP Address</dt>
                <dd class="col-span-2">{{ $activity->properties->get('ip') }}</dd>
            </div>
            @endif
        </dl>

        {{-- Changed values (model events) --}}
        {{-- v5: before/after values are in attribute_changes, NOT properties --}}
        @if($activity->attribute_changes?->has('attributes') && count($activity->attribute_changes->get('attributes', [])) > 0)
            <div class="mt-6">
                <h3 class="font-semibold mb-3">Changed Values</h3>
                <table class="table w-full text-sm">
                    <thead>
                        <tr>
                            <th>Field</th>
                            <th>Before</th>
                            <th>After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($activity->attribute_changes->get('attributes', []) as $field => $newValue)
                            <tr>
                                <td class="font-medium">{{ $field }}</td>
                                <td class="text-red-600">
                                    {{ $activity->attribute_changes->get('old')[$field] ?? '—' }}
                                </td>
                                <td class="text-green-600">{{ $newValue }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Auth event details — render known-safe keys only, never json_encode dump --}}
        {{-- properties holds manual withProperties() data: ip for auth events --}}
        {{-- DO NOT dump $activity->properties as raw JSON — exposes any future field additions --}}
    </div>
</x-app-layout>
```

---

## Checklist

- [ ] `audit_log/index.blade.php` — table with 6 filters (log_name, subject_type, event, causer, date_from, date_to)
- [ ] Index: event badge uses color-coding
- [ ] Index: subject_type resolved via `$subjectTypes` variable (passed from controller, not service constant in Blade)
- [ ] `audit_log/show.blade.php` — meta card + changed values table
- [ ] Show: "Before / After" table reads from `$activity->attribute_changes` (v5), not `$activity->properties`
- [ ] Show: IP rendered from `$activity->properties->get('ip')` in meta card — no raw JSON dump
- [ ] Show: controller passes `$subjectTypes` so Blade doesn't reference `AuditLogService::` directly
- [ ] Back link to index on show page
