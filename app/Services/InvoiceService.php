<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PurchaseOrderStatus;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function store(PurchaseOrder $po, array $data): Invoice
    {
        $allowedStatuses = [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::OnTheWay,
            PurchaseOrderStatus::PartiallyReceived,
            PurchaseOrderStatus::Received,
        ];

        throw_if(
            ! in_array($po->status, $allowedStatuses, true),
            \DomainException::class,
            'Invoices can only be created for approved or received purchase orders.'
        );

        return DB::transaction(function () use ($po, $data): Invoice {
            $invoice = Invoice::create([
                'purchase_order_id' => $po->id,
                'invoice_number' => $data['invoice_number'],
                'invoice_date' => $data['invoice_date'],
                'due_date' => $data['due_date'] ?? null,
                'amount' => $data['amount'],
                'status' => InvoiceStatus::Pending,
                'notes' => $data['notes'] ?? null,
            ]);

            if ($po->status === PurchaseOrderStatus::Received) {
                $po->update(['status' => PurchaseOrderStatus::Invoiced]);
            }

            return $invoice->fresh();
        });
    }

    public function approve(Invoice $invoice, User $approver): Invoice
    {
        throw_if(
            $invoice->status !== InvoiceStatus::Pending,
            \DomainException::class,
            'Only pending invoices can be approved.'
        );

        $invoice->update([
            'status' => InvoiceStatus::Approved,
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);

        return $invoice->fresh();
    }

    public function markPaid(Invoice $invoice): Invoice
    {
        throw_if(
            $invoice->status !== InvoiceStatus::Approved,
            \DomainException::class,
            'Only approved invoices can be marked as paid.'
        );

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
            ]);

            $po = $invoice->purchaseOrder;
            $unpaidExists = $po->invoices()
                ->where('id', '!=', $invoice->id)
                ->whereNull('deleted_at')
                ->where('status', '!=', InvoiceStatus::Paid->value)
                ->exists();

            if (! $unpaidExists) {
                $po->update(['status' => PurchaseOrderStatus::Closed]);
            }

            return $invoice->fresh();
        });
    }

    public function delete(Invoice $invoice): void
    {
        throw_if(
            $invoice->status === InvoiceStatus::Paid,
            \DomainException::class,
            'Paid invoices cannot be deleted.'
        );

        $invoice->delete();
    }
}
