<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('orders.viewAny');
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can('orders.view');
    }

    public function create(User $user): bool
    {
        return $user->can('orders.create');
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update') && $order->status === OrderStatus::Pending;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete') && $order->status === OrderStatus::Pending;
    }

    // Canonical rule (intentionally mirrored in OrderService::recordCashPayment
    // guards + show.blade.php @if): only pending + unpaid orders accept payment.
    // Policy = 403 before service runs. Service = defense in depth + reusable from
    // jobs/console. View = UX (hide button that would 403).
    public function recordCashPayment(User $user, Order $order): bool
    {
        return $user->can('orders.recordPayment')
            && $order->status === OrderStatus::Pending
            && $order->payment_status === PaymentStatus::Unpaid;
    }

    public function complete(User $user, Order $order): bool
    {
        return $user->can('orders.complete') && $order->status === OrderStatus::Processing;
    }
}
