<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $page['props']['site']['language'] ?? app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex, nofollow">
        <title inertia>Craft 6 Inertia proof of concept</title>
        @php($assets = \Robuust\CraftInertiaPoc\Plugin::getInstance()->vite())
        {!! $assets('resources/js/app.ts') !!}
        @inertiaHead
    </head>
    <body>
        @inertia
        <noscript>This proof of concept requires JavaScript.</noscript>
    </body>
</html>
