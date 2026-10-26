<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotifier
{
    public function send(Order $order, string $event): bool
    {
        if (! config('store.mail_enabled') || ! $order->email) {
            return false;
        }
        $key = 'order-mail:'.$order->id.':'.$event;

        return Cache::lock($key.':lock', 30)->get(function () use ($order, $event, $key) {
            if (Cache::has($key)) {
                return true;
            }
            try {
                Mail::send('emails.order', ['order' => $order, 'event' => $event], fn ($m) => $m->to($order->email)->subject('Clovic · '.$event.' · '.$order->reference));
                Cache::put($key, true, now()->addYear());

                return true;
            } catch (\Throwable $e) {
                Log::warning('Order notification failed', ['order' => $order->reference, 'event' => $event]);

                return false;
            }
        }) ?? false;
    }
}
