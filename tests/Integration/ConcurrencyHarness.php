<?php

/**
 * Standalone, destructive-fixture test for the dedicated isolated MariaDB QA DB.
 * Run only inside the QA Linux sandbox after migrations; never on a real store.
 * No PHPUnit transaction spans the child processes. Every child reconnects its PDO.
 */

use App\Core\Settings\Settings;
use App\Domain\Commerce\Checkout;
use App\Domain\Commerce\CommerceException;
use App\Domain\Commerce\Ledger;
use App\Domain\Payments\Gateways\SandboxGateway;
use App\Domain\Payments\VerifiedEvent;
use App\Domain\Payments\WebhookInbox;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Models\WalletEntry;
use App\Models\WebhookEvent;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! function_exists('pcntl_fork') || ! function_exists('pcntl_alarm') || PHP_OS_FAMILY !== 'Linux'
    || config('database.default') !== 'mariadb' || config('database.connections.mariadb.database') !== 'vibecraft_qa'
    || ! str_starts_with((string) config('database.connections.mariadb.unix_socket'), '/scratch/qa/')) {
    fwrite(STDERR, "Concurrency harness requires the dedicated isolated Linux/MariaDB QA profile.\n");
    exit(2);
}

$app->instance('env', 'testing');
config(['app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=', 'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'hashing.bcrypt.rounds' => 4]);
Http::preventStrayRequests();
Queue::fake();
Notification::fake();
app(Settings::class)->setMany(['payments.sandbox.enabled' => true, 'modules.store' => true]);
$run = 'qa_'.bin2hex(random_bytes(5));
$workers = max(2, min(6, (int) (getenv('QA_WORKERS') ?: 4)));
$scratch = '/scratch/qa/'.$run;
if (! mkdir($scratch, 0700)) {
    throw new RuntimeException('Cannot create assigned concurrency scratch.');
}

function invariant(bool $condition, string $description): void
{
    if (! $condition) {
        throw new RuntimeException($description);
    }
}

/** All forks are owned by this short-lived, resource-limited QA process. */
function race(string $name, int $workers, callable $work): array
{
    global $scratch;
    $directory = $scratch.'/'.$name;
    mkdir($directory, 0700);
    DB::purge();
    $children = [];
    for ($index = 0; $index < $workers; $index++) {
        $pid = pcntl_fork();
        if ($pid === -1) {
            throw new RuntimeException('QA fork failed.');
        }
        if ($pid === 0) {
            pcntl_alarm(25);
            $deadline = microtime(true) + 5;
            while (true) {
                clearstatcache(true, $directory.'/go');
                if (is_file($directory.'/go')) {
                    break;
                }
                if (microtime(true) > $deadline) {
                    exit(3);
                }
                usleep(2000);
            }
            try {
                DB::purge();
                DB::reconnect();
                DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
                $result = ['outcome' => $work($index)];
            } catch (CommerceException) {
                $result = ['outcome' => 'rejected'];
            } catch (Throwable $exception) {
                $code = (string) $exception->getCode();
                $info = $exception instanceof PDOException || $exception instanceof Illuminate\Database\QueryException
                    ? ($exception->errorInfo ?? []) : [];
                $state = (string) ($info[0] ?? '');
                $result = ['outcome' => 'error', 'error_type' => get_class($exception),
                    'code' => preg_match('/^[A-Z0-9_-]{1,16}$/D', $code) ? $code : null,
                    'sqlstate' => preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : null,
                    'driver_code' => isset($info[1]) && is_numeric($info[1]) ? (int) $info[1] : null];
            }
            file_put_contents($directory.'/'.$index.'.json', json_encode($result, JSON_THROW_ON_ERROR));
            DB::purge();
            exit(0);
        }
        $children[] = $pid;
    }
    file_put_contents($directory.'/go', 'go');
    $results = [];
    foreach ($children as $index => $pid) {
        pcntl_waitpid($pid, $status);
        invariant(pcntl_wifexited($status) && pcntl_wexitstatus($status) === 0, 'A bounded QA child failed to finish.');
        $file = $directory.'/'.$index.'.json';
        $result = json_decode(file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        $results[] = $result;
        unlink($file);
    }
    unlink($directory.'/go');
    rmdir($directory);
    DB::purge();
    DB::reconnect();
    if (in_array('error', array_column($results, 'outcome'), true)) {
        // Dedicated dummy QA only. Never retain messages, SQL or bindings.
        echo json_encode(['failed_race' => $name, 'children' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    }
    invariant(! in_array('error', array_column($results, 'outcome'), true), 'An unexpected database/application error occurred in '.$name.'.');

    return array_count_values(array_column($results, 'outcome'));
}

function fixtureUser(string $suffix): User
{
    return User::create(['name' => 'qa_'.substr(hash('sha256', $suffix), 0, 20), 'email' => $suffix.'@example.test', 'password' => 'FixturePassword123', 'locale' => 'tr']);
}

function fixtureProduct(Category $category, string $suffix, ?int $stock = 10): Product
{
    return Product::create(['category_id' => $category->id, 'name' => 'QA fixture', 'slug' => $suffix, 'price_minor' => 500,
        'stock' => $stock, 'max_quantity' => 1, 'requires_link' => false, 'is_visible' => true]);
}

$checks = [];
try {
    $walletUser = fixtureUser($run.'_wallet');
    $userId = $walletUser->id;
    $key = $run.':same-credit';
    $counts = race('ledger-same-key', $workers, function () use ($userId, $key) {
        app(Ledger::class)->post($userId, 1000, 'adjustment', $key);

        return 'posted';
    });
    invariant(($counts['posted'] ?? 0) === $workers && app(Ledger::class)->balance($userId) === 1000
        && WalletEntry::where('user_id', $userId)->count() === 1, 'Concurrent duplicate credit moved money more than once.');
    $checks['ledger_same_key'] = $counts;

    $counts = race('ledger-overspend', $workers, function (int $index) use ($run, $userId) {
        app(Ledger::class)->post($userId, -700, 'purchase', $run.':spend:'.$index);

        return 'posted';
    });
    invariant(($counts['posted'] ?? 0) === 1 && ($counts['rejected'] ?? 0) === $workers - 1
        && app(Ledger::class)->balance($userId) === 300, 'Concurrent spends overdrew a wallet.');
    $checks['wallet_overspend'] = $counts;

    $category = Category::create(['name' => 'QA fixtures', 'slug' => $run, 'is_visible' => true]);
    $stockProduct = fixtureProduct($category, $run.'_stock', 1);
    $userIds = [];
    for ($index = 0; $index < $workers; $index++) {
        $user = fixtureUser($run.'_stock_'.$index);
        $userIds[] = $user->id;
        CartItem::create(['user_id' => $user->id, 'product_id' => $stockProduct->id, 'quantity' => 1]);
    }
    $counts = race('checkout-stock', $workers, function (int $index) use ($userIds) {
        app(Checkout::class)->placeOrder(User::findOrFail($userIds[$index]), null, 'sandbox', null);

        return 'ordered';
    });
    invariant(($counts['ordered'] ?? 0) === 1 && Order::whereIn('user_id', $userIds)->count() === 1
        && $stockProduct->fresh()->stock === 0, 'Concurrent checkouts oversold the last stock unit.');
    $checks['checkout_stock'] = $counts;

    $cartUser = fixtureUser($run.'_cart');
    $cartProduct = fixtureProduct($category, $run.'_cart');
    CartItem::create(['user_id' => $cartUser->id, 'product_id' => $cartProduct->id, 'quantity' => 1]);
    $cartUserId = $cartUser->id;
    $counts = race('checkout-same-cart', $workers, function () use ($cartUserId) {
        app(Checkout::class)->placeOrder(User::findOrFail($cartUserId), null, 'sandbox', null);

        return 'ordered';
    });
    invariant(($counts['ordered'] ?? 0) === 1 && Order::where('user_id', $cartUserId)->count() === 1
        && $cartProduct->fresh()->stock === 9, 'A simultaneous checkout consumed the same cart twice.');
    $checks['checkout_same_cart'] = $counts;

    $coupon = Coupon::create(['code' => strtoupper($run), 'type' => 'fixed', 'value' => 100, 'max_uses' => 1, 'is_active' => true]);
    $couponUsers = [];
    for ($index = 0; $index < $workers; $index++) {
        $user = fixtureUser($run.'_coupon_'.$index);
        $product = fixtureProduct($category, $run.'_coupon_'.$index, null);
        $couponUsers[] = $user->id;
        CartItem::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1]);
    }
    $code = $coupon->code;
    $counts = race('checkout-coupon', $workers, function (int $index) use ($couponUsers, $code) {
        app(Checkout::class)->placeOrder(User::findOrFail($couponUsers[$index]), $code, 'sandbox', null);

        return 'ordered';
    });
    invariant(($counts['ordered'] ?? 0) === 1 && $coupon->fresh()->uses === 1
        && Order::whereIn('user_id', $couponUsers)->count() === 1, 'Concurrent checkouts exceeded the coupon usage cap.');
    $checks['coupon_last_use'] = $counts;

    foreach ([true, false] as $sameEvent) {
        $suffix = $sameEvent ? 'same_event' : 'distinct_events';
        $user = fixtureUser($run.'_'.$suffix);
        $order = app(Checkout::class)->createTopup($user, 3000, 'sandbox', null);
        $payment = app(Checkout::class)->createPayment($order, 'sandbox');
        $reference = $payment->provider_reference;
        $providerPaymentId = 'fixture-'.substr(hash('sha256', $reference), 0, 24);
        $eventPrefix = $run.':'.$suffix.':';
        $counts = race('webhook-'.$suffix, $workers, function (int $index) use ($sameEvent, $reference, $providerPaymentId, $eventPrefix) {
            $event = new VerifiedEvent('sandbox', $eventPrefix.($sameEvent ? 'shared' : $index), 'payment.succeeded', VerifiedEvent::SUCCEEDED,
                $reference, $providerPaymentId, 3000, 'TRY');

            return app(WebhookInbox::class)->accept(app(SandboxGateway::class), $event);
        });
        invariant(app(Ledger::class)->balance($user->id) === 3000 && WalletEntry::where('user_id', $user->id)->count() === 1
            && $order->fresh()->status === Order::PAID && WebhookEvent::where('event_id', 'like', $eventPrefix.'%')->count() === ($sameEvent ? 1 : $workers),
            'Concurrent webhook deliveries credited the top-up more than once or lost an event.');
        invariant(($counts['failed'] ?? 0) === 0 && ($counts['ignored'] ?? 0) === 0, 'A valid concurrent webhook was not processed.');
        $checks['webhook_'.$suffix] = $counts;
    }

    rmdir($scratch);
    echo json_encode(['status' => 'passed', 'workers_per_race' => $workers, 'database' => 'dedicated-mariadb-qa', 'checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
} catch (Throwable $exception) {
    $code = (string) $exception->getCode();
    $info = $exception instanceof PDOException || $exception instanceof Illuminate\Database\QueryException
        ? ($exception->errorInfo ?? []) : [];
    $state = (string) ($info[0] ?? '');
    echo json_encode(['status' => 'failed', 'error_type' => get_class($exception),
        'code' => preg_match('/^[A-Z0-9_-]{1,16}$/D', $code) ? $code : null,
        'sqlstate' => preg_match('/^[A-Z0-9]{5}$/D', $state) ? $state : null,
        'driver_code' => isset($info[1]) && is_numeric($info[1]) ? (int) $info[1] : null,
        'invariant' => get_class($exception) === RuntimeException::class ? $exception->getMessage() : null,
        'completed_checks' => $checks], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
    exit(1);
}
