<?php

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

// Destructive only within an explicitly named isolated *_concurrency_test database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (config('database.default') !== 'mysql' || ! str_ends_with(config('database.connections.mysql.database'), '_concurrency_test')) {
    fwrite(STDERR, "Use a dedicated MySQL database ending in _concurrency_test. This command resets it.\n");
    exit(2);
}
Artisan::call('migrate:fresh', ['--force' => true]);
$c = Category::create(['name' => 'Concurrency fixtures', 'slug' => 'concurrency', 'published' => true]);
$p = Product::create(['category_id' => $c->id, 'name' => 'Test only', 'slug' => 'test-only', 'description' => 'Test fixture', 'price' => 10000, 'status' => 'published']);
function race(string $mode, int $id, array $tokens): array
{
    $start = (string) (microtime(true) + 2);
    $workers = [];
    foreach ($tokens as $token) {
        $process = proc_open([PHP_BINARY, __DIR__.'/mysql-worker.php', $mode, (string) $id, $token, $start], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__));
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $results = [];
    foreach ($workers as [$process,$pipes]) {
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($process);
        if ($code !== 0 || ! ($result = json_decode($out, true))) {
            throw new RuntimeException('Worker failed: '.$error);
        }
        $results[] = $result;
    }

    return $results;
}
function check(bool $pass, string $label): void
{
    if (! $pass) {
        throw new RuntimeException('FAILED: '.$label);
    }
    echo $label." passed\n";
}
$v = $p->variants()->create(['sku' => 'LAST', 'size' => 'M', 'color' => 'Navy', 'stock' => 1]);
$r = race('checkout', $v->id, [(string) Str::uuid(), (string) Str::uuid()]);
check(count(array_filter($r, fn ($v) => $v['ok'])) === 1 && $v->fresh()->stock === 0 && Order::count() === 1, 'Last-unit simultaneous checkout');
$v = $p->variants()->create(['sku' => 'IDEMPOTENT', 'size' => 'L', 'color' => 'Navy', 'stock' => 5]);
$token = (string) Str::uuid();
$r = race('checkout', $v->id, [$token, $token]);
check($r[0]['ok'] && $r[1]['ok'] && $r[0]['reference'] === $r[1]['reference'] && $v->fresh()->stock === 4 && Order::where('idempotency_key', $token)->count() === 1, 'Simultaneous duplicate submission');
$o = Order::where('idempotency_key', $token)->firstOrFail();
$r = race('cancel', $o->id, ['', '']);
check($v->fresh()->stock === 5 && $o->fresh()->status === 'cancelled', 'Simultaneous cancellation restores once');
Coupon::create(['code' => 'ONCE', 'percent' => 10, 'minimum' => 0, 'usage_limit' => 1, 'active' => true]);
$r = race('coupon', $v->id, [(string) Str::uuid(), (string) Str::uuid()]);
check(count(array_filter($r, fn ($v) => $v['ok'])) === 1 && Coupon::first()->used === 1 && $v->fresh()->stock === 4, 'Simultaneous coupon usage limit');
echo "All four MySQL contention scenarios passed.\n";
