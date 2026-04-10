# Department Module — Views

## View Tree

```
resources/views/departments/
├── index.blade.php        -- paginated table + search + filters
├── create.blade.php       -- create form
├── edit.blade.php         -- edit form (reuses _form partial)
├── show.blade.php         -- detail with user list
└── _form.blade.php        -- shared form partial
```

## Layout

All views extend `layouts.app` (Breeze default).

---

## index.blade.php — Key Sections

```blade
@extends('layouts.app')
@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold text-gray-900">Departments</h1>
        @can('create', App\Models\Department::class)
            <a href="{{ route('departments.create') }}"
               class="btn-primary">+ New Department</a>
        @endcan
    </div>

    {{-- Flash messages --}}
    @include('partials.flash')

    {{-- Search & filter form --}}
    <form method="GET" class="flex gap-3 mb-4">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Name or code…"
               class="input-field w-64" />
        <select name="active" class="input-field w-40">
            <option value="">All status</option>
            <option value="1" @selected(request('active') === '1')>Active</option>
            <option value="0" @selected(request('active') === '0')>Inactive</option>
        </select>
        <button type="submit" class="btn-secondary">Filter</button>
        <a href="{{ route('departments.index') }}" class="btn-ghost">Clear</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th>Name</th><th>Code</th><th>Manager</th>
                    <th>Members</th><th>Status</th><th>Actions</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($departments as $dept)
                <tr>
                    <td>
                        <a href="{{ route('departments.show', $dept) }}"
                           class="text-indigo-600 hover:underline font-medium">
                            {{ $dept->name }}
                        </a>
                    </td>
                    <td><span class="badge-gray">{{ $dept->code }}</span></td>
                    <td>{{ $dept->manager?->name ?? '—' }}</td>
                    <td>{{ $dept->users_count }}</td>
                    <td>
                        @if($dept->is_active)
                            <span class="badge-green">Active</span>
                        @else
                            <span class="badge-gray">Inactive</span>
                        @endif
                    </td>
                    <td class="flex gap-2">
                        @can('update', $dept)
                            <a href="{{ route('departments.edit', $dept) }}"
                               class="btn-xs-secondary">Edit</a>
                            <form method="POST"
                                  action="{{ route('departments.toggle-active', $dept) }}">
                                @csrf
                                <button class="btn-xs-ghost">
                                    {{ $dept->is_active ? 'Deactivate' : 'Activate' }}
                                </button>
                            </form>
                        @endcan
                        @can('delete', $dept)
                            <form method="POST"
                                  action="{{ route('departments.destroy', $dept) }}"
                                  onsubmit="return confirm('Delete this department?')">
                                @csrf @method('DELETE')
                                <button class="btn-xs-danger">Delete</button>
                            </form>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-8 text-gray-400">
                    No departments found.
                </td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{ $departments->links() }}
</div>
@endsection
```

---

## _form.blade.php — Shared Form Partial

```blade
{{-- $department is optional (null = create, object = edit) --}}
<div class="space-y-5">

    <div>
        <x-label for="name" value="Name *" />
        <x-input id="name" name="name" type="text" class="w-full"
                 value="{{ old('name', $department->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" />
    </div>

    <div>
        <x-label for="code" value="Code *" />
        <x-input id="code" name="code" type="text" class="w-40 uppercase"
                 maxlength="20"
                 value="{{ old('code', $department->code ?? '') }}"
                 placeholder="e.g. SALES" required />
        <p class="text-xs text-gray-500 mt-1">
            Uppercase letters only, max 20 chars. Cannot change after users are assigned.
        </p>
        <x-input-error :messages="$errors->get('code')" />
    </div>

    <div>
        <x-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
                  class="w-full rounded-md border-gray-300 shadow-sm">
            {{ old('description', $department->description ?? '') }}
        </textarea>
        <x-input-error :messages="$errors->get('description')" />
    </div>

    <div>
        <x-label for="manager_id" value="Manager" />
        <select id="manager_id" name="manager_id" class="w-full rounded-md border-gray-300">
            <option value="">— None —</option>
            @foreach ($managers as $user)
                <option value="{{ $user->id }}"
                    @selected(old('manager_id', $department->manager_id ?? null) == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('manager_id')" />
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" id="is_active" name="is_active" value="1"
               @checked(old('is_active', $department->is_active ?? true)) />
        <x-label for="is_active" value="Active" class="mb-0" />
    </div>

</div>
```

---

## show.blade.php — Key Sections

- Department meta: name, code, description, status badge, manager name
- Active member count badge
- Members table: name, job title, role badge, status badge
- Action buttons (Edit, Delete, Deactivate) gated by `@can`

---

## CSS Utility Classes

Use Tailwind CSS. Define these as `@layer components` in `resources/css/app.css`:

```css
@layer components {
  .btn-primary   { @apply inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-medium hover:bg-indigo-700; }
  .btn-secondary { @apply inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md text-sm font-medium hover:bg-gray-50; }
  .btn-ghost     { @apply inline-flex items-center px-4 py-2 text-sm text-gray-600 hover:text-gray-900; }
  .btn-xs-secondary { @apply text-xs px-2 py-1 border border-gray-300 rounded hover:bg-gray-50; }
  .btn-xs-danger { @apply text-xs px-2 py-1 bg-red-50 text-red-700 border border-red-200 rounded hover:bg-red-100; }
  .btn-xs-ghost  { @apply text-xs px-2 py-1 text-gray-500 hover:text-gray-700; }
  .badge-green   { @apply inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800; }
  .badge-gray    { @apply inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800; }
  .badge-red     { @apply inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800; }
  .input-field   { @apply rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm; }
}
```
