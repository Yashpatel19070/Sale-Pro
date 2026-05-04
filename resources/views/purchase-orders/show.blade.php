<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <h2 class="text-xl font-semibold leading-tight text-gray-800">{{ $purchaseOrder->po_number }}</h2>
                <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $purchaseOrder->status->color() }}-100 text-{{ $purchaseOrder->status->color() }}-700">
                    {{ $purchaseOrder->status->label() }}
                </span>
            </div>
            <div class="flex items-center gap-2">
                {{-- Draft actions --}}
                @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Draft)
                    @can('update', $purchaseOrder)
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Edit</a>
                    @endcan
                    @can('submit', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.submit', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Submit</button>
                        </form>
                    @endcan
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}"
                              onsubmit="return confirm('Cancel this purchase order?')">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Cancel</button>
                        </form>
                    @endcan

                {{-- Pending Approval actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PendingApproval)
                    @can('approve', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.approve', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">Approve</button>
                        </form>
                    @endcan

                {{-- Rejected actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Rejected)
                    @can('update', $purchaseOrder)
                        <a href="{{ route('purchase-orders.edit', $purchaseOrder) }}"
                           class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Edit</a>
                    @endcan
                    @can('submit', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.submit', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">Resubmit</button>
                        </form>
                    @endcan
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}"
                              onsubmit="return confirm('Cancel this purchase order?')">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Cancel</button>
                        </form>
                    @endcan

                {{-- Approved actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Approved)
                    @can('markOnTheWay', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.markOnTheWay', $purchaseOrder) }}">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">Mark On The Way</button>
                        </form>
                    @endcan
                    @can('create', App\Models\GoodsReceipt::class)
                        <a href="{{ route('purchase-orders.goods-receipts.create', $purchaseOrder) }}"
                           class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Receive Goods</a>
                    @endcan
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}"
                              onsubmit="return confirm('Cancel this purchase order?')">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Cancel</button>
                        </form>
                    @endcan

                {{-- On The Way actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::OnTheWay)
                    @can('create', App\Models\GoodsReceipt::class)
                        <a href="{{ route('purchase-orders.goods-receipts.create', $purchaseOrder) }}"
                           class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Receive Goods</a>
                    @endcan
                    @can('cancel', $purchaseOrder)
                        <form method="POST" action="{{ route('purchase-orders.cancel', $purchaseOrder) }}"
                              onsubmit="return confirm('Cancel this purchase order?')">
                            @csrf
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Cancel</button>
                        </form>
                    @endcan

                {{-- Partially Received actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PartiallyReceived)
                    @can('create', App\Models\GoodsReceipt::class)
                        <a href="{{ route('purchase-orders.goods-receipts.create', $purchaseOrder) }}"
                           class="rounded-md bg-teal-600 px-4 py-2 text-sm font-medium text-white hover:bg-teal-700">Receive Goods</a>
                    @endcan

                {{-- Received actions --}}
                @elseif($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Received)
                    @can('create', App\Models\Invoice::class)
                        <a href="{{ route('purchase-orders.invoices.create', $purchaseOrder) }}"
                           class="rounded-md bg-cyan-600 px-4 py-2 text-sm font-medium text-white hover:bg-cyan-700">Add Invoice</a>
                    @endcan
                @endif

                <a href="{{ route('purchase-orders.print', $purchaseOrder) }}"
                   target="_blank"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Print</a>

                <a href="{{ route('purchase-orders.index') }}"
                   class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">Back</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

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

            {{-- Rejection reason alert --}}
            @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::Rejected && $purchaseOrder->rejection_reason)
                <div class="rounded-md bg-red-50 border border-red-200 px-4 py-3 text-red-800">
                    <strong class="font-medium">Rejection Reason:</strong> {{ $purchaseOrder->rejection_reason }}
                </div>
            @endif

            {{-- Rejection form (shown for pending_approval to allow reject action) --}}
            @if($purchaseOrder->status === \App\Enums\PurchaseOrderStatus::PendingApproval)
                @can('approve', $purchaseOrder)
                    <div class="rounded-lg bg-white shadow p-4">
                        <form method="POST" action="{{ route('purchase-orders.reject', $purchaseOrder) }}"
                              class="flex items-end gap-3"
                              onsubmit="return confirm('Reject this purchase order?')">
                            @csrf
                            <div class="flex-1">
                                <label for="rejection_reason" class="block text-sm font-medium text-gray-700">Rejection Reason</label>
                                <input type="text"
                                       id="rejection_reason"
                                       name="rejection_reason"
                                       placeholder="Enter reason for rejection..."
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
                            </div>
                            <button type="submit"
                                    class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white hover:bg-red-700">Reject</button>
                        </form>
                    </div>
                @endcan
            @endif

            {{-- Approval info --}}
            @if($purchaseOrder->approved_at && $purchaseOrder->approvedBy)
                <div class="rounded-md bg-blue-50 border border-blue-200 px-4 py-3 text-blue-800 text-sm">
                    Approved by <strong>{{ $purchaseOrder->approvedBy->name }}</strong>
                    on {{ $purchaseOrder->approved_at->format('M d, Y \a\t g:i A') }}
                </div>
            @endif

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

                {{-- Supplier Info --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Supplier</h3>
                    </div>
                    <dl class="divide-y divide-gray-200">
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Name</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->supplier->name }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Code</dt>
                            <dd class="col-span-2 text-sm text-gray-900">—</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Contact</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->supplier->contact_name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Email</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->supplier->email ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Phone</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->supplier->phone ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Payment Terms</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->supplier->payment_terms ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>

                {{-- PO Details --}}
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">PO Details</h3>
                    </div>
                    <dl class="divide-y divide-gray-200">
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">PO Number</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->po_number }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Created By</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->createdBy->name ?? '—' }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Created At</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->created_at->format('M d, Y') }}</dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Expected Delivery</dt>
                            <dd class="col-span-2 text-sm text-gray-900">
                                {{ $purchaseOrder->expected_delivery_date ? $purchaseOrder->expected_delivery_date->format('M d, Y') : '—' }}
                            </dd>
                        </div>
                        <div class="grid grid-cols-3 gap-4 px-6 py-3">
                            <dt class="text-sm font-medium text-gray-500">Notes</dt>
                            <dd class="col-span-2 text-sm text-gray-900">{{ $purchaseOrder->notes ?? '—' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Line Items --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Line Items</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Description</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Stock @ Order</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty Ordered</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Qty Received</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Remaining</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Unit Cost</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Tax Rate</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @foreach($purchaseOrder->lines as $line)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $line->product->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-600">{{ $line->description ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->qty_on_hand_snapshot, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->qty_ordered, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->qty_received, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->qty_ordered - $line->qty_received, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->unit_cost, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($line->tax_rate, 2) }}%</td>
                                <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">{{ number_format($line->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50">
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-right text-sm font-medium text-gray-700">Subtotal</td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">{{ number_format($purchaseOrder->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="8" class="px-4 py-3 text-right text-sm font-medium text-gray-700">Tax Total</td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-900">{{ number_format($purchaseOrder->tax_total, 2) }}</td>
                        </tr>
                        <tr class="border-t-2 border-gray-300">
                            <td colspan="8" class="px-4 py-3 text-right text-sm font-bold text-gray-700">Grand Total</td>
                            <td class="px-4 py-3 text-right text-sm font-bold text-gray-900">{{ number_format($purchaseOrder->grand_total, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Goods Receipts --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Goods Receipts</h3>
                    @if(in_array($purchaseOrder->status, [
                        \App\Enums\PurchaseOrderStatus::Approved,
                        \App\Enums\PurchaseOrderStatus::OnTheWay,
                        \App\Enums\PurchaseOrderStatus::PartiallyReceived,
                    ]))
                        @can('create', App\Models\GoodsReceipt::class)
                            <a href="{{ route('purchase-orders.goods-receipts.create', $purchaseOrder) }}"
                               class="rounded-md bg-teal-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-700">
                                Record Goods Receipt
                            </a>
                        @endcan
                    @endif
                </div>

                @if($purchaseOrder->goodsReceipts->isEmpty())
                    <div class="py-10 text-center text-sm text-gray-500">No goods receipts recorded yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">GRN Number</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Received By</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($purchaseOrder->goodsReceipts as $grn)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $grn->grn_number }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $grn->received_date->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $grn->receivedBy->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $grn->status->color() }}-100 text-{{ $grn->status->color() }}-700">
                                            {{ $grn->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('purchase-orders.goods-receipts.show', [$purchaseOrder, $grn]) }}"
                                               class="text-indigo-600 hover:text-indigo-900">View</a>

                                            @if($grn->status === \App\Enums\GoodsReceiptStatus::Draft)
                                                @can('update', $grn)
                                                    <a href="{{ route('purchase-orders.goods-receipts.edit', [$purchaseOrder, $grn]) }}"
                                                       class="text-gray-600 hover:text-gray-900">Edit</a>
                                                @endcan
                                                @can('update', $grn)
                                                    <form method="POST" action="{{ route('purchase-orders.goods-receipts.complete', [$purchaseOrder, $grn]) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-green-600 hover:text-green-900">Complete</button>
                                                    </form>
                                                @endcan
                                                @can('delete', $grn)
                                                    <form method="POST" action="{{ route('purchase-orders.goods-receipts.destroy', [$purchaseOrder, $grn]) }}"
                                                          onsubmit="return confirm('Delete this goods receipt?')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            {{-- Invoices --}}
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Invoices</h3>
                    @if(in_array($purchaseOrder->status, [
                        \App\Enums\PurchaseOrderStatus::Received,
                        \App\Enums\PurchaseOrderStatus::Invoiced,
                    ]))
                        @can('create', App\Models\Invoice::class)
                            <a href="{{ route('purchase-orders.invoices.create', $purchaseOrder) }}"
                               class="rounded-md bg-cyan-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-cyan-700">
                                Add Invoice
                            </a>
                        @endcan
                    @endif
                </div>

                @if($purchaseOrder->invoices->isEmpty())
                    <div class="py-10 text-center text-sm text-gray-500">No invoices recorded yet.</div>
                @else
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Invoice Number</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Date</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Due Date</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Amount</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            @foreach($purchaseOrder->invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $invoice->invoice_number }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $invoice->invoice_date->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-900">
                                        {{ $invoice->due_date ? $invoice->due_date->format('M d, Y') : '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900">{{ number_format($invoice->amount, 2) }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="px-2 py-1 text-xs font-medium rounded-full bg-{{ $invoice->status->color() }}-100 text-{{ $invoice->status->color() }}-700">
                                            {{ $invoice->status->label() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm">
                                        <div class="flex items-center gap-3">
                                            <a href="{{ route('purchase-orders.invoices.show', [$purchaseOrder, $invoice]) }}"
                                               class="text-indigo-600 hover:text-indigo-900">View</a>

                                            @if($invoice->status === \App\Enums\InvoiceStatus::Pending)
                                                @can('approve', $invoice)
                                                    <form method="POST" action="{{ route('purchase-orders.invoices.approve', [$purchaseOrder, $invoice]) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-green-600 hover:text-green-900">Approve</button>
                                                    </form>
                                                @endcan
                                            @endif

                                            @if($invoice->status === \App\Enums\InvoiceStatus::Approved)
                                                @can('markPaid', $invoice)
                                                    <form method="POST" action="{{ route('purchase-orders.invoices.markPaid', [$purchaseOrder, $invoice]) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                class="text-blue-600 hover:text-blue-900">Mark Paid</button>
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
                                                                class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endcan
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
