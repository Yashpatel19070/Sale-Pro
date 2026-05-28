<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt — {{ $receipt['order']['number'] }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="mx-auto max-w-3xl bg-white p-8 print:p-0 print:shadow-none">

        {{-- Shop letterhead --}}
        @if($receipt['shop']['has_letterhead'])
            <div data-testid="shop-letterhead" class="text-center border-b border-gray-200 pb-4">
                <h1 class="text-xl font-bold text-gray-800">{{ $receipt['shop']['name'] }}</h1>
                @if($receipt['shop']['address_line1'])
                    <p class="text-sm text-gray-600">{{ $receipt['shop']['address_line1'] }}</p>
                @endif
                @if($receipt['shop']['city'] || $receipt['shop']['state'] || $receipt['shop']['postal_code'])
                    <p class="text-sm text-gray-600">{{ $receipt['shop']['city'] }}, {{ $receipt['shop']['state'] }} {{ $receipt['shop']['postal_code'] }}</p>
                @endif
                @if($receipt['shop']['email'] || $receipt['shop']['phone'])
                    <p class="text-sm text-gray-600">{{ $receipt['shop']['email'] }} · {{ $receipt['shop']['phone'] }}</p>
                @endif
            </div>
        @endif

        {{-- Receipt header --}}
        <div class="mt-6 flex items-start justify-between">
            <div>
                <h2 class="text-2xl font-bold uppercase tracking-wide text-gray-800">Receipt</h2>
                <p class="mt-1 text-sm text-gray-600">Order {{ $receipt['order']['number'] }}</p>
                <p class="text-sm text-gray-600">{{ $receipt['order']['created_at']->format('M j, Y g:i A') }}</p>
            </div>
            <span class="rounded bg-gray-100 px-3 py-1 text-xs font-semibold uppercase">{{ $receipt['order']['status_label'] }}</span>
        </div>

        {{-- Customer --}}
        <div class="mt-6">
            <h3 class="text-xs font-semibold uppercase text-gray-500">Customer</h3>
            <p class="font-medium text-gray-800">{{ $receipt['customer']['name'] }}</p>
            @if($receipt['customer']['email'])<p class="text-sm text-gray-600">{{ $receipt['customer']['email'] }}</p>@endif
            @if($receipt['customer']['phone'])<p class="text-sm text-gray-600">{{ $receipt['customer']['phone'] }}</p>@endif
        </div>

        {{-- Items --}}
        <div class="mt-6">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-xs uppercase text-gray-500">
                        <th class="pb-2">Description</th>
                        <th class="pb-2 text-right">Unit Price</th>
                        <th class="pb-2 text-right">Tax</th>
                        <th class="pb-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="text-sm">
                    @foreach($receipt['lines'] as $line)
                        <tr class="border-b border-gray-100">
                            <td class="py-2 font-medium">{{ $line['product_name'] }}</td>
                            <td class="py-2 text-right">${{ number_format($line['unit_price'], 2) }}</td>
                            <td class="py-2 text-right">${{ number_format($line['tax_amount'], 2) }}</td>
                            <td class="py-2 text-right font-semibold">${{ number_format($line['line_total'], 2) }}</td>
                        </tr>
                        @foreach($line['fees'] as $fee)
                            <tr class="border-b border-gray-100 text-xs text-gray-600">
                                <td class="py-1 pl-6">└ {{ $fee['name'] }}</td>
                                <td class="py-1 text-right">${{ number_format($fee['amount'], 2) }}</td>
                                <td class="py-1 text-right">${{ number_format($fee['tax_amount'], 2) }}</td>
                                <td class="py-1 text-right font-semibold">${{ number_format($fee['fee_total'], 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Totals --}}
        <div class="mt-4 flex justify-end">
            <div class="w-80 space-y-1 text-sm">
                <div class="flex justify-between"><span>Line totals:</span><span>${{ number_format($receipt['totals']['line_totals'], 2) }}</span></div>
                <div class="flex justify-between"><span>Fee totals:</span><span>${{ number_format($receipt['totals']['fee_totals'], 2) }}</span></div>
                <div class="flex justify-between"><span>Shipping:</span><span>${{ number_format($receipt['totals']['shipping'], 2) }}</span></div>
                <div class="mt-2 flex justify-between border-t-2 border-gray-300 pt-2 text-base font-bold">
                    <span>GRAND TOTAL:</span><span>${{ number_format($receipt['totals']['grand_total'], 2) }}</span>
                </div>
            </div>
        </div>

        {{-- Payment --}}
        @if(count($receipt['payments']) > 0)
            <div class="mt-6">
                <h3 class="text-xs font-semibold uppercase text-gray-500">Payment</h3>
                @foreach($receipt['payments'] as $p)
                    <p class="text-sm text-gray-700">
                        {{ $p['method_label'] }} · ${{ number_format($p['amount'], 2) }} · {{ $p['status_label'] }}
                        @if($p['cash_received_at']) · {{ $p['cash_received_at']->format('M j, Y g:i A') }}@endif
                    </p>
                @endforeach
            </div>
        @endif

        {{-- Footer --}}
        <div class="mt-8 border-t border-gray-200 pt-4 text-center text-sm text-gray-600">
            <p>{{ $receipt['footer']['thank_you'] }}</p>
            @if($receipt['footer']['support_email'])
                <p class="mt-1 text-xs">For support: {{ $receipt['footer']['support_email'] }}</p>
            @endif
        </div>

        {{-- Print button (hidden on print) --}}
        <div class="no-print mt-6 text-center">
            <button onclick="window.print()" class="rounded-md bg-indigo-600 px-5 py-2 text-sm font-medium text-white hover:bg-indigo-700">Print Receipt</button>
        </div>

    </div>
</body>
</html>
