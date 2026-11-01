<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $t) {
            $t->softDeletes();
            $t->text('short_description')->nullable();
            $t->string('brand')->default('Clovic');
            $t->unsignedBigInteger('cost_price')->nullable();
            $t->boolean('bestseller')->default(true);
            $t->string('canonical_url')->nullable();
        });
        Schema::table('categories', function (Blueprint $t) {
            $t->foreignId('parent_id')->nullable()->constrained('categories')->restrictOnDelete();
            $t->unsignedInteger('position')->default(0)->index();
            $t->string('canonical_url')->nullable();
        });
        Schema::table('product_variants', function (Blueprint $t) {
            $t->unsignedBigInteger('price_override')->nullable();
            $t->foreignId('image_id')->nullable()->constrained('product_images')->nullOnDelete();
            $t->string('color_hex', 7)->nullable();
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->unsignedBigInteger('unit_cost')->nullable());
        Schema::table('orders', function (Blueprint $t) {
            $t->text('public_note')->nullable();
            $t->index(['payment_status', 'created_at']);
            $t->index(['area', 'created_at']);
        });
        Schema::table('shipping_records', function (Blueprint $t) {
            $t->string('status')->default('pending');
            $t->date('dispatched_at')->nullable();
            $t->date('estimated_at')->nullable();
            $t->text('notes')->nullable();
        });
        Schema::table('users', fn (Blueprint $t) => $t->text('service_notes')->nullable());
        Schema::table('coupons', function (Blueprint $t) {
            $t->string('type')->default('percent');
            $t->unsignedBigInteger('amount')->default(0);
            $t->timestamp('starts_at')->nullable();
            $t->unsignedBigInteger('maximum_discount')->nullable();
            $t->unsignedInteger('per_customer_limit')->nullable();
            $t->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('category_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::table('coupon_redemptions', function (Blueprint $t) {
            $t->string('customer_key', 64)->nullable();
            $t->unsignedBigInteger('discount')->default(0);
            $t->index(['coupon_id', 'customer_key']);
        });
        Schema::table('reviews', fn (Blueprint $t) => $t->text('reply')->nullable());
        Schema::table('pages', function (Blueprint $t) {
            $t->string('canonical_url')->nullable();
            $t->string('og_title')->nullable();
            $t->text('og_description')->nullable();
            $t->string('og_image')->nullable();
        });
        Schema::create('homepage_sections', function (Blueprint $t) {
            $t->id();
            $t->string('kind')->unique();
            $t->string('title')->nullable();
            $t->text('content')->nullable();
            $t->string('link_label')->nullable();
            $t->string('link_path')->nullable();
            $t->string('image')->nullable();
            $t->string('mobile_image')->nullable();
            $t->string('image_alt')->nullable();
            $t->boolean('published')->default(true);
            $t->unsignedInteger('position')->default(0)->index();
            $t->timestamps();
        });
        foreach (['hero', 'categories', 'new_arrivals', 'best_sellers', 'brand', 'featured', 'offers', 'benefits', 'faq', 'testimonials', 'campaign'] as $position => $kind) {
            DB::table('homepage_sections')->insert(['kind' => $kind, 'position' => $position, 'published' => ! in_array($kind, ['faq', 'testimonials', 'campaign']), 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('admin_notification_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('event_key');
            $t->timestamp('read_at');
            $t->unique(['user_id', 'event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_receipts');
        Schema::dropIfExists('homepage_sections');
        Schema::table('pages', fn (Blueprint $t) => $t->dropColumn(['canonical_url', 'og_title', 'og_description', 'og_image']));
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn('reply'));
        // Preserve a standalone foreign-key index before dropping the composite.
        Schema::table('coupon_redemptions', fn (Blueprint $t) => $t->index('coupon_id', 'coupon_redemptions_coupon_rollback_index'));
        Schema::table('coupon_redemptions', function (Blueprint $t) {
            $t->dropIndex(['coupon_id', 'customer_key']);
            $t->dropColumn(['customer_key', 'discount']);
        });
        Schema::table('coupons', function (Blueprint $t) {
            $t->dropConstrainedForeignId('product_id');
            $t->dropConstrainedForeignId('category_id');
            $t->dropColumn(['type', 'amount', 'starts_at', 'maximum_discount', 'per_customer_limit']);
        });
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('service_notes'));
        Schema::table('shipping_records', fn (Blueprint $t) => $t->dropColumn(['status', 'dispatched_at', 'estimated_at', 'notes']));
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['payment_status', 'created_at']);
            $t->dropIndex(['area', 'created_at']);
            $t->dropColumn('public_note');
        });
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('unit_cost'));
        Schema::table('product_variants', function (Blueprint $t) {
            $t->dropConstrainedForeignId('image_id');
            $t->dropColumn(['price_override', 'color_hex']);
        });
        Schema::table('categories', function (Blueprint $t) {
            $t->dropConstrainedForeignId('parent_id');
            $t->dropIndex(['position']);
            $t->dropColumn(['position', 'canonical_url']);
        });
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['deleted_at', 'short_description', 'brand', 'cost_price', 'bestseller', 'canonical_url']));
    }
};
