<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\AdminAccess;
use App\Services\Settings;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportsController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'period' => 'nullable|in:daily,weekly,monthly']);
        $from = Carbon::parse($request->from ?? now()->subDays(29)->toDateString())->startOfDay();
        $to = Carbon::parse($request->to ?? now()->toDateString())->endOfDay();
        abort_if($from->diffInDays($to) > 731, 422, 'Choose a date range of two years or less.');
        $orders = Order::whereBetween('created_at', [$from, $to]);
        $sales = (clone $orders)->where('status', 'delivered')->where('payment_status', 'paid');
        $financial = AdminAccess::can($request->user(), 'reports');
        $cost = AdminAccess::can($request->user(), 'cost');
        $counts = (clone $orders)->select('status', DB::raw('COUNT(*) as count'))->groupBy('status')->pluck('count', 'status');
        $revenue = (int) (clone $sales)->sum('total');
        $goods = (int) (clone $sales)->sum('subtotal') - (int) (clone $sales)->sum('discount');
        $shipping = (int) (clone $sales)->sum('delivery');
        $days = $from->diffInDays($to) + 1;
        $priorTo = $from->copy()->subSecond();
        $priorFrom = $from->copy()->subDays((int) $days);
        $prior = Order::whereBetween('created_at', [$priorFrom, $priorTo])->where('status', 'delivered')->where('payment_status', 'paid');
        $priorRevenue = (int) $prior->sum('total');
        $comparison = $priorRevenue > 0 ? round(($revenue - $priorRevenue) / $priorRevenue * 100, 1) : null;
        $daily = (clone $sales)->selectRaw('DATE(created_at) as day, SUM(total) as total, COUNT(*) as count')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $dates = collect();
        for ($date = $from->copy(); $date <= $to; $date->addDay()) {
            $dates->put($date->toDateString(), ['total' => 0, 'count' => 0]);
        }foreach ($daily as $day) {
            $dates->put($day->day, ['total' => (int) $day->total, 'count' => (int) $day->count]);
        }
        $period = $request->period ?? 'daily';
        $trend = $dates->map(fn ($value, $date) => $value + ['key' => match ($period) {
            'weekly' => Carbon::parse($date)->startOfWeek()->toDateString(),'monthly' => Carbon::parse($date)->startOfMonth()->format('Y-m'),default => $date
        }])->groupBy('key')->map(fn ($rows, $label) => ['label' => $label, 'total' => $rows->sum('total'), 'count' => $rows->sum('count')])->values();
        $items = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->join('products', 'products.id', '=', 'product_variants.product_id')->whereBetween('orders.created_at', [$from, $to])->where('orders.status', 'delivered')->where('orders.payment_status', 'paid');
        $best = (clone $items)->select('products.id', 'products.name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.unit_price * order_items.quantity - order_items.discount) as net'))->groupBy('products.id', 'products.name')->orderByDesc('units')->limit(12)->get();
        $categories = (clone $items)->join('categories', 'categories.id', '=', 'products.category_id')->select('categories.name', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.unit_price * order_items.quantity - order_items.discount) as net'))->groupBy('categories.name')->orderByDesc('net')->get();
        $registrations = User::where('role', 'customer')->whereBetween('created_at', [$from, $to])->selectRaw('DATE(created_at) as day, COUNT(*) as count')->groupByRaw('DATE(created_at)')->orderBy('day')->get();
        $valuation = $cost ? ProductVariant::join('products', 'products.id', '=', 'product_variants.product_id')->whereNull('products.deleted_at')->whereNotNull('products.cost_price')->sum(DB::raw('product_variants.stock * products.cost_price')) : null;
        $unknownCost = $cost ? (clone $items)->whereNull('order_items.unit_cost')->count() : 0;
        $cogs = $cost ? (int) (clone $items)->sum(DB::raw('order_items.unit_cost * order_items.quantity')) : null;
        $couponUsage = DB::table('coupon_redemptions')->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')->whereBetween('coupon_redemptions.created_at', [$from, $to])->select('coupons.code', DB::raw('COUNT(*) as uses'), DB::raw('SUM(coupon_redemptions.discount) as discount'))->groupBy('coupons.code')->orderByDesc('uses')->get();
        $customers = (clone $sales)->whereNotNull('user_id')->distinct()->pluck('user_id');
        $returning = Order::whereIn('user_id', $customers)->where('created_at', '<', $from)->where('status', 'delivered')->where('payment_status', 'paid')->distinct()->count('user_id');
        $stats = ['orders' => $counts->sum(), 'pending' => $counts['pending'] ?? 0, 'completed' => $counts['delivered'] ?? 0, 'cancelled' => $counts['cancelled'] ?? 0, 'customers' => User::where('role', 'customer')->count(), 'products' => Product::published()->count(), 'low_stock' => ProductVariant::whereColumn('stock', '<=', 'low_stock_threshold')->where('stock', '>', 0)->count(), 'out_of_stock' => ProductVariant::where('stock', 0)->count(), 'today_sales' => (int) Order::whereDate('created_at', today())->where('status', 'delivered')->where('payment_status', 'paid')->sum('total')];
        $orders = (clone $orders)->latest()->limit(10)->get();
        $low = ProductVariant::with('product')->whereColumn('stock', '<=', 'low_stock_threshold')->orderBy('stock')->limit(10)->get();

        return view('admin.reports', compact('from', 'to', 'financial', 'cost', 'counts', 'revenue', 'goods', 'shipping', 'comparison', 'trend', 'best', 'categories', 'registrations', 'valuation', 'unknownCost', 'cogs', 'couponUsage', 'customers', 'returning', 'stats', 'orders', 'low'));
    }

    public static function csv(string $name, array $headers, iterable $rows)
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            $moneyColumns = array_keys(array_filter($headers, fn ($header) => str_ends_with($header, '_paisa')));
            fputcsv($out, array_map(fn ($header) => preg_replace('/_paisa$/', '_bdt', $header), $headers));
            foreach ($rows as $row) {
                $row = array_values((array) $row);
                foreach ($moneyColumns as $index) {
                    $row[$index] = $row[$index] === null ? null : Settings::decimal((int) $row[$index]);
                }
                fputcsv($out, array_map(fn ($value) => is_string($value) && preg_match('/^[=+@\-\t\r]/', $value) ? "'".$value : $value, (array) $row));
            }fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function export(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'kind' => 'required|in:sales,products,categories,inventory,coupons']);
        AdminAuditLog::create(['user_id' => $request->user()->id, 'action' => 'report.exported', 'subject' => $request->kind, 'changes' => $request->only('from', 'to')]);
        $from = $request->from ?? now()->subDays(29)->toDateString();
        $to = ($request->to ?? now()->toDateString()).' 23:59:59';
        if ($request->kind === 'inventory') {
            $cost = AdminAccess::can($request->user(), 'cost');
            $rows = ProductVariant::with('product')->orderBy('id')->cursor()->map(fn ($v) => $cost ? [$v->sku, $v->product->name, $v->size, $v->color, $v->stock, $v->product->cost_price, $v->product->cost_price === null ? null : $v->stock * $v->product->cost_price] : [$v->sku, $v->product->name, $v->size, $v->color, $v->stock]);

            return self::csv('clovic-inventory.csv', $cost ? ['sku', 'product', 'size', 'color', 'stock', 'cost_paisa', 'value_paisa'] : ['sku', 'product', 'size', 'color', 'stock'], $rows);
        }
        if ($request->kind === 'coupons') {
            $rows = DB::table('coupon_redemptions')->join('coupons', 'coupons.id', '=', 'coupon_redemptions.coupon_id')->whereBetween('coupon_redemptions.created_at', [$from, $to])->select('coupons.code', DB::raw('COUNT(*) as uses'), DB::raw('SUM(coupon_redemptions.discount) as discount_paisa'))->groupBy('coupons.code')->get();

            return self::csv('clovic-coupons.csv', ['code', 'uses', 'discount_paisa'], $rows);
        }
        if (in_array($request->kind, ['products', 'categories'])) {
            $q = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->join('products', 'products.id', '=', 'product_variants.product_id')->join('categories', 'categories.id', '=', 'products.category_id')->whereBetween('orders.created_at', [$from, $to])->where('orders.status', 'delivered')->where('orders.payment_status', 'paid');
            $field = $request->kind === 'products' ? 'products.name' : 'categories.name';
            $rows = $q->select($field, DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.unit_price * order_items.quantity - order_items.discount) as net_paisa'))->groupBy($field)->get();

            return self::csv('clovic-'.$request->kind.'.csv', ['name', 'units', 'net_paisa'], $rows);
        }

        return self::csv('clovic-sales.csv', ['reference', 'date', 'status', 'payment_status', 'subtotal_paisa', 'delivery_paisa', 'discount_paisa', 'total_paisa'], Order::whereBetween('created_at', [$from, $to])->orderBy('id')->cursor()->map(fn ($o) => [$o->reference, $o->created_at, $o->status, $o->payment_status, $o->subtotal, $o->delivery, $o->discount, $o->total]));
    }
}
