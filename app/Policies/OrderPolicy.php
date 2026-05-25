<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
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

    public function pay(User $user, Order $order): bool
    {
        return $user->can('orders.pay');
    }

    public function ship(User $user, Order $order): bool
    {
        return $user->can('orders.ship');
    }

    public function deliver(User $user, Order $order): bool
    {
        return $user->can('orders.deliver');
    }

    public function edit(User $user, Order $order): bool
    {
        return $user->can('orders.update') && $order->status === OrderStatus::Pending;
    }

    public function update(User $user, Order $order): bool
    {
        return $user->can('orders.update');
    }

    public function cancel(User $user, Order $order): bool
    {
        return $user->can('orders.cancel');
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->can('orders.delete');
    }
}
