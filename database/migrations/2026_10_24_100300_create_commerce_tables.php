<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Commerce schema. All money is stored as signed 64-bit integers in minor units
 * (kuruş / cents) of the single store currency; floats are never used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name', 80);
            $table->string('slug', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('summary', 300)->nullable();
            $table->longText('description_markdown')->nullable();
            $table->longText('description_html')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media')->nullOnDelete();
            $table->bigInteger('price_minor');
            $table->bigInteger('compare_at_minor')->nullable(); // shown struck-through, never used for charging
            $table->unsignedInteger('stock')->nullable(); // null = unlimited
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('max_quantity')->default(1);
            $table->boolean('requires_link')->default(true); // needs a verified Minecraft identity
            $table->unsignedInteger('duration_days')->nullable(); // timed entitlement (e.g. monthly rank)
            $table->string('badge', 30)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        // Typed command templates per product, server and lifecycle phase.
        Schema::create('product_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('game_server_id')->constrained()->restrictOnDelete();
            $table->string('phase', 12)->default('purchase'); // purchase | expire | refund
            $table->string('command', 255);
            $table->boolean('require_online')->default(false);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('type', 10); // percent | fixed
            $table->bigInteger('value'); // basis points (1% = 100) or minor units
            $table->bigInteger('min_subtotal_minor')->default(0);
            $table->bigInteger('max_discount_minor')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('max_uses_per_user')->default(1);
            $table->unsignedInteger('uses')->default(0);
            $table->json('category_ids')->nullable();
            $table->json('product_ids')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('kind', 10)->default('purchase'); // purchase | topup
            // Two independent axes: money and delivery.
            $table->string('status', 24)->default('pending_payment')->index();
            $table->string('fulfillment_status', 24)->default('not_started')->index();
            $table->char('currency', 3);
            $table->bigInteger('subtotal_minor');
            $table->bigInteger('discount_minor')->default(0);
            $table->bigInteger('total_minor');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 20)->nullable(); // wallet | stripe | paytr | sandbox
            $table->string('ip', 45)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('admin_note', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name', 120);
            $table->bigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->bigInteger('total_minor');
            $table->unsignedInteger('duration_days')->nullable();
            // Snapshot of the verified identity at purchase time.
            $table->char('player_uuid', 36)->nullable();
            $table->string('player_name', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('coupon_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('discount_minor');
            $table->timestamps();

            $table->unique(['coupon_id', 'order_id']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('provider_reference', 191)->nullable(); // checkout session / merchant oid
            $table->string('provider_payment_id', 191)->nullable();
            $table->bigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 20)->default('created'); // created|pending|succeeded|failed|refunded|cancelled
            $table->string('failure_reason', 255)->nullable();
            $table->json('meta')->nullable(); // redacted provider data
            $table->timestamp('succeeded_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_reference']);
        });

        // Inbox: every verified provider event is stored once (dedup by provider event id).
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20);
            $table->string('event_id', 191);
            $table->string('event_type', 80)->nullable();
            $table->json('payload');
            $table->string('status', 16)->default('received'); // received|processed|ignored|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'event_id']);
        });

        Schema::create('wallets', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained()->cascadeOnDelete();
            $table->bigInteger('balance_minor')->default(0);
            $table->timestamps();
        });

        // Immutable ledger. Balance changes only through a new row; the idempotency key
        // makes every posting at-most-once.
        Schema::create('wallet_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->bigInteger('amount_minor');
            $table->bigInteger('balance_after_minor');
            $table->string('type', 20); // topup|purchase|refund|adjustment|reversal
            $table->string('idempotency_key', 120)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        // Fulfillment outbox consumed by the in-game bridge.
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_command_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('game_server_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phase', 12);
            $table->char('player_uuid', 36)->nullable();
            $table->string('player_name', 32)->nullable();
            $table->string('command', 512);
            $table->boolean('require_online')->default(false);
            $table->string('status', 16)->default('queued')->index(); // queued|leased|delivered|failed|dead|cancelled|manual_review
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(8);
            $table->timestamp('available_at')->useCurrent();
            $table->timestamp('lease_expires_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->string('dedup_key', 191)->unique();
            $table->timestamps();

            $table->index(['game_server_id', 'status', 'available_at']);
        });

        Schema::create('entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('status', 12)->default('active'); // active|expired|revoked
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['entitlements', 'deliveries', 'wallet_entries', 'wallets', 'webhook_events', 'payments',
            'coupon_redemptions', 'order_items', 'orders', 'cart_items', 'coupons', 'product_commands',
            'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
