<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $product->name }}</h2>
                @if ($product->is_active)
                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @can('update', $product)
                    <a href="{{ route('products.edit', $product) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('products.toggle-active', $product) }}">
                        @csrf
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $product->is_active ? 'Deactivate' : 'Activate' }}
                        </button>
                    </form>
                @endcan
                @can('delete', $product)
                    <form method="POST" action="{{ route('products.destroy', $product) }}"
                          onsubmit="return confirm('Delete ' + {{ Js::from($product->name) }} + '?')">
                        @csrf
                        @method('DELETE')
                        <button class="inline-flex items-center rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700">
                            Delete
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-6">

            @include('partials.flash')

            <div class="mb-2">
                <a href="{{ route('products.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Products</a>
            </div>

            {{-- Main detail --}}
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

                {{-- Left: product details --}}
                <div class="lg:col-span-2 space-y-6">
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-6">
                            <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <dt class="text-xs font-medium uppercase text-gray-500">SKU</dt>
                                    <dd class="mt-1 font-mono text-sm font-medium text-gray-900">{{ $product->sku }}</dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase text-gray-500">Category</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($product->category)
                                            {{ $product->category->name }}
                                        @else
                                            <span class="text-gray-400">Uncategorised</span>
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase text-gray-500">Regular Price</dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        @if ($product->isOnSale())
                                            <span class="line-through text-gray-400">${{ $product->regular_price }}</span>
                                        @else
                                            ${{ $product->regular_price }}
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase text-gray-500">Sale Price</dt>
                                    <dd class="mt-1 text-sm">
                                        @if ($product->isOnSale())
                                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800">
                                                On Sale: ${{ $product->sale_price }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </dd>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase text-gray-500">Purchase Price <span class="normal-case text-gray-400">(internal, not shown to customers)</span></dt>
                                    <dd class="mt-1 text-sm text-gray-900">
                                        {{ $product->purchase_price ? '$'.$product->purchase_price : '—' }}
                                    </dd>
                                </div>
                            </dl>

                            @if ($product->description)
                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <dt class="text-xs font-medium uppercase text-gray-500">Description</dt>
                                    <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $product->description }}</dd>
                                </div>
                            @endif

                            @if ($product->notes)
                                <div class="mt-4 border-t border-gray-100 pt-4">
                                    <dt class="text-xs font-medium uppercase text-gray-500">Internal Notes</dt>
                                    <dd class="mt-1 text-sm text-gray-700 whitespace-pre-line">{{ $product->notes }}</dd>
                                </div>
                            @endif

                            <div class="mt-4 border-t border-gray-100 pt-4 flex gap-6 text-xs text-gray-400">
                                <span>Created {{ $product->created_at->format('M d, Y') }}</span>
                                <span>Updated {{ $product->updated_at->format('M d, Y') }}</span>
                            </div>
                        </div>
                    </div>

                    {{-- Listings preview --}}
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                            <h3 class="text-sm font-semibold text-gray-900">Listings</h3>
                            {{-- TODO: replace with route('product-listings.index', ['product_id' => $product->id]) once module exists --}}
                            <span class="text-xs text-gray-400">Manage Listings (coming soon)</span>
                        </div>
                        @if ($product->listings->isEmpty())
                            <p class="px-6 py-8 text-center text-sm text-gray-400">No listings yet.</p>
                        @else
                            <table class="min-w-full divide-y divide-gray-100">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Title</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    @foreach ($product->listings->take(5) as $listing)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 text-sm text-gray-900">{{ $listing->title }}</td>
                                            <td class="px-4 py-2">
                                                @if ($listing->is_active)
                                                    <span class="inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-800">Active</span>
                                                @else
                                                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs text-gray-600">Inactive</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @if ($product->listings->count() > 5)
                                <p class="px-4 py-2 text-xs text-gray-400">
                                    … and {{ $product->listings->count() - 5 }} more.
                                </p>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- Right: listings count card --}}
                <div>
                    <div class="overflow-hidden rounded-lg bg-white shadow">
                        <div class="p-6 text-center">
                            <p class="text-3xl font-bold text-indigo-600">{{ $product->listings->count() }}</p>
                            <p class="mt-1 text-sm text-gray-500">Total Listings</p>
                            {{-- TODO: replace with route('product-listings.index', ['product_id' => $product->id]) once module exists --}}
                            <span class="mt-3 inline-block text-xs text-gray-400">Manage Listings (coming soon)</span>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</x-app-layout>
