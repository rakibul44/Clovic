<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Checkout;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class TakaAmountsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        $u = User::factory()->create();
        $u->forceFill(['role' => 'owner'])->save();

        return $u;
    }

    private function payload(): array
    {
        return ['money_unit' => 'BDT', 'name' => 'Taka priced polo', 'slug' => 'taka-priced-polo', 'category_id' => Category::factory()->create()->id, 'description' => 'Actual product', 'price' => '1200.50', 'sale_price' => '1100.25', 'cost_price' => '500.05', 'status' => 'published', 'variants' => [['sku' => 'TAKA-M', 'size' => 'M', 'color' => 'Blue', 'stock' => 3, 'low_stock_threshold' => 1, 'price_override' => '1000.01']]];
    }

    public function test_taka_form_prices_are_exact_and_reopening_does_not_multiply_them(): void
    {
        $this->actingAs($this->owner());
        $data = $this->payload();
        $this->post('/admin/products', $data)->assertSessionHasNoErrors()->assertRedirect();
        $p = Product::firstOrFail();
        $this->assertEquals(120050, $p->price);
        $this->assertEquals(110025, $p->sale_price);
        $this->assertEquals(50005, $p->cost_price);
        $this->assertEquals(100001, $p->variants->first()->price_override);
        $this->get('/admin/products/'.$p->id.'/edit')->assertOk()->assertSee('value="1200.50"', false)->assertSee('value="1000.01"', false)->assertDontSee('paisa');
        $this->post('/admin/products/'.$p->id, $data)->assertSessionHasNoErrors();
        $this->assertEquals(120050, $p->fresh()->price);
        $this->get('/product/'.$p->slug)->assertOk()->assertSee('TK 1,000.01');
        $this->assertSame('TK 1,200.50', Settings::money(120050));
        $this->assertSame('0.01', Settings::decimal(1));
    }

    public function test_invalid_precision_is_rejected_and_failed_form_retains_taka_values(): void
    {
        $this->actingAs($this->owner());
        $data = $this->payload();
        $data['description'] = '';
        $this->from('/admin/products/create')->post('/admin/products', $data)->assertSessionHasErrors('description')->assertSessionHasInput('price', '1200.50');
        $data['description'] = 'Actual description';
        $data['price'] = '1200.005';
        $this->post('/admin/products', $data)->assertSessionHasErrors('price');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_coupon_and_shipping_taka_inputs_produce_exact_checkout_totals(): void
    {
        $this->actingAs($this->owner());
        $this->post('/admin/products', $this->payload())->assertSessionHasNoErrors();
        $p = Product::firstOrFail();
        $this->post('/admin/content/coupons', ['money_unit' => 'BDT', 'code' => 'TK100', 'type' => 'fixed', 'amount' => '100.50', 'minimum' => '500.00', 'maximum_discount' => '100.50', 'usage_limit' => 10, 'active' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('coupons', ['code' => 'TK100', 'amount' => 10050, 'minimum' => 50000, 'maximum_discount' => 10050]);
        $this->post('/admin/shipping/settings', ['money_unit' => 'BDT', 'delivery_inside' => '80.50', 'delivery_outside' => '150.25', 'free_shipping_threshold' => '900.00'])->assertSessionHasNoErrors();
        $this->get('/admin/shipping')->assertOk()->assertSee('value="80.50"', false)->assertDontSee('paisa');
        $order = app(Checkout::class)->place(['name' => 'Test buyer', 'phone' => '01712345678', 'district' => 'Dhaka', 'area' => 'inside', 'address' => 'Test road', 'coupon' => 'TK100', 'idempotency_key' => (string) Str::uuid()], [$p->variants->first()->id => 1], 'test');
        $this->assertEquals(100001, $order->subtotal);
        $this->assertEquals(10050, $order->discount);
        $this->assertEquals(8050, $order->delivery);
        $this->assertEquals(98001, $order->total);
    }

    public function test_website_settings_accept_taka_and_csv_exports_use_bdt_columns(): void
    {
        $this->seed();
        $this->actingAs($this->owner());
        $data = StoreSetting::pluck('value', 'key')->all();
        $data['money_unit'] = 'BDT';
        $data['delivery_inside'] = '80.50';
        $data['delivery_outside'] = '150.25';
        $this->post('/admin/settings', $data)->assertSessionHasNoErrors();
        $this->assertSame('8050', StoreSetting::where('key', 'delivery_inside')->value('value'));
        $this->get('/admin/settings')->assertOk()->assertSee('value="80.50"', false)->assertDontSee('paisa');
        $this->post('/admin/products', $this->payload())->assertSessionHasNoErrors();
        $csv = $this->get('/admin/products/export')->assertOk()->streamedContent();
        $this->assertStringContainsString('price_bdt,sale_price_bdt', $csv);
        $this->assertStringContainsString('1200.50,1100.25', $csv);
        $this->post('/admin/products/import', ['file' => UploadedFile::fake()->createWithContent('bdt.csv', $csv)])->assertSessionHasNoErrors();
        $this->assertEquals(120050, Product::where('slug', 'taka-priced-polo')->value('price'));
        $report = $this->get('/admin/reports/export?kind=inventory')->assertOk()->streamedContent();
        $this->assertStringContainsString('cost_bdt,value_bdt',$report);
        $this->assertStringNotContainsString('paisa',$report);
        $this->assertStringContainsString('500.05,1500.15',$report);
    }
}
