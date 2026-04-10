{{-- $department (optional) = existing model on edit, null on create --}}
<div class="space-y-5">

    <div>
        <x-input-label for="name" value="Name *" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                      value="{{ old('name', $department->name ?? '') }}" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="code" value="Code *" />
        <x-text-input id="code" name="code" type="text" class="mt-1 block w-40 uppercase"
                      maxlength="20"
                      value="{{ old('code', $department->code ?? '') }}"
                      placeholder="e.g. SALES" required />
        <p class="mt-1 text-xs text-gray-500">Letters only, max 20 chars (e.g. SALES, MKT, OPS).</p>
        <x-input-error :messages="$errors->get('code')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="description" value="Description" />
        <textarea id="description" name="description" rows="3"
                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">{{ old('description', $department->description ?? '') }}</textarea>
        <x-input-error :messages="$errors->get('description')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="manager_id" value="Manager" />
        <select id="manager_id" name="manager_id"
                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
            <option value="">— None —</option>
            @foreach ($managers as $user)
                <option value="{{ $user->id }}"
                    @selected(old('manager_id', $department->manager_id ?? null) == $user->id)>
                    {{ $user->name }}
                </option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('manager_id')" class="mt-1" />
    </div>

    <div class="flex items-center gap-2">
        <input type="hidden" name="is_active" value="0" />
        <input type="checkbox" id="is_active" name="is_active" value="1"
               class="rounded border-gray-300 text-indigo-600 shadow-sm"
               @checked(old('is_active', $department->is_active ?? true)) />
        <x-input-label for="is_active" value="Active" class="mb-0" />
    </div>

</div>
