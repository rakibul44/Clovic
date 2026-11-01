<x-layout>
@foreach($sections as $section)
    @includeIf('home-sections.'.$section->kind)
@endforeach
</x-layout>
