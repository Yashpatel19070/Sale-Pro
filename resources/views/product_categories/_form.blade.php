<div class="space-y-5">

    {{-- Parent Category --}}
    <div>
        <x-input-label for="parent_id" value="Parent Category" />
        <select id="parent_id" name="parent_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">— None (root category) —</option>
            @foreach ($flatTree as $item)
                <option value="{{ $item->id }}"
                        @selected(old('parent_id', $category->parent_id ?? '') == $item->id)>
                    {{ str_repeat('— ', $item->depth) }}{{ $item->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('parent_id')" class="mt-1" />
    </div>

    {{-- Name --}}
    <div>
        <x-input-label for="name" value="Name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $category->name ?? '') }}"
                      required maxlength="100" autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    {{-- Description --}}
    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $category->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    {{-- Active --}}
    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" id="is_active" name="is_active" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm"
               @checked(old('is_active', $category->is_active ?? true)) />
        <x-input-label for="is_active" value="Active" class="mb-0" />
    </div>

    <div class="flex items-center gap-3 pt-1">
        <x-primary-button>Save Category</x-primary-button>
        <a href="{{ route('product-categories.index') }}"
           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            Cancel
        </a>
    </div>

</div>
