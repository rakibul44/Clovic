@props(['title'=>null,'description'=>null,'canonical'=>null,'noindex'=>false,'image'=>null,'ogTitle'=>null,'ogDescription'=>null])
@php
    $brand=$store['brand']??'Clovic';
    $title=$title??($store['seo_title']??'Clovic | Everyday Style. Elevated.');
    $description=$description??($store['meta_description']??'Thoughtfully selected clothing and everyday essentials from Clovic, Bangladesh.');
    $canonical=$canonical??url()->current();
    $image=$image??(!empty($store['og_image'])?url(Storage::url($store['og_image'])):asset('assets/social.svg'));
    $private=request()->is('account*','cart*','checkout*','order*','track-order','login','register','forgot-password','reset-password*','wishlist');
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }}</title><meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}"><meta name="robots" content="{{ $noindex||$private||($store['indexing']??'normal')==='discourage'?'noindex,follow':'index,follow' }}">
    <meta property="og:type" content="website"><meta property="og:title" content="{{ $ogTitle??(request()->is('/')?($store['og_title']??$title):$title) }}"><meta property="og:description" content="{{ $description }}"><meta property="og:url" content="{{ $canonical }}"><meta property="og:image" content="{{ $image }}">
    <meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="{{ $title }}"><meta name="twitter:description" content="{{ $description }}"><meta name="twitter:image" content="{{ $image }}">
    <link rel="icon" type="image/svg+xml" href="{{ !empty($store['favicon'])?Storage::url($store['favicon']):'/assets/favicon.svg' }}"><meta name="theme-color" content="#f8f7f4">
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script type="application/ld+json">{!! json_encode(['@'.'context'=>'https://schema.org','@type'=>'Organization','name'=>$brand,'url'=>url('/'),'sameAs'=>array_values(array_filter([$store['facebook']??null,$store['instagram']??null]))],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_SLASHES) !!}</script>
    <script type="application/ld+json">{!! json_encode(['@'.'context'=>'https://schema.org','@type'=>'WebSite','name'=>$brand,'url'=>url('/')],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    <script id="storefront-state" type="application/json">{!! json_encode(['cart'=>$cartState,'wishlist'=>$wishlistIds],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
    {{ $head??'' }}
</head>
<body x-data="storefront" @keydown.escape.window="closePanels()" @open-bag.window="openBag()">
    <a class="skip" href="#main">Skip to content</a>
    <div class="site-chrome">
        <div class="announcement" x-show="announcement" x-cloak>
            <span>{{ $store['announcement']??'Everyday essentials. Delivery across Bangladesh.' }}</span>
            <a href="/shipping-policy">Delivery information <span aria-hidden="true">↗</span></a>
            <button @click="dismissAnnouncement()" aria-label="Dismiss announcement"><i data-lucide="x"></i></button>
        </div>
        <header class="header">
            <button class="icon-button mobile-menu-trigger" @click="openMenu()" :aria-expanded="mobile" aria-controls="mobile-menu" aria-label="Open navigation"><i data-lucide="menu"></i></button>
            <x-brand-mark/>
            <nav class="desktop-nav" aria-label="Main navigation">
                <a href="/shop" @class(['active'=>request()->is('shop')])>Shop</a>
                <a href="/category/new-arrivals">New arrivals</a>
                <div class="category-navigation" @click.outside="categories=false">
                    <button @click="categories=!categories" :aria-expanded="categories" aria-controls="category-menu">Categories <i data-lucide="chevron-down"></i></button>
                    <div id="category-menu" class="mega-menu" x-show="categories" x-cloak>
                        <div><span class="eyebrow">FIND YOUR EVERYDAY</span><h2>A place for every piece.</h2><a href="/shop" class="text-link">Explore all clothing ↗</a></div>
                        <div class="mega-links">@foreach($navigationCategories as $category)<a href="/category/{{ $category->slug }}">{{ $category->name }} <span>↗</span></a>@endforeach<a href="/category/new-arrivals">New arrivals ↗</a><a href="/category/sale">Current offers ↗</a>@foreach($customNavigation as $link)<a href="{{ $link['path'] }}">{{ $link['label'] }} ↗</a>@endforeach</div>
                    </div>
                </div>
                <a href="/category/sale">Sale</a>
            </nav>
            <button class="header-search" @click="openSearch()" :aria-expanded="search" aria-controls="search-panel"><i data-lucide="search"></i><span>Search your next essential</span><kbd>/</kbd></button>
            <div class="header-actions">
                <button class="icon-button search-mobile" @click="openSearch()" aria-label="Search products"><i data-lucide="search"></i></button>
                <a class="icon-button account-desktop" href="/account" aria-label="Your account"><i data-lucide="user-round"></i></a>
                <a class="icon-button wishlist-desktop" href="/wishlist" aria-label="Your wishlist"><i data-lucide="heart"></i></a>
                <a href="/cart" x-ref="openBag" @click.prevent="openBag()" class="icon-button bag" aria-label="Open shopping bag" :aria-expanded="bag"><i data-lucide="shopping-bag"></i><span class="bag-count" x-text="$store.cart.count">{{ $cartState['count'] }}</span></a>
            </div>
        </header>
        <section id="search-panel" class="search-panel" x-cloak x-show="search" @click.outside="search=false">
            <div class="search-container">
                <div class="section-head"><span class="eyebrow">A GOOD PLACE TO START</span><button class="icon-button" @click="search=false" aria-label="Close search"><i data-lucide="x"></i></button></div>
                <form action="/shop" class="search-form"><label class="sr-only" for="site-search">Search by product name or SKU</label><div class="search-row"><i data-lucide="search"></i><input id="site-search" x-ref="searchInput" x-model="query" @input.debounce.250ms="suggest()" name="q" type="search" maxlength="100" placeholder="Try a polo, a size, or a product SKU…" autocomplete="off"><button type="button" class="icon-button" x-show="query" @click="clearSearch()" aria-label="Clear search">×</button><button class="button">Search ↗</button></div></form>
                <p class="small" x-show="loading" role="status">Finding your essentials…</p>
                <div class="search-suggestions"><template x-for="item in suggestions" :key="item.id"><a :href="'/product/'+item.slug"><img :src="item.image" alt="" width="48" height="60"><span x-text="item.name"></span><strong x-text="item.price"></strong><span aria-hidden="true">↗</span></a></template></div>
                <p class="search-empty" x-show="query.length>=2&&!loading&&searched&&!suggestions.length">No matches yet. Try “shirt”, check the SKU, or <a href="/shop">explore all clothing</a>.</p>
                <div class="search-shortcuts" x-show="query.length<2"><span class="small">Explore the essentials</span>@foreach($navigationCategories->take(5) as $category)<a href="/category/{{ $category->slug }}">{{ $category->name }} ↗</a>@endforeach</div>
            </div>
        </section>
    </div>
    <div id="mobile-menu" class="drawer-backdrop mobile-menu-backdrop" x-cloak x-show="mobile" @click.self="mobile=false"><nav x-ref="menuDialog" class="mobile-menu" role="dialog" aria-modal="true" aria-label="Shop navigation" @keydown.tab="window.trapDialogFocus($event,$el)"><div class="section-head"><x-brand-mark/><button x-ref="closeMenu" class="icon-button" @click="mobile=false" aria-label="Close navigation">×</button></div><span class="eyebrow">THE CLOVIC WARDROBE</span><a href="/shop">Shop all <span>↗</span></a><a href="/category/new-arrivals">New arrivals <span>↗</span></a>@foreach($navigationCategories as $category)<a href="/category/{{ $category->slug }}">{{ $category->name }} <span>↗</span></a>@endforeach<a href="/category/sale">Sale <span>↗</span></a><div class="mobile-menu-help">@foreach($customNavigation as $link)<a href="{{ $link['path'] }}">{{ $link['label'] }} ↗</a>@endforeach<a href="/track-order">Track an order</a><a href="/contact">Get in touch</a></div></nav></div>
    <x-cart-drawer/>
    @if(\App\Services\AdminAccess::staff(auth()->user()))<div class="admin-link"><a href="/admin">Manage your store ↗</a></div>@endif
    @if(session('success'))<div role="status" class="notice success">{{ session('success') }}</div>@endif
    @if($errors->any())<div role="alert" class="notice error"><strong>Please check your details.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <main id="main">{{ $slot }}</main>
    <section class="newsletter"><div><span class="eyebrow">THE CLOVIC LETTER</span><h2>A little style in your inbox.</h2><p>New collections and fresh perspectives. At your invitation.</p></div><form action="/newsletter" method="post">@csrf<label class="sr-only" for="newsletter-email">Email address</label><div class="subscribe-row"><input id="newsletter-email" name="email" type="email" placeholder="Your email address" required><button aria-label="Subscribe to the Clovic letter">↗</button></div><label class="check"><input type="checkbox" name="consent" value="1" required> I agree to receive emails. <a href="/privacy-policy">Privacy policy</a></label></form></section>
    <footer><div class="footer-top"><div><x-brand-mark/><p>{{ $store['brand_description']??'Everyday style. A little more considered. From Bangladesh, with intention.' }}</p><div class="socials"><a href="{{ $store['instagram']??'https://instagram.com/clovicbd' }}" aria-label="Instagram"><i data-lucide="instagram"></i></a><a href="{{ $store['facebook']??'https://facebook.com/clovicbd' }}" aria-label="Facebook"><i data-lucide="facebook"></i></a></div></div><div><h3>Explore</h3><a href="/shop">All clothing</a><a href="/category/new-arrivals">New arrivals</a><a href="/about">Our story</a><a href="/blog">Journal</a></div><div><h3>Customer care</h3><a href="/contact">Contact us</a><a href="/track-order">Track an order</a><a href="/shipping-policy">Shipping & delivery</a><a href="/return-exchange-policy">Returns & exchanges</a><a href="/size-guide">Size guide</a><a href="/faq">FAQs</a></div><div><h3>Let’s talk</h3>@if(!empty($store['business_address']))<p>{{ $store['business_address'] }}</p>@endif @if(!empty($store['email']))<a href="mailto:{{ $store['email'] }}">{{ $store['email'] }}</a>@endif @if(!empty($store['phone']))<a href="tel:{{ $store['phone'] }}">{{ $store['phone'] }}</a>@endif @if(!empty($store['whatsapp']))<a href="{{ $store['whatsapp'] }}">WhatsApp ↗</a>@endif<a href="{{ $store['facebook']??'https://facebook.com/clovicbd' }}">Message Clovic ↗</a><span class="payment-label"><i data-lucide="banknote"></i> Cash on Delivery</span></div></div><div class="footer-bottom"><span>© {{ date('Y') }} {{ $brand }}. All rights reserved.</span><div><a href="/privacy-policy">Privacy</a><a href="/terms-and-conditions">Terms</a><span>Bangladesh · BDT ৳</span></div></div></footer>
    <nav class="bottom-nav" aria-label="Mobile shortcuts">@foreach(['/' => ['house','Home'],'/shop'=>['layout-grid','Shop'],'/wishlist'=>['heart','Wishlist'],'/account'=>['user-round','Account']] as $url=>$item)<a href="{{ $url }}" @class(['active'=>request()->path()===trim($url,'/')||($url==='/'&&request()->is('/'))])><i data-lucide="{{ $item[0] }}"></i><span>{{ $item[1] }}</span></a>@endforeach<a href="/cart" @click.prevent="openBag()"><i data-lucide="shopping-bag"></i><span>Bag <span x-text="$store.cart.count">{{ $cartState['count'] }}</span></span></a></nav>
    <div class="toast" x-cloak x-show="$store.cart.message" role="status" aria-live="polite"><span x-text="$store.cart.message"></span><button @click="openBag();$store.cart.message=''">View bag ↗</button><button class="icon-button" @click="$store.cart.message=''" aria-label="Dismiss notification">×</button></div>
</body></html>
