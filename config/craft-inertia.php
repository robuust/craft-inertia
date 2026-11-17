<?php

declare(strict_types=1);

return [
    'rootView' => 'app',

    // Optional callable(PublicPageContext): array<string, mixed>; additive native page props.
    // Use [ProjectClass::class, 'publicStaticMethod'] for config:cache; never store closures or instances.
    'publicProps' => null,

    // Optional callable(PublicPageContext): array{title?: string, meta?: array, link?: array, jsonLd?: array}.
    // Supplies project metadata; the plugin retains response/preview/robots policy and Head rendering.
    'head' => null,

    // Entry-type handle => public field handles; '*' supplies the fallback for every related entry too.
    // Empty config retains all layout fields. Use ['*' => []] when migrating a selected-field consumer.
    'entryFields' => [],

    // Ordered named {matches: callable(FieldContext): bool, serialize: callable(FieldContext): mixed}.
    'fieldSerializers' => [],

    // Named {transform: native handle|array, widths: list<int>}; no profiles apply automatically.
    'imageProfiles' => [],

    // Entry-type handle => Assets field handle => profile names; '*' is an optional fallback at either level.
    'fieldImageProfiles' => [],

    // Optional callable(Request, Entry): bool; may only restrict built-in public eligibility.
    'publicPagePolicy' => null,

    // Optional callable(Request): bool; indexing also requires public guest eligibility in the shared renderer.
    'indexingPolicy' => null,
];
