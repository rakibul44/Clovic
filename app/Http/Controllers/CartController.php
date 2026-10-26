<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckoutRequest;
use App\Models\Coupon;
use App\Models\Order;
use App\Services\Cart;
use App\Services\Checkout;
use App\Services\OrderNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index(Cart $cart)
    {
        return view('cart', ['lines' => $cart->lines(), 'subtotal' => $cart->subtotal()]);
    }

    public function update(Request $r, Cart $cart)
    {
        $d = $r->validate(['variant_id' => 'required|integer|exists:product_variants,id', 'quantity' => 'required|integer|min:0|max:99', 'add' => 'nullable|in:1']);
        $cart->set($d['variant_id'], $r->boolean('add') ? min(99, ($cart->quantities()[$d['variant_id']] ?? 0) + $d['quantity']) : $d['quantity']);

        return back()->with('success', 'Shopping bag updated.');
    }

    public function checkout(Cart $cart)
    {
        if (! $cart->quantities()) {
            return redirect('/cart');
        }
        if (! session('checkout_key') || Order::where('idempotency_key', session('checkout_key'))->exists()) {
            session(['checkout_key' => (string) Str::uuid()]);
        }

        return view('checkout', ['lines' => $cart->lines(), 'subtotal' => $cart->subtotal(), 'discount' => $this->discount(session('checkout_coupon'), $cart->subtotal()), 'key' => session('checkout_key')]);
    }

    private function discount(?string $code, int $subtotal): int
    {
        if (! $code) {
            return 0;
        }
        $c = Coupon::where('code', strtoupper($code))->first();

        return $c && $c->active && (! $c->expires_at || $c->expires_at->isFuture()) && $c->used < $c->usage_limit && $subtotal >= $c->minimum ? intdiv($subtotal * $c->percent, 100) : 0;
    }

    public function quote(Request $r, Cart $cart)
    {
        $r->validate(['coupon' => 'nullable|string|max:40']);
        $discount = $this->discount($r->coupon, $cart->subtotal());
        if ($r->filled('coupon') && ! $discount) {
            return redirect('/checkout')->withInput()->withErrors(['coupon' => 'This coupon is not available for this order.']);
        }
        session(['checkout_coupon' => $r->coupon]);

        return redirect('/checkout')->withInput()->with('success', $discount ? 'Coupon applied.' : 'Coupon removed.');
    }

    public function place(CheckoutRequest $r, Cart $cart, Checkout $checkout)
    {
        $owner = session('checkout_owner');
        if (! $owner) {
            $owner = Str::random(64);
            session(['checkout_owner' => $owner]);
        }
        abort_unless(hash_equals((string) session('checkout_key'), $r->idempotency_key) || in_array($r->idempotency_key, session('completed_checkout_keys', []), true), 419);
        $order = $checkout->place($r->validated(), $cart->quantities(), $owner);
        if ($order->wasRecentlyCreated) {
            $cart->clear();
        }
        session(['completed_checkout_keys' => array_slice(array_unique([...session('completed_checkout_keys', []), $r->idempotency_key]), -20)]);
        session()->put('confirmed_orders.'.$order->reference, true);
        app(OrderNotifier::class)->send($order, 'confirmation');

        return redirect('/order/'.$order->reference);
    }

    public function confirmation(string $reference)
    {
        $order = Order::where('reference', $reference)->firstOrFail();
        abort_unless(session('confirmed_orders.'.$reference) || (auth()->check() && Gate::allows('view', $order)), 404);

        return view('confirmation', ['order' => $order->load('items')]);
    }

    public function track(Request $r)
    {
        $order = null;
        if ($r->isMethod('post')) {
            $d = $r->validate(['reference' => 'required|string|max:40', 'phone' => ['required', 'regex:/^(?:\+?88)?01[3-9][0-9]{8}$/']]);
            $order = Order::where('reference', $d['reference'])->where('phone', preg_replace('/^(?:\+?88)/', '', $d['phone']))->first();
            if (! $order) {
                return back()->withErrors(['tracking' => 'No matching order was found.']);
            }
        }

        return view('track', ['order' => $order?->load('history')]);
    }
}
