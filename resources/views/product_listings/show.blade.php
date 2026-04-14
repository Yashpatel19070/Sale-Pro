<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $listing->title }}</h2>
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $listing->visibility->badgeClass() }}">
                    {{ $listing->visibility->label() }}
                </span>
                @if ($listing->is_active)
                    <span class="inline-flex items-center rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800">Active</span>
                @else
                    <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">Inactive</span>
                @endif
            </div>
            <div class="flex items-center gap-2">
                @can('update', $listing)
                    <a href="{{ route('product-listings.edit', $listing) }}"
                       class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                        Edit
                    </a>
                    <form method="POST" action="{{ route('product-listings.toggle-visibility', $listing) }}">
                        @csrf
                        <button class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">
                            {{ $listing->visibility === App\Enums\ListingVisibility::Public ? 'Set Draft' : 'Set Public' }}
                        </button>
                    </form>
                @endcan
                @can('delete', $listing)
                    <form method="POST" action="{{ route('product-listings.destroy', $listing) }}"
                          onsubmit="return confirm('Delete ' + {{ Js::from($listing->title) }} + '?')">
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
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8 space-y-6">

            @include('partials.flash')

            <div class="mb-2">
                <a href="{{ route('product-listings.index') }}"
                   class="text-sm text-indigo-600 hover:underline">← Back to Listings</a>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="p-6">
                    <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Product</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                <a href="{{ route('products.show', $listing->product) }}"
                                   class="text-indigo-600 hover:underline">
                                    {{ $listing->product->name }}
                                </a>
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">SKU</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $listing->product->sku }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Category</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $listing->product->category?->name ?? '—' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Slug</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $listing->slug }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Visibility</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $listing->visibility->label() }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Status</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $listing->is_active ? 'Active' : 'Inactive' }}</dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Regular Price</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($listing->product->sale_price)
                                    <span class="line-through text-gray-400">${{ $listing->product->regular_price }}</span>
                                @else
                                    ${{ $listing->product->regular_price }}
                                @endif
                            </dd>
                        </div>

                        <div>
                            <dt class="text-xs font-medium uppercase text-gray-500">Sale Price</dt>
                            <dd class="mt-1 text-sm text-gray-900">
                                @if ($listing->product->sale_price)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-sm font-medium text-green-800">
                                        On Sale: ${{ $listing->product->sale_price }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-4 border-t border-gray-100 pt-4 flex gap-6 text-xs text-gray-400">
                        <span>Created {{ $listing->created_at->format('M d, Y') }}</span>
                        <span>Updated {{ $listing->updated_at->format('M d, Y') }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
