<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-gray-800">Add Invoice</h2>
                <p class="mt-1 text-sm text-gray-500">
                    PO: <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                           class="text-indigo-600 hover:text-indigo-900">{{ $purchaseOrder->po_number }}</a>
                    &mdash; {{ $purchaseOrder->supplier->name }}
                </p>
            </div>
            <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
               class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                Cancel
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">

            @if($errors->any())
                <div class="mb-4 rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    <ul class="list-disc list-inside space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('purchase-orders.invoices.store', $purchaseOrder) }}">
                @csrf

                <input type="hidden" name="purchase_order_id" value="{{ $purchaseOrder->id }}" />

                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Invoice Details</h3>
                    </div>
                    <div class="space-y-5 px-6 py-5">

                        <div>
                            <label for="invoice_number" class="block text-sm font-medium text-gray-700">
                                Invoice Number <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   id="invoice_number"
                                   name="invoice_number"
                                   value="{{ old('invoice_number') }}"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('invoice_number')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-5">
                            <div>
                                <label for="invoice_date" class="block text-sm font-medium text-gray-700">
                                    Invoice Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       id="invoice_date"
                                       name="invoice_date"
                                       value="{{ old('invoice_date', now()->format('Y-m-d')) }}"
                                       required
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                @error('invoice_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="due_date" class="block text-sm font-medium text-gray-700">Due Date</label>
                                <input type="date"
                                       id="due_date"
                                       name="due_date"
                                       value="{{ old('due_date') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                                @error('due_date')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700">
                                Amount <span class="text-red-500">*</span>
                            </label>
                            <input type="number"
                                   id="amount"
                                   name="amount"
                                   value="{{ old('amount') }}"
                                   min="0"
                                   step="0.01"
                                   required
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="notes"
                                      name="notes"
                                      rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                    </div>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
                       class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit"
                            class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                        Add Invoice
                    </button>
                </div>

            </form>
        </div>
    </div>
</x-app-layout>
