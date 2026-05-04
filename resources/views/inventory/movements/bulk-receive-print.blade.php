<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Serial Labels — {{ $serials->first()?->product->name ?? 'Batch' }}</title>

    <style>
        /* ── Screen ─────────────────────────────── */
        body { font-family: monospace; background: #f3f4f6; padding: 16px; }
        .no-print { text-align: center; margin-bottom: 16px; }
        .no-print button {
            padding: 10px 24px; background: #4f46e5; color: #fff;
            border: none; border-radius: 8px; font-size: 14px; cursor: pointer;
        }
        .no-print a { margin-left: 12px; font-size: 14px; color: #6b7280; text-decoration: none; }
        .label-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
        .label {
            background: #fff; border: 1px solid #374151;
            padding: 6px 8px; font-size: 9pt;
        }
        .label .sku  { font-weight: bold; font-size: 8pt; color: #374151; }
        .label .name { font-size: 8pt; color: #6b7280; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .label .sn   { font-weight: bold; font-size: 11pt; margin: 2px 0; letter-spacing: 0.5px; }
        .label .meta { font-size: 7pt; color: #9ca3af; margin-top: 2px; }
        .label svg   { width: 100%; display: block; }

        /* ── Print ──────────────────────────────── */
        @media print {
            body { background: #fff; padding: 0; }
            .no-print { display: none; }
            .label-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
            .label { page-break-inside: avoid; border: 1px solid #000; padding: 3mm; }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>

    <div class="no-print">
        <p style="color:#374151;font-family:sans-serif;margin-bottom:12px">
            <strong>{{ $serials->count() }}</strong> labels —
            {{ $serials->first()?->product->name }}
            &nbsp;·&nbsp; {{ now()->format('Y-m-d') }}
        </p>
        <button onclick="window.print()">Print Labels</button>
        <a href="{{ route('inventory-movements.index') }}">← Back to movements</a>
    </div>

    <div class="label-grid">
        @foreach ($serials as $serial)
        <div class="label">
            <div class="sku">{{ $serial->product->sku }}</div>
            <div class="name">{{ $serial->product->name }}</div>
            <div class="sn">{{ $serial->serial_number }}</div>
            <svg class="barcode" data-serial="{{ $serial->serial_number }}"></svg>
            <div class="meta">
                Rcvd: {{ $serial->received_at->format('Y-m-d') }}
                &nbsp;·&nbsp; {{ $serial->location->code }}
            </div>
        </div>
        @endforeach
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        document.querySelectorAll('.barcode').forEach(function (el) {
            JsBarcode(el, el.dataset.serial, {
                format:       'CODE128',
                width:        1.5,
                height:       35,
                displayValue: false,
                margin:       2,
            });
        });
    </script>

</body>
</html>
