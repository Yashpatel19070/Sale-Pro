<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $purchaseOrder->po_number }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @media print {
            .no-print { display: none !important; }
            body { font-size: 12px; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="p-8 bg-white text-gray-900">

    {{-- Print button --}}
    <div class="no-print mb-6 flex justify-end gap-3">
        <button onclick="window.print()"
                class="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
            Print / Save as PDF
        </button>
        <a href="{{ route('purchase-orders.show', $purchaseOrder) }}"
           class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
            Back to PO
        </a>
    </div>

    {{-- Header --}}
    <div class="mb-8 flex items-start justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Purchase Order</h1>
            <p class="mt-1 text-lg font-semibold text-gray-700">{{ $purchaseOrder->po_number }}</p>
            <p class="mt-1 text-sm text-gray-500">
                Date: {{ $purchaseOrder->created_at->format('M d, Y') }}
            </p>
            @if($purchaseOrder->expected_delivery_date)
                <p class="text-sm text-gray-500">
                    Expected Delivery: {{ $purchaseOrder->expected_delivery_date->format('M d, Y') }}
                </p>
            @endif
        </div>
        <div class="text-right">
            <span class="inline-block px-3 py-1 text-sm font-medium rounded-full bg-{{ $purchaseOrder->status->color() }}-100 text-{{ $purchaseOrder->status->color() }}-700">
                {{ $purchaseOrder->status->label() }}
            </span>
            @if($purchaseOrder->approved_at && $purchaseOrder->approvedBy)
                <p class="mt-2 text-xs text-gray-500">
                    Approved by {{ $purchaseOrder->approvedBy->name }}<br>
                    {{ $purchaseOrder->approved_at->format('M d, Y') }}
                </p>
            @endif
        </div>
    </div>

    {{-- Supplier + Created by --}}
    <div class="mb-8 grid grid-cols-2 gap-8">
        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Supplier</h2>
            <p class="text-sm font-medium text-gray-900">{{ $purchaseOrder->supplier->name }}</p>
            @if($purchaseOrder->supplier->contact_name)
                <p class="text-sm text-gray-600">{{ $purchaseOrder->supplier->contact_name }}</p>
            @endif
            @if($purchaseOrder->supplier->email)
                <p class="text-sm text-gray-600">{{ $purchaseOrder->supplier->email }}</p>
            @endif
            @if($purchaseOrder->supplier->phone)
                <p class="text-sm text-gray-600">{{ $purchaseOrder->supplier->phone }}</p>
            @endif
        </div>
        <div>
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Prepared By</h2>
            <p class="text-sm font-medium text-gray-900">{{ $purchaseOrder->createdBy->name ?? '—' }}</p>
            <p class="text-sm text-gray-600">{{ $purchaseOrder->created_at->format('M d, Y') }}</p>
        </div>
    </div>

    {{-- Line Items --}}
    <table class="mb-6 w-full border-collapse text-sm">
        <thead>
            <tr class="border-b-2 border-gray-300">
                <th class="pb-2 text-left font-semibold text-gray-700">Product</th>
                <th class="pb-2 text-left font-semibold text-gray-700">Description</th>
                <th class="pb-2 text-right font-semibold text-gray-700">Stock @ Order</th>
                <th class="pb-2 text-right font-semibold text-gray-700">Qty Ordered</th>
                <th class="pb-2 text-right font-semibold text-gray-700">Unit Cost</th>
                <th class="pb-2 text-right font-semibold text-gray-700">Tax %</th>
                <th class="pb-2 text-right font-semibold text-gray-700">Line Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($purchaseOrder->lines as $line)
                <tr class="border-b border-gray-200">
                    <td class="py-2 text-gray-900">{{ $line->product->name ?? '—' }}</td>
                    <td class="py-2 text-gray-600">{{ $line->description ?? '—' }}</td>
                    <td class="py-2 text-right text-gray-600">{{ number_format($line->qty_on_hand_snapshot, 2) }}</td>
                    <td class="py-2 text-right text-gray-900">{{ number_format($line->qty_ordered, 2) }}</td>
                    <td class="py-2 text-right text-gray-900">{{ number_format($line->unit_cost, 2) }}</td>
                    <td class="py-2 text-right text-gray-900">{{ number_format($line->tax_rate, 2) }}%</td>
                    <td class="py-2 text-right font-medium text-gray-900">{{ number_format($line->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="border-t border-gray-200">
                <td colspan="6" class="py-2 text-right text-gray-600">Subtotal</td>
                <td class="py-2 text-right font-medium text-gray-900">{{ number_format($purchaseOrder->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="6" class="py-2 text-right text-gray-600">Tax Total</td>
                <td class="py-2 text-right font-medium text-gray-900">{{ number_format($purchaseOrder->tax_total, 2) }}</td>
            </tr>
            <tr class="border-t-2 border-gray-900">
                <td colspan="6" class="py-2 text-right font-bold text-gray-900">Grand Total</td>
                <td class="py-2 text-right font-bold text-gray-900">{{ number_format($purchaseOrder->grand_total, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    {{-- Notes --}}
    @if($purchaseOrder->notes)
        <div class="mt-6 rounded-md border border-gray-200 p-4">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wider text-gray-500">Notes</h2>
            <p class="text-sm text-gray-700">{{ $purchaseOrder->notes }}</p>
        </div>
    @endif

    <script>window.onload = () => window.print();</script>
</body>
</html>
