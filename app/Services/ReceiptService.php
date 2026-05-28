<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Order;

class ReceiptService
{
    public function build(Order $order): array
    {
        $order->loadMissing([
            'customer',
            'lines.lineFees',
            'lines.inventorySerial',
            'payments',
            'createdBy',
        ]);

        return [
            'shop' => $this->shop(),
            'order' => $this->orderHeader($order),
            'customer' => $this->customer($order),
            'lines' => $this->lines($order),
            'totals' => $this->totals($order),
            'payments' => $this->payments($order),
            'footer' => $this->footer(),
        ];
    }

    private function shop(): array
    {
        $cfg = config('shop.billing');

        return [
            'name' => $cfg['first_name'] ?? null,
            'email' => $cfg['email'] ?? null,
            'phone' => $cfg['phone'] ?? null,
            'address_line1' => $cfg['address_line1'] ?? null,
            'city' => $cfg['city'] ?? null,
            'state' => $cfg['state'] ?? null,
            'postal_code' => $cfg['postal_code'] ?? null,
            'country' => $cfg['country'] ?? null,
            'has_letterhead' => ! empty($cfg['first_name']),
        ];
    }

    private function orderHeader(Order $order): array
    {
        return [
            'number' => $order->number,
            'created_at' => $order->created_at,
            'status_label' => $order->status->label(),
        ];
    }

    private function customer(Order $order): array
    {
        $c = $order->customer;

        return [
            'name' => $c?->name,
            'email' => $c?->email,
            'phone' => $c?->phone,
        ];
    }

    private function lines(Order $order): array
    {
        return $order->lines->map(fn ($line) => [
            'product_name' => $line->product_name,
            'sku' => $line->sku,
            'unit_price' => (float) $line->unit_price,
            'tax_amount' => (float) $line->tax_amount,
            'line_total' => (float) $line->line_total,
            'serial_number' => $line->inventorySerial?->serial_number,
            'fees' => $line->lineFees->map(fn ($f) => [
                'name' => $f->name,
                'amount' => (float) $f->amount,
                'tax_amount' => (float) $f->tax_amount,
                'fee_total' => (float) $f->fee_total,
            ])->all(),
        ])->all();
    }

    private function totals(Order $order): array
    {
        $lineTotals = $order->lines->sum(fn ($l) => (float) $l->line_total);
        $feeTotals = $order->lines->flatMap->lineFees->sum(fn ($f) => (float) $f->fee_total);

        return [
            'line_totals' => round((float) $lineTotals, 2),
            'fee_totals' => round((float) $feeTotals, 2),
            'shipping' => (float) $order->shipping,
            'grand_total' => (float) $order->grand_total,
        ];
    }

    private function payments(Order $order): array
    {
        return $order->payments->map(fn ($p) => [
            'method_label' => $p->method->label(),
            'amount' => (float) $p->amount,
            'status_label' => $p->status->label(),
            'cash_received_at' => $p->cash_received_at,
        ])->all();
    }

    private function footer(): array
    {
        return [
            'thank_you' => 'Thank you for your business!',
            'support_email' => config('shop.billing.email'),
        ];
    }
}
