<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $invoice->invoice_number }}</h2>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $invoice->status->color() }}-100 text-{{ $invoice->status->color() }}-700">
                    {{ $invoice->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                @if($invoice->status === \App\Enums\InvoiceStatus::Pending)
                    @can('approve', $invoice)
                        <form method="POST" action="{{ route('purchase-orders.invoices.approve', [$purchaseOrder, $invoice]) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                                Approve
                            </button>
                        </form>
                    @endcan
                @endif

                @if($invoice->status === \App\Enums\InvoiceStatus::Approved)
                    @can('markPaid', $invoice)
                        <form method="POST" action="{{ route('purchase-orders.invoices.markPaid', [$purchaseOrder, $invoice]) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                                Mark Paid
                            </button>
                        </form>
                    @endcan
                @endif

                @if($invoice->status !== \App\Enums\InvoiceStatus::Paid)
                    @can('delete', $invoice)
                        <form method="POST" action="{{ route('purchase-orders.invoices.destroy', [$purchaseOrder, $invoice]) }}"
                              onsubmit="return confirm('Delete this invoice?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                    @endcan
                @endif

                <a href="{{ route('purchase-orders.show', $invoice->purchaseOrder) }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                    Back to PO
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="rounded-md bg-green-100 px-4 py-3 text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-md bg-red-100 px-4 py-3 text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            {{-- Approval info --}}
            @if($invoice->approved_at && $invoice->approvedBy)
                <div class="rounded-md bg-blue-50 border border-blue-200 px-4 py-3 text-blue-800 text-sm">
                    Approved by <strong>{{ $invoice->approvedBy->name }}</strong>
                    on {{ $invoice->approved_at->format('M d, Y \a\t g:i A') }}
                </div>
            @endif

            {{-- Paid info --}}
            @if($invoice->paid_at)
                <div class="rounded-md bg-green-50 border border-green-200 px-4 py-3 text-green-800 text-sm">
                    Paid on {{ $invoice->paid_at->format('M d, Y \a\t g:i A') }}
                </div>
            @endif

            {{-- Invoice details card --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Invoice Details</h3>
                </div>
                <dl class="divide-y divide-gray-200">
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Invoice Number</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $invoice->invoice_number }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Purchase Order</dt>
                        <dd class="col-span-2 text-sm text-gray-900">
                            <a href="{{ route('purchase-orders.show', $invoice->purchaseOrder) }}"
                               class="text-indigo-600 hover:text-indigo-900">
                                {{ $invoice->purchaseOrder->po_number }}
                            </a>
                            &mdash; {{ $invoice->purchaseOrder->supplier->name }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Invoice Date</dt>
                        <dd class="col-span-2 text-sm text-gray-900">{{ $invoice->invoice_date->format('M d, Y') }}</dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Due Date</dt>
                        <dd class="col-span-2 text-sm text-gray-900">
                            {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '—' }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Amount</dt>
                        <dd class="col-span-2 text-sm font-semibold text-gray-900">
                            {{ number_format($invoice->amount, 2) }}
                        </dd>
                    </div>
                    <div class="grid grid-cols-3 gap-4 px-6 py-3">
                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                        <dd class="col-span-2 text-sm">
                            <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $invoice->status->color() }}-100 text-{{ $invoice->status->color() }}-700">
                                {{ $invoice->status->label() }}
                            </span>
                        </dd>
                    </div>
                    @if($invoice->notes)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $invoice->notes }}</dd>
                        </div>
                    @endif
                    @if($invoice->approved_at)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Approved By</dt>
                            <dd class="col-span-2 text-sm text-gray-900">
                                {{ $invoice->approvedBy->name ?? '—' }}
                                &mdash; {{ $invoice->approved_at->format('M d, Y') }}
                            </dd>
                        </div>
                    @endif
                    @if($invoice->paid_at)
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Paid At</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $invoice->paid_at->format('M d, Y \a\t g:i A') }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

        </div>
    </div>
</x-app-layout>
