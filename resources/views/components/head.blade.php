@props(['page'])

<x-inertia::head>
    @php($metadata = $page['props']['head'])
    <title data-inertia="">{{ $metadata['title'] }}</title>
    @foreach($metadata['meta'] as $index => $tag)
        <meta data-inertia="meta-{{ $index }}" {{ new \Illuminate\View\ComponentAttributeBag($tag) }}>
    @endforeach
    @foreach($metadata['link'] as $index => $tag)
        <link data-inertia="link-{{ $index }}" {{ new \Illuminate\View\ComponentAttributeBag($tag) }}>
    @endforeach
    @foreach($metadata['jsonLd'] as $script)
        <script data-inertia="jsonld-{{ $script['key'] }}" type="application/ld+json">{!! $script['content'] !!}</script>
    @endforeach
</x-inertia::head>
