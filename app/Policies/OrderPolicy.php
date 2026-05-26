<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Permission;
use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ORDERS_VIEW);
    }

    public function view(User $user, Order $order): bool
    {
        return $user->can(Permission::ORDERS_VIEW);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ORDERS_CREATE);
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can(Permission::ORDERS_MANAGE)
            && $order->status === OrderStatus::Pending;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can(Permission::ORDERS_MANAGE)
            && $order->status === OrderStatus::Pending;
    }

    public function recordCashPayment(User $user, Order $order): bool
    {
        return $user->can(Permission::ORDERS_MANAGE)
            && $order->payment_status === PaymentStatus::Unpaid;
    }

    public function complete(User $user, Order $order): bool
    {
        return $user->can(Permission::ORDERS_MANAGE)
            && $order->status === OrderStatus::Processing;
    }
}
