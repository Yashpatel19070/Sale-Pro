<tr class="hover:bg-gray-50">
    <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-900">
        @if ($depth > 0)
            <span class="inline-block text-gray-400" style="width: {{ $depth * 1.5 }}rem">
                @if ($depth === 1) └ @else {{ str_repeat('  ', $depth - 1) }}└ @endif
            </span>
        @endif
        <a href="{{ route('product-categories.show', $category) }}"
           class="text-indigo-600 hover:text-indigo-900">
            {{ $category->name }}
        </a>
    </td>
    <td class="px-6 py-4 text-sm text-gray-600">
        {{ Str::limit($category->description ?? '—', 60) }}
    </td>
    <td class="whitespace-nowrap px-6 py-4 text-sm">
        @if ($category->is_active)
            <span class="inline-flex rounded-full bg-green-100 px-2 py-1 text-xs font-semibold text-green-800">Active</span>
        @else
            <span class="inline-flex rounded-full bg-red-100 px-2 py-1 text-xs font-semibold text-red-800">Inactive</span>
        @endif
    </td>
    <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-600">
        {{ $category->created_at->format('M d, Y') }}
    </td>
    <td class="whitespace-nowrap px-6 py-4 text-sm">
        <div class="flex items-center gap-3">
            @can('update', $category)
                <a href="{{ route('product-categories.edit', $category) }}"
                   class="text-gray-600 hover:text-gray-900">Edit</a>
            @endcan
            @can('delete', $category)
                <form method="POST"
                      action="{{ route('product-categories.destroy', $category) }}"
                      onsubmit="return confirm('Delete \'{{ $category->name }}\'?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                </form>
            @endcan
        </div>
    </td>
</tr>

{{-- Recurse into children --}}
@foreach ($category->children->sortBy('name') as $child)
    @include('product_categories._category-node', ['category' => $child, 'depth' => $depth + 1])
@endforeach
