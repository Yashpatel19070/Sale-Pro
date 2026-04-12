# ProductCategory Module — Views

## Directory
`resources/views/product_categories/`

## Files
- `index.blade.php`
- `show.blade.php`
- `create.blade.php`
- `edit.blade.php`
- `_form.blade.php`
- `_category-node.blade.php` ← recursive partial for tree rendering

---

## index.blade.php

Extend admin layout. Show:
- Page title "Product Categories" + "Add Category" button (gated: `@can('create', ProductCategory::class)`)
- Search input (GET, `name="search"`, `value="{{ request('search') }}"`)
- Active filter select: All / Active / Inactive (`name="active"`)
- **Tree table** — not a flat list. Renders root categories, then recursively indents children
- Include `_category-node.blade.php` for each root category
- Flash success/error messages
- Empty state when no categories

```blade
@foreach ($categories as $category)
    @include('product_categories._category-node', ['category' => $category, 'depth' => 0])
@endforeach
```

---

## _category-node.blade.php (recursive partial)

Renders one row + recurses into children:

```blade
<tr>
    <td>
        {{-- Indent based on depth --}}
        @if ($depth > 0)
            <span style="padding-left: {{ $depth * 1.5 }}rem">
                @if ($depth > 1)— @endif
            </span>
        @endif
        {{ $category->name }}
    </td>
    <td>{{ Str::limit($category->description ?? '—', 60) }}</td>
    <td>
        @if ($category->is_active)
            <span class="badge-green">Active</span>
        @else
            <span class="badge-red">Inactive</span>
        @endif
    </td>
    <td>
        <a href="{{ route('product-categories.show', $category) }}">View</a>
        @can('update', $category)
            <a href="{{ route('product-categories.edit', $category) }}">Edit</a>
        @endcan
        @can('delete', $category)
            <form method="POST" action="{{ route('product-categories.destroy', $category) }}"
                  onsubmit="return confirm('Delete {{ $category->name }}?')">
                @csrf @method('DELETE')
                <button type="submit">Delete</button>
            </form>
        @endcan
    </td>
</tr>

{{-- Recurse into children --}}
@foreach ($category->children->sortBy('name') as $child)
    @include('product_categories._category-node', ['category' => $child, 'depth' => $depth + 1])
@endforeach
```

**Note:** This works because `tree()` eager-loads `with('children')` which Laravel resolves
recursively through the already-loaded collection — no additional DB queries per level.

---

## show.blade.php

Extend admin layout. Show:
- Category name as heading
- Breadcrumb: parent chain (if has parent, show parent link → current)
- Detail rows: Parent, Description, Status badge, Created At, Updated At
- **Children section**: list direct children with links (if any)
- "Edit" button (gated by `@can('update', $category)`)
- "Delete" button/form (gated by `@can('delete', $category)`)
- "Back to Categories" link

```blade
{{-- Parent breadcrumb --}}
@if ($category->parent)
    <a href="{{ route('product-categories.show', $category->parent) }}">
        {{ $category->parent->name }}
    </a>
    →
@endif
{{ $category->name }}
```

---

## create.blade.php

Extend admin layout. Show:
- Page title "New Category"
- Form: `action="{{ route('product-categories.store') }}"` method POST
- Include `_form` partial with `$category = null`
- Back link

---

## edit.blade.php

Extend admin layout. Show:
- Page title "Edit: {{ $category->name }}"
- Form: `action="{{ route('product-categories.update', $category) }}"` with `@method('PUT')`
- Include `_form` partial with `$category`
- Back link

---

## _form.blade.php

Shared form partial:

```blade
{{-- Parent Category --}}
<div>
    <label for="parent_id">Parent Category</label>
    <select id="parent_id" name="parent_id">
        <option value="">— None (root category) —</option>
        @foreach ($flatTree as $item)
            <option
                value="{{ $item->id }}"
                {{ old('parent_id', $category->parent_id ?? '') == $item->id ? 'selected' : '' }}
            >
                {{ str_repeat('— ', $item->depth) }}{{ $item->name }}
            </option>
        @endforeach
    </select>
    @error('parent_id') <p>{{ $message }}</p> @enderror
</div>

{{-- Name --}}
<div>
    <label for="name">Name <span>*</span></label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $category->name ?? '') }}"
        required
        maxlength="100"
    >
    @error('name') <p>{{ $message }}</p> @enderror
</div>

{{-- Description --}}
<div>
    <label for="description">Description</label>
    <textarea id="description" name="description" rows="3">
        {{ old('description', $category->description ?? '') }}
    </textarea>
    @error('description') <p>{{ $message }}</p> @enderror
</div>

{{-- Active --}}
<div>
    <label>
        <input
            type="checkbox"
            name="is_active"
            value="1"
            {{ old('is_active', $category->is_active ?? true) ? 'checked' : '' }}
        >
        Active
    </label>
</div>

<button type="submit">Save Category</button>
```

The `$flatTree` variable is passed from the controller — depth-first flat list with
`depth` attribute set. The dropdown shows:
```
— None (root category) —
Electronics
— Phones
—— Smartphones
— Laptops
Clothing
— Men
— Women
```

---

## Controller Changes for Views

`create()` and `edit()` must pass `$flatTree`:

```php
public function create(): View
{
    $this->authorize('create', ProductCategory::class);

    return view('product_categories.create', [
        'flatTree' => $this->service->flatTree(),
        'category' => null,
    ]);
}

public function edit(ProductCategory $productCategory): View
{
    $this->authorize('update', $productCategory);

    // Load descendants so flatTree can exclude them from dropdown
    $productCategory->load('children.children.children');

    $forbiddenIds = array_merge(
        [$productCategory->id],
        $productCategory->descendantIds()
    );

    return view('product_categories.edit', [
        'category' => $productCategory,
        'flatTree' => collect($this->service->flatTree())
            ->reject(fn ($item) => in_array($item->id, $forbiddenIds, true))
            ->values()
            ->all(),
    ]);
}
```

The edit dropdown **excludes self + descendants** so circular references are impossible in the UI.

---

## Checklist
- [ ] `index.blade.php` renders tree using recursive `_category-node` partial
- [ ] `_category-node.blade.php` indents by `$depth * 1.5rem` and recurses into children
- [ ] `show.blade.php` shows parent breadcrumb and children list
- [ ] `_form.blade.php` parent dropdown uses `$flatTree` with `str_repeat('— ', $depth)`
- [ ] Edit view excludes self + descendants from parent dropdown
- [ ] `is_active` checkbox uses `old()` with default true on create
- [ ] All edit/delete elements wrapped in `@can` directives
- [ ] Flash messages displayed
