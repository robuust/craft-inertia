<?php

declare(strict_types=1);

return [
    'rootView' => 'app',

    // Optional callable(Request, Entry): bool; may only restrict built-in public eligibility.
    'publicPagePolicy' => null,

    // Optional callable(Request): bool; indexing also requires public guest eligibility in the shared renderer.
    'indexingPolicy' => null,
];
