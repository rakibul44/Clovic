<?php

use App\Models\Order;
use App\Services\Checkout;
use App\Services\OrderWorkflow;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Worker for the opt-in MySQL integration harness. Uses only isolated test fixtures.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_concurrency_test')) {
    fwrite(STDERR, "An isolated *_concurrency_test database is required.\n");
    exit(2);
}
[$script,$mode,$id,$key,$start] = $argv;
while (microtime(true) < (float) $start) {
    usleep(1000);
}
try {
    if ($mode === 'cancel') {
        $order = Order::findOrFail($id);
        $order = app(OrderWorkflow::class)->transition($order, 'cancelled', null);
    } else {
        $order = app(Checkout::class)->place(['name' => 'Concurrency fixture', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Isolated test address', 'idempotency_key' => $key] + ($mode === 'coupon' ? ['coupon' => 'ONCE'] : []), [(int) $id => 1], 'test-owner');
    }
    echo json_encode(['ok' => true, 'reference' => $order->reference]);
} catch (ValidationException $e) {
    echo json_encode(['ok' => false, 'validation' => true]);
}
