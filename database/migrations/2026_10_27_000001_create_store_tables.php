<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('customer');
            $t->boolean('active')->default(true);
            $t->string('phone')->nullable();
        });
        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('image')->nullable();
            $t->boolean('published')->default(false);
            $t->string('seo_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamps();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('category_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('slug')->unique();
            $t->text('description');
            $t->text('fabric')->nullable();
            $t->text('fit')->nullable();
            $t->text('care')->nullable();
            $t->unsignedBigInteger('price');
            $t->unsignedBigInteger('sale_price')->nullable();
            $t->string('status')->default('draft')->index();
            $t->boolean('featured')->default(false);
            $t->boolean('new_arrival')->default(false);
            $t->boolean('demo')->default(false);
            $t->string('seo_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->string('og_image')->nullable();
            $t->boolean('noindex')->default(false);
            $t->timestamps();
        });
        Schema::create('product_images', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->string('path');
            $t->string('alt');
            $t->unsignedInteger('position')->default(0);
            $t->timestamps();
        });
        Schema::create('product_variants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_id')->constrained()->restrictOnDelete();
            $t->string('sku')->unique();
            $t->string('size', 30);
            $t->string('color', 50);
            $t->unsignedInteger('stock')->default(0);
            $t->unsignedInteger('low_stock_threshold')->default(5);
            $t->timestamps();
            $t->unique(['product_id', 'size', 'color']);
        });
        Schema::create('inventory_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->integer('quantity');
            $t->string('reason');
            $t->string('reference')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('carts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity');
            $t->timestamps();
            $t->unique(['cart_id', 'product_variant_id']);
        });
        Schema::create('wishlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('wishlist_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('wishlist_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['wishlist_id', 'product_id']);
        });
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('phone');
            $t->string('district');
            $t->text('address');
            $t->timestamps();
        });
        Schema::create('coupons', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->unsignedInteger('percent');
            $t->unsignedBigInteger('minimum')->default(0);
            $t->unsignedInteger('usage_limit')->default(1);
            $t->unsignedInteger('used')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('active')->default(false);
            $t->timestamps();
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->string('reference')->unique();
            $t->string('idempotency_key', 64)->unique();
            $t->string('checkout_owner', 64);
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('name');
            $t->string('phone')->index();
            $t->string('email')->nullable();
            $t->string('district');
            $t->string('area');
            $t->text('address');
            $t->text('notes')->nullable();
            $t->string('status')->default('pending')->index();
            $t->string('payment_status')->default('unpaid');
            $t->string('currency', 3)->default('BDT');
            $t->unsignedBigInteger('subtotal');
            $t->unsignedBigInteger('delivery');
            $t->unsignedBigInteger('discount')->default(0);
            $t->unsignedBigInteger('total');
            $t->timestamp('restocked_at')->nullable();
            $t->text('internal_notes')->nullable();
            $t->timestamps();
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->string('product_name');
            $t->string('sku');
            $t->string('size');
            $t->string('color');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price');
            $t->unsignedBigInteger('discount')->default(0);
            $t->timestamps();
        });
        Schema::create('order_status_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status');
            $t->timestamps();
        });
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->string('method')->default('cod');
            $t->string('status')->default('unpaid');
            $t->unsignedBigInteger('amount');
            $t->string('provider_reference')->nullable()->unique();
            $t->timestamps();
        });
        Schema::create('shipping_records', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('courier');
            $t->string('tracking_number');
            $t->timestamps();
        });
        Schema::create('coupon_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('coupon_id')->constrained()->restrictOnDelete();
            $t->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $t->timestamps();
        });
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_item_id')->unique()->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->text('body');
            $t->boolean('approved')->default(false);
            $t->timestamps();
        });
        Schema::create('blog_categories', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->timestamps();
        });
        Schema::create('blog_posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('blog_category_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title');
            $t->string('slug')->unique();
            $t->string('author');
            $t->text('excerpt')->nullable();
            $t->longText('content');
            $t->string('image')->nullable();
            $t->string('seo_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamp('published_at')->nullable()->index();
            $t->timestamps();
        });
        Schema::create('pages', function (Blueprint $t) {
            $t->id();
            $t->string('title');
            $t->string('slug')->unique();
            $t->longText('content');
            $t->boolean('published')->default(false);
            $t->string('seo_title')->nullable();
            $t->text('meta_description')->nullable();
            $t->timestamps();
        });
        Schema::create('store_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('admin_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action');
            $t->string('subject');
            $t->json('changes')->nullable();
            $t->timestamps();
        });
        Schema::create('slug_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('old_path')->unique();
            $t->string('new_path');
            $t->timestamps();
        });
        Schema::create('newsletter_subscribers', function (Blueprint $t) {
            $t->id();
            $t->string('email')->unique();
            $t->timestamp('consented_at');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['newsletter_subscribers', 'slug_redirects', 'admin_audit_logs', 'store_settings', 'pages', 'blog_posts', 'blog_categories', 'reviews', 'coupon_redemptions', 'shipping_records', 'payments', 'order_status_histories', 'order_items', 'orders', 'coupons', 'addresses', 'wishlist_items', 'wishlists', 'cart_items', 'carts', 'inventory_movements', 'product_variants', 'product_images', 'products', 'categories'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['role', 'active', 'phone']));
    }
};
