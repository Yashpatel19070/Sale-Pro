<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="text-xl font-semibold leading-tight text-gray-800">Product Listings</h2>
            @can('create', App\Models\ProductListing::class)
                <a href="{{ route('product-listings.create') }}"
                   class="inline-flex items-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    + New Listing
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
                       placeholder="Search title…"
                       class="w-56 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" />

                <select name="product_id"
                        class="w-48 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All products</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected(request('product_id') == $product->id)>
                            {{ $product->sku }} — {{ $product->name }}
                        </option>
                    @endforeach
                </select>

                <select name="visibility"
                        class="w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All visibility</option>
                    @foreach ($visibilities as $value => $label)
                        <option value="{{ $value }}" @selected(request('visibility') === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <select name="active"
                        class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <option value="">All status</option>
                    <option value="1" @selected(request('active') === '1')>Active</option>
                    <option value="0" @selected(request('active') === '0')>Inactive</option>
                </select>

                <button type="submit"
                        class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    Filter
                </button>
                <a href="{{ route('product-listings.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700">Clear</a>
            </form>

            {{-- Table --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Title</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">SKU</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Category</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Regular Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Sale Price</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Visibility</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @forelse ($listings as $listing)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <a href="{{ route('product-listings.show', $listing) }}"
                                       class="font-medium text-indigo-600 hover:underline">
                                        {{ $listing->title }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 font-mono text-sm text-gray-700">{{ $listing->product->sku }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    <a href="{{ route('products.show', $listing->product) }}"
                                       class="hover:underline">
                                        {{ $listing->product->name }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $listing->product->category?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    ${{ $listing->product->regular_price }}
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">
                                    @if ($listing->product->sale_price)
                                        <span class="font-medium text-green-700">${{ $listing->product->sale_price }}</span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $listing->visibility->badgeClass() }}">
                                        {{ $listing->visibility->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($listing->is_active)
                                        <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">Active</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @can('view', $listing)
                                            <a href="{{ route('product-listings.show', $listing) }}"
                                               class="text-xs text-indigo-600 hover:underline">View</a>
                                        @endcan
                                        @can('update', $listing)
                                            <a href="{{ route('product-listings.edit', $listing) }}"
                                               class="text-xs text-gray-600 hover:text-gray-900">Edit</a>
                                            <form method="POST"
                                                  action="{{ route('product-listings.toggle-visibility', $listing) }}">
                                                @csrf
                                                <button class="text-xs text-gray-500 hover:text-gray-700">
                                                    {{ $listing->visibility === App\Enums\ListingVisibility::Public ? 'Set Draft' : 'Set Public' }}
                                                </button>
                                            </form>
                                        @endcan
                                        @can('delete', $listing)
                                            <form method="POST"
                                                  action="{{ route('product-listings.destroy', $listing) }}"
                                                  onsubmit="return confirm('Delete ' + {{ Js::from($listing->title) }} + '?')">
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
                                <td colspan="9" class="px-4 py-10 text-center text-sm text-gray-400">
                                    No listings found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $listings->links() }}
            </div>

        </div>
    </div>
</x-app-layout>
