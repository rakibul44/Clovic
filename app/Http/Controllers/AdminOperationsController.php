<?php

namespace App\Http\Controllers;

use App\Models\AdminAuditLog;
use App\Models\Category;
use App\Models\InventoryMovement;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\AdminAccess;
use App\Services\AdminSignals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AdminOperationsController extends Controller
{
    private function audit(string $action, string $subject, array $changes = []): void
    {
        AdminAuditLog::create(['user_id' => auth()->id(), 'action' => $action, 'subject' => $subject, 'changes' => $changes]);
    }

    public function deleteProduct(int $id)
    {
        DB::transaction(function () use ($id) {
            $product = Product::lockForUpdate()->findOrFail($id);
            $product->update(['status' => 'archived']);
            $product->delete();
            $this->audit('product.deleted', (string) $id);
        });

        return back()->with('success', 'Product moved to deleted items. Historical orders and images are preserved.');
    }

    public function restoreProduct(int $id)
    {
        DB::transaction(function () use ($id) {
            $product = Product::onlyTrashed()->lockForUpdate()->findOrFail($id);
            $product->restore();
            $product->update(['status' => 'draft']);
            $this->audit('product.restored', (string) $id);
        });

        return back()->with('success', 'Product restored as a draft. Review before publishing.');
    }

    public function preview(int $id)
    {
        $product = Product::withTrashed()->with('images', 'variants', 'category')->findOrFail($id);
        $sales = DB::table('order_items')->join('product_variants', 'product_variants.id', '=', 'order_items.product_variant_id')->join('orders', 'orders.id', '=', 'order_items.order_id')->where('product_variants.product_id', $id)->select('orders.id', 'orders.reference', 'orders.created_at', 'orders.status', 'order_items.quantity', 'order_items.unit_price')->orderByDesc('orders.created_at')->paginate(20);

        return view('admin.preview', compact('product', 'sales'));
    }

    public function deleteCategory(int $id)
    {
        DB::transaction(function () use ($id) {
            $category = Category::lockForUpdate()->findOrFail($id);
            abort_if($category->children()->exists() || Product::withTrashed()->where('category_id', $id)->exists(), 422, 'Move all products and child categories before deleting this category.');
            $category->delete();
            $this->audit('category.deleted', (string) $id);
        });

        return redirect('/admin/content/categories')->with('success', 'Empty category deleted.');
    }

    public function inventory(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'category' => 'nullable|integer|exists:categories,id', 'level' => 'nullable|in:low,out']);
        $query = ProductVariant::with('product')->when($request->q, fn ($q) => $q->where(fn ($v) => $v->where('sku', 'like', '%'.$request->q.'%')->orWhere('size', 'like', '%'.$request->q.'%')->orWhere('color', 'like', '%'.$request->q.'%')->orWhereHas('product', fn ($p) => $p->where('name', 'like', '%'.$request->q.'%'))))->when($request->category, fn ($q) => $q->whereHas('product', fn ($p) => $p->where('category_id', $request->category)));
        if ($request->level === 'low') {
            $query->whereColumn('stock', '<=', 'low_stock_threshold');
        }if ($request->level === 'out') {
            $query->where('stock', 0);
        }

        return view('admin.inventory', ['variants' => $query->orderBy('stock')->paginate(30)->withQueryString(), 'movements' => InventoryMovement::with('variant')->latest()->limit(50)->get(), 'categories' => Category::orderBy('position')->get()]);
    }

    public function bulkStock(Request $request)
    {
        $data = $request->validate(['adjustments' => 'required|array|min:1|max:100', 'adjustments.*.id' => 'required|integer|exists:product_variants,id|distinct', 'adjustments.*.quantity' => 'required|integer|between:-100000,100000', 'reason' => 'required|string|max:200']);
        DB::transaction(function () use ($data) {
            foreach (collect($data['adjustments'])->sortBy('id') as $adjustment) {
                if (! $adjustment['quantity']) {
                    continue;
                }$variant = ProductVariant::lockForUpdate()->findOrFail($adjustment['id']);
                abort_if($variant->stock + $adjustment['quantity'] < 0, 422, 'An adjustment would make stock negative. Nothing was changed.');
                $variant->increment('stock', $adjustment['quantity']);
                InventoryMovement::create(['product_variant_id' => $variant->id, 'quantity' => $adjustment['quantity'], 'reason' => $data['reason'], 'user_id' => auth()->id()]);
            }$this->audit('inventory.bulk', 'inventory', ['variants' => count($data['adjustments']), 'reason' => $data['reason']]);
        }, 5);

        return back()->with('success', 'Bulk stock adjustments recorded.');
    }

    public function orderExport(Request $request)
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from', 'status' => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled,return_requested,returned,refunded']);
        $rows = Order::when($request->from, fn ($q) => $q->whereDate('created_at', '>=', $request->from))->when($request->to, fn ($q) => $q->whereDate('created_at', '<=', $request->to))->when($request->status, fn ($q) => $q->where('status', $request->status))->orderBy('id')->cursor()->map(fn ($o) => [$o->reference, $o->created_at, $o->name, $o->phone, $o->status, $o->payment_status, $o->area, $o->subtotal, $o->delivery, $o->discount, $o->total]);
        $this->audit('orders.exported', 'orders');

        return AdminReportsController::csv('clovic-orders.csv', ['reference', 'date', 'customer', 'phone', 'status', 'payment', 'area', 'subtotal_paisa', 'delivery_paisa', 'discount_paisa', 'total_paisa'], $rows);
    }

    public function document(int $id, string $kind)
    {
        abort_unless(in_array($kind, ['invoice', 'packing-slip']), 404);
        $order = Order::with('items', 'payments', 'shipping')->findOrFail($id);
        $this->audit('order.document', $order->reference, ['kind' => $kind]);

        return response()->view('admin.document', compact('order', 'kind'))->header('Cache-Control', 'private, no-store');
    }

    public function customers(Request $request)
    {
        $request->validate(['q' => 'nullable|string|max:100', 'active' => 'nullable|in:0,1']);
        $customers = User::where('role', 'customer')->withCount('orders')->withSum(['orders as spent' => fn ($q) => $q->where('status', 'delivered')->where('payment_status', 'paid')], 'total')->when($request->filled('active'), fn ($q) => $q->where('active', $request->active))->when($request->q, fn ($q) => $q->where(fn ($u) => $u->where('name', 'like', '%'.$request->q.'%')->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', '%'.$request->q.'%')))->latest()->paginate(25)->withQueryString();

        return view('admin.customers', compact('customers'));
    }

    public function customerDetail(int $id)
    {
        $customer = User::where('role', 'customer')->findOrFail($id);
        $orders = $customer->orders()->latest()->paginate(20);
        $spending = $customer->orders()->where('status', 'delivered')->where('payment_status', 'paid')->sum('total');

        return view('admin.customer', compact('customer', 'orders', 'spending'));
    }

    public function customerNotes(Request $request, int $id)
    {
        $data = $request->validate(['service_notes' => 'nullable|string|max:5000', 'active' => 'required|boolean']);
        $customer = User::where('role', 'customer')->findOrFail($id);
        $customer->forceFill($data)->save();
        $this->audit('customer.updated', (string) $id, ['active' => $data['active'], 'notes_changed' => true]);

        return back()->with('success', 'Customer service record saved.');
    }

    public function customerExport()
    {
        $this->audit('customers.exported', 'customers');

        return AdminReportsController::csv('clovic-customers.csv', ['id', 'name', 'email', 'phone', 'active', 'orders'], User::where('role', 'customer')->withCount('orders')->orderBy('id')->cursor()->map(fn ($u) => [$u->id, $u->name, $u->email, $u->phone, $u->active, $u->orders_count]));
    }

    public function staff()
    {
        return view('admin.staff', ['staff' => User::where('role', '!=', 'customer')->orderBy('name')->get(), 'roles' => collect(AdminAccess::ROLES)->except('owner')->all()]);
    }

    public function saveStaff(Request $request, ?int $id = null)
    {
        abort_unless(AdminAccess::can($request->user(), 'staff'), 403);
        $user = $id ? User::findOrFail($id) : new User;
        abort_if($user->exists && ($user->role === 'owner' || $user->id === auth()->id()), 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)], 'role' => ['required', Rule::in(array_diff(array_keys(AdminAccess::ROLES), ['owner']))], 'active' => 'required|boolean', 'password' => [$id ? 'nullable' : 'required', 'confirmed', PasswordRule::min(12)->letters()->numbers()]]);
        DB::transaction(function () use ($user, $data) {
            $user->fill(collect($data)->only(['name', 'email'])->all());
            if (! empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }$user->forceFill(['role' => $data['role'], 'active' => $data['active']])->save();
            $this->audit('staff.saved', (string) $user->id, ['role' => $user->role, 'active' => $user->active]);
        });

        return back()->with('success', 'Staff account saved. No default credentials were created.');
    }

    public function notifications()
    {
        return view('admin.notifications', ['signals' => app(AdminSignals::class)->forUser(auth()->user())]);
    }

    public function readNotifications(Request $request)
    {
        $data = $request->validate(['key' => 'nullable|string|max:200']);
        $signals = app(AdminSignals::class)->forUser($request->user());
        foreach ($signals['items'] as $signal) {
            if (! empty($data['key']) && $data['key'] !== $signal['key']) {
                continue;
            }DB::table('admin_notification_receipts')->updateOrInsert(['user_id' => auth()->id(), 'event_key' => $signal['key']], ['read_at' => now()]);
        }

        return back()->with('success', 'Notifications marked as read.');
    }

    public function search(Request $request)
    {
        $request->validate(['q' => 'required|string|min:2|max:100']);
        $q = $request->q;
        $results = [];
        if (AdminAccess::canRead($request->user(), 'orders')) {
            foreach (Order::where(fn ($b) => $b->where('reference', 'like', '%'.$q.'%')->orWhere('name', 'like', '%'.$q.'%')->orWhere('phone', 'like', '%'.$q.'%'))->limit(10)->get() as $order) {
                $results[] = ['label' => $order->reference.' · '.$order->name, 'type' => 'Order', 'url' => '/admin/orders/'.$order->id];
            }
        }if (AdminAccess::can($request->user(), 'products')) {
            foreach (Product::where('name', 'like', '%'.$q.'%')->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', '%'.$q.'%'))->limit(10)->get() as $product) {
                $results[] = ['label' => $product->name, 'type' => 'Product', 'url' => '/admin/products/'.$product->id.'/edit'];
            }
        }if (AdminAccess::canRead($request->user(), 'customers')) {
            foreach (User::where('role', 'customer')->where(fn ($b) => $b->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%'))->limit(10)->get() as $user) {
                $results[] = ['label' => $user->name, 'type' => 'Customer', 'url' => '/admin/customers/'.$user->id];
            }
        }

        return view('admin.search', compact('results', 'q'));
    }

    public function shipping(Request $request)
    {
        $request->validate(['status' => 'nullable|in:pending,dispatched,in_transit,delivered,failed,return_to_sender']);
        $shipments = Order::with('shipping')->whereHas('shipping', fn ($q) => $q->when($request->status, fn ($s) => $s->where('status', $request->status)))->latest()->paginate(25);

        return view('admin.shipping', compact('shipments'));
    }

    public function shipment(Request $request, int $id)
    {
        $data = $request->validate(['courier' => 'required|string|max:100', 'tracking_number' => 'required|string|max:100', 'status' => 'required|in:pending,dispatched,in_transit,delivered,failed,return_to_sender', 'dispatched_at' => 'nullable|date', 'estimated_at' => 'nullable|date|after_or_equal:dispatched_at', 'notes' => 'nullable|string|max:2000']);
        $order = Order::findOrFail($id);
        $order->shipping()->updateOrCreate(['order_id' => $id], $data);
        $this->audit('shipment.saved', $order->reference, collect($data)->except('notes')->all());

        return back()->with('success', 'Manual shipment record saved. Order and payment statuses are unchanged.');
    }

    public function shippingSettings(Request $request)
    {
        abort_unless(AdminAccess::can($request->user(), 'shipping.configure'), 403);
        $data = $request->validate(['delivery_inside' => 'required|integer|min:0|max:1000000', 'delivery_outside' => 'required|integer|min:0|max:1000000', 'free_shipping_threshold' => 'nullable|integer|min:0|max:100000000', 'delivery_estimate' => 'nullable|string|max:500', 'courier_info' => 'nullable|string|max:500']);
        foreach ($data as $key => $value) {
            StoreSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }$this->audit('shipping.settings', 'store', array_keys($data));

        return back()->with('success', 'Delivery rules saved. Checkout will use them immediately.');
    }

    public function payments(Request $request)
    {
        $request->validate(['status' => 'nullable|in:unpaid,paid,refunded']);
        $orders = Order::with('payments')->when($request->status, fn ($q) => $q->where('payment_status', $request->status))->latest()->paginate(25);

        return view('admin.payments', compact('orders'));
    }

    public function payment(Request $request, int $id)
    {
        $data = $request->validate(['status' => 'required|in:paid,refunded', 'reference' => 'required|string|max:150']);
        DB::transaction(function () use ($id, $data) {
            $order = Order::lockForUpdate()->findOrFail($id);
            if ($data['status'] === $order->payment_status) {
                return;
            }abort_unless(($data['status'] === 'paid' && $order->payment_status === 'unpaid') || ($data['status'] === 'refunded' && $order->payment_status === 'paid'), 422, 'Invalid payment transition.');
            $order->update(['payment_status' => $data['status']]);
            $order->payments()->update(['status' => $data['status'], 'provider_reference' => $data['reference']]);
            $this->audit('payment.recorded', $order->reference, ['status' => $data['status']]);
        });

        return back()->with('success', 'Verified collection/refund recorded. This did not execute a gateway payment.');
    }
}
