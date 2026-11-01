<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class AdminSignals
{
    public function forUser($user): array
    {
        $signals = [];
        if (AdminAccess::canRead($user, 'orders')) {
            foreach (Order::where('created_at', '>=', now()->subDays(30))->latest()->limit(20)->get() as $order) {
                $signals[] = ['key' => 'order:'.$order->id, 'title' => 'New order '.$order->reference, 'detail' => Settings::money($order->total).' · '.$order->status, 'url' => '/admin/orders/'.$order->id, 'time' => $order->created_at];
            }
        }
        if (AdminAccess::can($user, 'orders')) {
            foreach (Order::whereIn('status', ['cancelled', 'returned', 'return_requested'])->latest('updated_at')->limit(10)->get() as $order) {
                $signals[] = ['key' => 'order-status:'.$order->id.':'.$order->status, 'title' => ucfirst(str_replace('_', ' ', $order->status)).' · '.$order->reference, 'detail' => 'Review the order and its inventory history.', 'url' => '/admin/orders/'.$order->id, 'time' => $order->updated_at];
            }
        }
        if (AdminAccess::can($user, 'inventory')) {
            foreach (ProductVariant::with('product')->whereHas('product', fn ($q) => $q->where('status', '!=', 'archived'))->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(20)->get() as $variant) {
                $signals[] = ['key' => 'stock:'.$variant->id.':'.$variant->stock, 'title' => ($variant->stock === 0 ? 'Out of stock: ' : 'Low stock: ').$variant->sku, 'detail' => $variant->product->name.' · '.$variant->stock.' remaining', 'url' => '/admin/inventory?q='.urlencode($variant->sku), 'time' => $variant->updated_at];
            }
        }
        if (AdminAccess::can($user, 'payments')) {
            foreach (Order::where('status', 'delivered')->where('payment_status', 'unpaid')->latest()->limit(10)->get() as $order) {
                $signals[] = ['key' => 'payment:'.$order->id, 'title' => 'COD collection unrecorded · '.$order->reference, 'detail' => 'Delivery does not confirm collection. Verify before recording payment.', 'url' => '/admin/orders/'.$order->id, 'time' => $order->updated_at];
            }
        }
        $read = DB::table('admin_notification_receipts')->where('user_id', $user->id)->pluck('event_key')->all();
        $items = collect($signals)->sortByDesc('time')->values()->map(fn ($signal) => $signal + ['read' => in_array($signal['key'], $read, true)])->all();

        return ['items' => $items, 'unread' => count(array_filter($items, fn ($item) => ! $item['read']))];
    }
}
