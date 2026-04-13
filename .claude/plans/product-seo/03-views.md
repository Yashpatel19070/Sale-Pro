# ProductSEO Module — Views

## 1. Portal Layout — `<head>` Integration

File: `resources/views/layouts/portal.blade.php`

Add seotools directives inside `<head>`, **replacing or augmenting** any hardcoded `<title>` tag:

```blade
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    {!! SEOMeta::generate() !!}
    {!! OpenGraph::generate() !!}
    {!! TwitterCard::generate() !!}
    {!! JsonLd::generate() !!}

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
```

> **Note:** `SEOMeta::generate()` outputs `<title>`, `<meta name="description">`, and `<link rel="canonical">`. Remove any existing hardcoded `<title>` tag to avoid duplication.

---

## 2. Admin Form — Meta Fields

File: `resources/views/product_listings/_form.blade.php`

Add an **SEO section** at the bottom of the form, above the submit button:

```blade
{{-- SEO --}}
<div class="mt-8 border-t pt-6">
    <h3 class="text-sm font-semibold text-gray-700 mb-4">SEO (optional)</h3>

    <div class="space-y-4">
        {{-- Meta Title --}}
        <div>
            <label for="meta_title" class="block text-sm font-medium text-gray-700">
                Meta Title
                <span class="text-gray-400 font-normal">(max 160 chars)</span>
            </label>
            <input
                type="text"
                id="meta_title"
                name="meta_title"
                maxlength="160"
                value="{{ old('meta_title', $listing->meta_title ?? '') }}"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('meta_title') border-red-300 @enderror"
                placeholder="Defaults to listing title if left blank"
            >
            @error('meta_title')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Meta Description --}}
        <div>
            <label for="meta_description" class="block text-sm font-medium text-gray-700">
                Meta Description
                <span class="text-gray-400 font-normal">(max 320 chars)</span>
            </label>
            <textarea
                id="meta_description"
                name="meta_description"
                maxlength="320"
                rows="3"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm @error('meta_description') border-red-300 @enderror"
                placeholder="Defaults to listing title + SKU if left blank"
            >{{ old('meta_description', $listing->meta_description ?? '') }}</textarea>
            @error('meta_description')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>
    </div>
</div>
```

---

## 3. FormRequest Validation Updates

**Append** to the existing `rules()` array in both requests — do not replace existing rules.

`app/Http/Requests/ProductListing/StoreProductListingRequest.php`:

```php
public function rules(): array
{
    return [
        'product_id'      => ['required', 'integer', 'exists:products,id'],
        'title'           => ['required', 'string', 'max:200'],
        'visibility'      => ['required', 'string', Rule::in(array_column(ListingVisibility::cases(), 'value'))],
        'is_active'       => ['nullable', 'boolean'],
        // Added by product-seo:
        'meta_title'      => ['nullable', 'string', 'max:160'],
        'meta_description'=> ['nullable', 'string', 'max:320'],
    ];
}
```

`app/Http/Requests/ProductListing/UpdateProductListingRequest.php`:

```php
public function rules(): array
{
    return [
        // product_id intentionally absent — immutable after creation
        'title'           => ['required', 'string', 'max:200'],
        'visibility'      => ['required', 'string', Rule::in(array_column(ListingVisibility::cases(), 'value'))],
        'is_active'       => ['nullable', 'boolean'],
        // Added by product-seo:
        'meta_title'      => ['nullable', 'string', 'max:160'],
        'meta_description'=> ['nullable', 'string', 'max:320'],
    ];
}
```

## 4. Portal Listing View

**New file:** `resources/views/portal/shop/show.blade.php`

This is the portal-facing product listing detail page returned by `PortalListingController::show()`. Minimum structure:

```blade
@extends('layouts.portal')

@section('content')
    <div class="max-w-4xl mx-auto py-8 px-4">
        <h1 class="text-2xl font-bold text-gray-900">{{ $listing->title }}</h1>

        <div class="mt-4 text-gray-600">
            <p>SKU: {{ $listing->product->sku }}</p>
            <p>Price: ${{ number_format($listing->product->sale_price ?? $listing->product->regular_price, 2) }}</p>
        </div>
    </div>
@endsection
```

> Full view design (layout, components, styling) is out of scope for product-seo — this is the minimal shell needed for SEO tags to render. Expand when the storefront UI module is built.

---

## Key Rules

- `SEOMeta::generate()` must not coexist with a hardcoded `<title>` — remove the old one
- All four directives go in `<head>` on the portal layout only (not admin layout)
- Meta fields are optional — no `required` validation
- `old()` helper used for form repopulation on validation failure

---

## Checklist
- [ ] Portal layout `<head>` has all four seotools directives
- [ ] Hardcoded `<title>` tag removed from portal layout
- [ ] Admin `_form.blade.php` has SEO section with meta_title + meta_description fields
- [ ] `StoreProductListingRequest::rules()` has meta_title + meta_description appended (not replaced)
- [ ] `UpdateProductListingRequest::rules()` has meta_title + meta_description appended (not replaced)
- [ ] `meta_title` and `meta_description` in `$fillable` on `ProductListing` model
- [ ] `resources/views/portal/shop/show.blade.php` created (minimal shell — extends portal layout)
