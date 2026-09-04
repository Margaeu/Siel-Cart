<?php

// Run with: php tests/Integration/cart_concurrency.php
// Uses the configured local MySQL server and a temporary, isolated database.
// Requires permission to create and drop that temporary database.

use App\Models\Customer;
use App\Models\Product;
use App\Services\CartService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function waitFor(callable $condition, string $description): void
{
    $deadline = microtime(true) + 15;
    while (! $condition()) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for '.$description);
        }
        usleep(20000);
    }
}

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$worker = ($argv[1] ?? '') === 'worker';
$database = $worker ? $argv[2] : 'cart_lock_test_'.bin2hex(random_bytes(8));
check((bool) preg_match('/\Acart_lock_test_[a-f0-9]{16}\z/', $database), 'Invalid test database name.');
$connection = config('database.connections.mysql');
check(in_array($connection['host'], ['127.0.0.1', 'localhost'], true), 'This test requires a local MySQL server.');
$connection['url'] = null;
$connection['database'] = $database;
config([
    'database.default' => 'cart_concurrency',
    'database.connections.cart_concurrency' => $connection,
    'cache.default' => 'array',
    'session.driver' => 'array',
    'mail.default' => 'array',
]);

if ($worker) {
    [$customerId, $productId, $signals, $role] = array_slice($argv, 3);
    auth('customer')->setUser(Customer::findOrFail($customerId));
    if ($role === 'first') {
        $pending = true;
        DB::listen(function (QueryExecuted $query) use (&$pending, $signals) {
            if ($pending && str_starts_with($query->sql, 'select') && str_contains($query->sql, 'from `cart_items`')) {
                $pending = false;
                touch($signals.'/read');
                waitFor(fn () => file_exists($signals.'/release'), 'release of the first addition');
            }
        });
    }
    touch($signals.'/'.$role.'-ready');
    echo json_encode(app(CartService::class)->addItem((int) $productId), JSON_THROW_ON_ERROR);
    exit;
}

// Connect without selecting the application's database. Only the generated
// test database is created, migrated, populated, and eventually dropped.
$server = new PDO(
    'mysql:host='.$connection['host'].';port='.$connection['port'],
    $connection['username'],
    $connection['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
$signalsRoot = storage_path('framework/testing/'.$database);
mkdir($signalsRoot, 0777, true);
$processes = [];

try {
    check(Artisan::call('migrate', ['--database' => 'cart_concurrency', '--force' => true]) === 0, 'Test migrations failed.');
    $customer = Customer::factory()->create();
    auth('customer')->setUser($customer);
    $service = app(CartService::class);
    $cart = $service->getCart();

    foreach ([
        'first additions respect stock' => [0, 1, 1, 1],
        'first additions share one row' => [0, 5, 2, 2],
        'existing quantity preserves both additions' => [1, 5, 3, 2],
    ] as $name => [$initial, $stock, $expectedQuantity, $expectedSuccesses]) {
        $service->clearCart();
        $product = Product::factory()->create([
            'is_active' => true, 'has_variants' => false,
            'price' => 125.50, 'stock_quantity' => $stock,
        ]);
        if ($initial) {
            $service->addItem($product->id, quantity: $initial);
        }

        $signals = $signalsRoot.'/'.$product->id;
        mkdir($signals);
        $startWorker = function (string $role) use ($database, $customer, $product, $signals, &$processes): Process {
            $process = new Process([
                PHP_BINARY, __FILE__, 'worker', $database,
                (string) $customer->id, (string) $product->id, $signals, $role,
            ], dirname(__DIR__, 2));
            $process->setTimeout(20);
            $process->start();
            $processes[] = $process;

            return $process;
        };
        $first = $startWorker('first');
        waitFor(fn () => file_exists($signals.'/read'), 'the first addition to read its item');
        $second = $startWorker('second');
        waitFor(fn () => file_exists($signals.'/second-ready'), 'the second addition to start');

        // Confirm an actual database lock wait between independent connections.
        // The first worker stays paused until the second is waiting on its cart.
        waitFor(fn () => (int) $server->query(
            "SELECT COUNT(*) FROM performance_schema.data_lock_waits w "
            ."JOIN performance_schema.data_locks l ON l.ENGINE_LOCK_ID = w.REQUESTING_ENGINE_LOCK_ID "
            ."AND l.ENGINE = w.ENGINE WHERE l.OBJECT_SCHEMA = '".$database."' AND l.OBJECT_NAME = 'carts'"
        )->fetchColumn() > 0, 'the second addition to wait on the cart lock');

        touch($signals.'/release');
        check($first->wait() === 0, 'First worker failed: '.$first->getErrorOutput());
        check($second->wait() === 0, 'Second worker failed: '.$second->getErrorOutput());
        $results = [
            json_decode($first->getOutput(), true, flags: JSON_THROW_ON_ERROR),
            json_decode($second->getOutput(), true, flags: JSON_THROW_ON_ERROR),
        ];
        check($cart->items()->count() === 1, $name.': duplicate cart rows');
        check((int) $cart->items()->sole()->quantity === $expectedQuantity, $name.': incorrect quantity');
        check(count(array_filter($results, fn ($result) => $result['success'])) === $expectedSuccesses, $name.': incorrect success responses');
        echo 'PASS: '.$name.PHP_EOL;
    }
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(0);
        }
    }
    DB::disconnect('cart_concurrency');
    $server->exec('DROP DATABASE `'.$database.'`');
    foreach (glob($signalsRoot.'/*') as $directory) {
        foreach (glob($directory.'/*') as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
    rmdir($signalsRoot);
}
