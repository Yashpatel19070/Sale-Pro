<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::INVOICES_VIEW_ANY);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::INVOICES_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::INVOICES_CREATE);
    }

    public function approve(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::INVOICES_APPROVE);
    }

    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::INVOICES_MARK_PAID);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->can(Permission::INVOICES_DELETE);
    }
}
