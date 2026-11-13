<?php

declare(strict_types=1);

return [
    'rootView' => 'app',

    // Optional callable(PublicPageContext): array<string, mixed>; additive native page props.
    'publicProps' => null,

    // Ordered named {matches: callable(FieldContext): bool, serialize: callable(FieldContext): mixed}.
    'fieldSerializers' => [],

    // Named {transform: native handle|array, widths: list<int>}; no profiles apply automatically.
    'imageProfiles' => [],

    // Optional callable(Request, Entry): bool; may only restrict built-in public eligibility.
    'publicPagePolicy' => null,

    // Optional callable(Request): bool; indexing also requires public guest eligibility in the shared renderer.
    'indexingPolicy' => null,
];
