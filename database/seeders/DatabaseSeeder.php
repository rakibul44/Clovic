<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Page;
use App\Models\StoreSetting;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['brand' => 'Clovic', 'announcement' => 'Made for your everyday. Styled your way.', 'delivery_inside' => '8000', 'delivery_outside' => '15000', 'facebook' => 'https://facebook.com/clovicbd', 'instagram' => 'https://instagram.com/clovicbd', 'hero_title' => 'Everyday style.', 'hero_text' => 'Thoughtfully selected fashion. Timeless essentials. A wardrobe that feels like you.', 'hero_second_line' => 'Elevated.', 'brand_intro' => 'Clothes are part of your everyday story. We’re building Clovic around the pieces you reach for again and again — easy to wear, easy to make your own.', 'show_featured' => '1', 'show_offers' => '1', 'navigation' => json_encode([['label' => 'Shop all', 'path' => '/shop'], ['label' => 'New arrivals', 'path' => '/category/new-arrivals'], ['label' => 'Polo shirts', 'path' => '/category/polo-shirts'], ['label' => 'Our story', 'path' => '/about']]), 'restore_stock' => '1', 'low_stock_threshold' => '5', 'seo_title' => 'Clovic | Everyday Style. Elevated.', 'meta_description' => 'Explore everyday clothing and timeless essentials from Clovic, Bangladesh.'] as $key => $value) {
            StoreSetting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
        foreach (['polo-shirts' => 'Polo shirts', 't-shirts' => 'T-shirts', 'casual-shirts' => 'Casual shirts', 'trousers' => 'Trousers', 'jeans' => 'Jeans'] as $slug => $name) {
            Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'description' => 'Explore the Clovic '.$name.' collection.', 'published' => true]);
        }
        $pages = [
            'about' => ['Our story', "Clovic is a clothing brand for everyday style in Bangladesh. We’re building a wardrobe of considered essentials, with room for your own perspective.\n\nDiscover the collection, explore the details, and make it your own."],
            'contact' => ['Let’s talk', 'Have a question about a piece or an order? Message Clovic on Facebook or Instagram at @clovicbd. Include your order reference if you need order support. Never share passwords or payment credentials.'],
            'faq' => ['Frequently asked questions', "How do I place an order?\nChoose a product, select an available size and color, add it to your bag, and complete checkout. Guest checkout is available.\n\nHow do I pay?\nCash on Delivery is the available payment method.\n\nHow do I track my order?\nUse the tracking page with your order reference and the phone number entered at checkout.\n\nHow much is delivery?\nDelivery charges are shown in the bag and recalculated at checkout for your selected area."],
            'shipping-policy' => ['Shipping & delivery', "Clovic currently serves Bangladesh. Choose Inside Dhaka or Outside Dhaka at checkout. The applicable delivery charge is shown before placing the order.\n\nThe store will confirm availability and delivery arrangements. Delivery time estimates and courier service details must be confirmed with Clovic before ordering."],
            'size-guide' => ['Find your fit', 'Choose a product to see its available sizes. Product-specific garment measurements have not yet been supplied. Please contact Clovic for measurements and fit guidance before ordering. Do not infer measurements from size labels alone.'],
            'return-exchange-policy' => ['Returns & exchanges', 'Business policy awaiting approval. Before ordering, contact Clovic to confirm eligibility and time limits for size or color errors, damaged products, customer-requested exchanges, and responsibility for return shipping. No return or refund promise is made by this draft page.'],
            'privacy-policy' => ['Privacy policy', "Draft for business review. The checkout collects the contact and delivery information needed to process an order. Account registration stores your profile and a hashed password. Session cookies support sign-in and the shopping bag. Order tracking requires your reference and matching phone number.\n\nMarketing tracking is inactive. Newsletter preferences are recorded only after consent. The business must publish its approved retention, data access, deletion, service-provider, and contact procedures before launch."],
            'terms-and-conditions' => ['Terms & conditions', "Draft for business review. Product availability and the amount payable are checked when an order is placed. Cash on Delivery payment collection is recorded separately from fulfillment.\n\nThe business must approve and publish its sales terms, cancellation procedures, delivery terms, dispute contact, and return policy before accepting live orders."],
        ];
        foreach ($pages as $slug => [$title,$content]) {
            Page::firstOrCreate(['slug' => $slug], ['title' => $title, 'content' => $content, 'published' => true]);
        }
    }
}
