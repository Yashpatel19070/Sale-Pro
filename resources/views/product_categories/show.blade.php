<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                {{-- Breadcrumb --}}
                <div class="mb-1 flex items-center gap-1 text-sm text-gray-500">
                    <a href="{{ route('product-categories.index') }}" class="hover:text-gray-700">Categories</a>
                    @if ($category->parent)
                        <span>/</span>
                        <a href="{{ route('product-categories.show', $category->parent) }}"
                           class="hover:text-gray-700">{{ $category->parent->name }}</a>
                    @endif
                    <span>/</span>
                    <span class="text-gray-800">{{ $category->name }}</span>
                </div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $category->name }}</h2>
            </div>
            <div class="flex items-center gap-3">
                @can('update', $category)
                    <a href="{{ route('product-categories.edit', $category) }}"
                       class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Edit
                    </a>
                @endcan
                <a href="{{ route('product-categories.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Detail card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="px-6 py-5">
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $category->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Parent</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($category->parent)
                                    <a href="{{ route('product-categories.show', $category->parent) }}"
                                       class="text-indigo-600 hover:text-indigo-900">
                                        {{ $category->parent->name }}
                                    </a>
                                @else
                                    <span class="text-gray-400">— Root category</span>
                                @endif
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Description</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $category->description ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm">
                                @if ($category->is_active)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500">Created</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $category->created_at->format('M d, Y') }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Children --}}
            @if ($category->children->isNotEmpty())
                <div class="mt-6 overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-sm font-medium text-gray-700">
                            Subcategories ({{ $category->children->count() }})
                        </h3>
                    </div>
                    <ul class="divide-y divide-gray-200">
                        @foreach ($category->children->sortBy('name') as $child)
                            <li class="flex items-center justify-between px-6 py-3">
                                <a href="{{ route('product-categories.show', $child) }}"
                                   class="text-sm text-indigo-600 hover:text-indigo-900">
                                    {{ $child->name }}
                                </a>
                                @if ($child->is_active)
                                    <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
                                @else
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Delete --}}
            @can('delete', $category)
                <div class="mt-6">
                    <form method="POST"
                          action="{{ route('product-categories.destroy', $category) }}"
                          onsubmit="return confirm('Delete \'{{ $category->name }}\'?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                            Delete Category
                        </button>
                    </form>
                </div>
            @endcan

        </div>
    </div>
</x-app-layout>
