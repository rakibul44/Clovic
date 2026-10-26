<?php

namespace App\Policies;

use App\Models\Order;
use App\Models\User;

class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->active && ($order->user_id === $user->id || in_array($user->role, ['admin', 'owner']));
    }
}
