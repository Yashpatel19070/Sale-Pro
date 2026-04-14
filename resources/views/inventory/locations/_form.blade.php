{{--
  Shared form partial — included by create.blade.php and edit.blade.php.
  $location is null on create, an InventoryLocation model on edit.
--}}

@if($errors->any())
    <div class="mb-4 rounded-md bg-red-50 px-4 py-3">
        <ul class="list-disc pl-5 text-sm text-red-700">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Code — shown on create only; read-only display on edit --}}
@if($location === null)
    <div class="mb-4">
        <label for="code" class="block text-sm font-medium text-gray-700">
            Location Code <span class="text-red-500">*</span>
        </label>
        <input type="text"
               id="code"
               name="code"
               value="{{ old('code') }}"
               maxlength="20"
               placeholder="e.g. L1, L99, ZONE-A"
               class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 uppercase
                      @error('code') border-red-500 @enderror" />
        @error('code')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
        <p class="mt-1 text-xs text-gray-500">Letters, numbers, hyphens, underscores only. Cannot be changed after creation.</p>
    </div>
@else
    <div class="mb-4">
        <label class="block text-sm font-medium text-gray-700">Location Code</label>
        <p class="mt-1 font-mono text-sm font-semibold text-gray-900">{{ $location->code }}</p>
        <p class="text-xs text-gray-500">Code cannot be changed after creation.</p>
    </div>
@endif

{{-- Name --}}
<div class="mb-4">
    <label for="name" class="block text-sm font-medium text-gray-700">
        Name <span class="text-red-500">*</span>
    </label>
    <input type="text"
           id="name"
           name="name"
           value="{{ old('name', $location?->name) }}"
           maxlength="100"
           placeholder="e.g. Shelf L1 Row A"
           class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500
                  @error('name') border-red-500 @enderror" />
    @error('name')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

{{-- Description --}}
<div class="mb-4">
    <label for="description" class="block text-sm font-medium text-gray-700">Description</label>
    <textarea id="description"
              name="description"
              rows="3"
              maxlength="1000"
              placeholder="Optional notes about this location..."
              class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500
                     @error('description') border-red-500 @enderror">{{ old('description', $location?->description) }}</textarea>
    @error('description')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>
