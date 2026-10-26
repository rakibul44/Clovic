<?php

namespace App\Services;

use App\Models\AdminAuditLog;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderWorkflow
{
    public const TRANSITIONS = ['pending' => ['confirmed', 'cancelled'], 'confirmed' => ['processing', 'cancelled'], 'processing' => ['shipped', 'cancelled'], 'shipped' => ['delivered', 'returned'], 'delivered' => ['return_requested'], 'return_requested' => ['returned', 'delivered'], 'returned' => ['refunded'], 'cancelled' => [], 'refunded' => []];

    public function transition(Order $order, string $status, ?int $actor): Order
    {
        return DB::transaction(function () use ($order, $status, $actor) {
            $order = Order::lockForUpdate()->findOrFail($order->id);
            if ($order->status === $status) {
                return $order;
            }
            if (! in_array($status, self::TRANSITIONS[$order->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => 'This status transition is not permitted.']);
            }
            if ($status === 'refunded' && $order->payment_status !== 'refunded') {
                throw ValidationException::withMessages(['status' => 'Record the actual payment refund before marking the order refunded.']);
            }
            if (in_array($status, ['cancelled', 'returned']) && ! $order->restocked_at && Settings::get('restore_stock', '1') === '1') {
                foreach ($order->items()->orderBy('product_variant_id')->get() as $item) {
                    $v = ProductVariant::lockForUpdate()->findOrFail($item->product_variant_id);
                    $v->increment('stock', $item->quantity);
                    InventoryMovement::create(['product_variant_id' => $v->id, 'user_id' => $actor, 'quantity' => $item->quantity, 'reason' => $status, 'reference' => $order->reference]);
                }
                $order->restocked_at = now();
            }
            $order->status = $status;
            $order->save();
            $order->history()->create(['status' => $status, 'user_id' => $actor]);
            AdminAuditLog::create(['user_id' => $actor, 'action' => 'order.status', 'subject' => $order->reference, 'changes' => ['status' => $status]]);

            return $order;
        }, 5);
    }
}
