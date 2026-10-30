<?php

namespace Tests;

use App\Core\Install\Installation;
use App\Core\Security\Permissions;
use App\Core\Settings\Settings;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected string $testScratch;

    protected function setUp(): void
    {
        parent::setUp();

        // Every fixture gets its own installation marker. The developer's actual
        // installation lock/token and database are never used by these tests.
        $this->testScratch = storage_path('framework/testing/vibecraft-'.getmypid().'-'.bin2hex(random_bytes(6)));
        if (! is_dir($this->testScratch)) {
            mkdir($this->testScratch, 0700, true);
        }
        config([
            'app.key' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
            'app.url' => 'https://vibecraft.example',
            'app.debug' => false,
            'vibecraft.install_lock' => $this->testScratch.'/installed.lock',
            'vibecraft.setup_token_file' => $this->testScratch.'/setup-token.sha256',
            'vibecraft.store.currency' => 'TRY',
            'vibecraft.proxy.mode' => 'none',
        ]);
        URL::forceRootUrl('https://vibecraft.example');
        URL::forceScheme('https');
        Installation::forget();
        Installation::markInstalled();
        app(Settings::class)->flush();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (Permissions::ALL as $name => $label) {
            Permission::findOrCreate($name, 'web');
        }
        foreach (Permissions::ROLES as $name => $grants) {
            Role::findOrCreate($name, 'web')->syncPermissions($grants === '*' ? array_keys(Permissions::ALL) : $grants);
        }
        Http::preventStrayRequests();
        Notification::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        if (isset($this->testScratch)) {
            foreach (['installed.lock', 'setup-token.sha256'] as $file) {
                $path = $this->testScratch.'/'.$file;
                if (is_file($path)) {
                    unlink($path);
                }
            }
            if (is_dir($this->testScratch)) {
                rmdir($this->testScratch);
            }
        }
        Installation::forget();
        parent::tearDown();
    }

    protected function customer(string $role = 'member'): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    protected function product(array $overrides = []): Product
    {
        $category = Category::create(['name' => 'Survival', 'slug' => 'survival-'.Str::lower(Str::random(8)), 'is_visible' => true]);

        return Product::create(array_replace([
            'category_id' => $category->id, 'name' => 'Explorer rank', 'slug' => 'explorer-'.Str::lower(Str::random(8)),
            'price_minor' => 1999, 'stock' => 10, 'max_quantity' => 5, 'requires_link' => false, 'is_visible' => true,
        ], $overrides));
    }

    protected function pendingOrder(User $user, int $amount = 1000, string $provider = 'sandbox', string $kind = 'topup'): Order
    {
        return Order::create([
            'user_id' => $user->id, 'kind' => $kind, 'status' => Order::PENDING, 'fulfillment_status' => 'not_started',
            'currency' => 'TRY', 'subtotal_minor' => $amount, 'total_minor' => $amount, 'payment_method' => $provider,
            'expires_at' => now()->addHour(),
        ]);
    }

    protected function enableSandbox(): void
    {
        app(Settings::class)->set('payments.sandbox.enabled', true);
    }
}
