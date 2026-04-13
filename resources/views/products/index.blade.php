<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Products</h2>
            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Product
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">

            @include('partials.flash')

            {{-- Filters --}}
            <form method="GET" class="mb-4 flex flex-wrap gap-3">
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="Name or SKU…"
                       class="w-64 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="category_id"
                        class="w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All categories</option>
                    @foreach ($categories as $cat)
                        <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>
                            {{ $cat->name }}
                        </option>
                    @endforeach
                </select>

                <select name="active"
                        class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All status</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Filter
                </button>
                <a href="{{ route('products.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Name</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($products as $product)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-800">
                                        {{ $product->sku }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="{{ route('products.show', $product) }}"
                                       class="font-medium text-indigo-600 hover:underline">
                                        {{ $product->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $product->category?->name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    @if ($product->isOnSale())
                                        <span class="font-medium text-green-700">${{ $product->sale_price }}</span>
                                        <span class="ml-1 text-xs text-gray-400 line-through">${{ $product->regular_price }}</span>
                                        <span class="ml-1 inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-800">SALE</span>
                                    @else
                                        ${{ $product->regular_price }}
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($product->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('view', $product)
                                            <a href="{{ route('products.show', $product) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                        @endcan
                                        @can('update', $product)
                                            <a href="{{ route('products.edit', $product) }}"
                                               class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                            <form method="POST"
                                                  action="{{ route('products.toggle-active', $product) }}">
                                                @csrf
                                                <button class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $product)
                                            <form method="POST"
                                                  action="{{ route('products.destroy', $product) }}"
                                                  onsubmit="return confirm('Delete ' + {{ Js::from($product->name) }} + '?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-xs text-red-600 hover:text-red-800">Delete</button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No products found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $products->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
