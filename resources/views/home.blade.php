<x-layout>
@foreach($sections as $section)
    @includeIf('home-sections.'.$section->kind)
@endforeach
@if($brandPhotos->isNotEmpty())<section class="section"><div class="section-head"><div><span class="eyebrow">THE BRAND, IN PICTURES</span><h2>From the Clovic journal.</h2><p class="small">Facebook archive · Past promotions may have ended.</p></div><a href="/gallery" class="text-link">All photos ↗</a></div><div class="brand-photo-strip">@foreach($brandPhotos as $photo)<a href="/gallery"><img src="{{ Storage::url($photo->path) }}" alt="{{ $photo->alt }}" width="{{ $photo->width }}" height="{{ $photo->height }}" loading="lazy"></a>@endforeach</div></section>@endif
</x-layout>
