<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Sudo mode
    |--------------------------------------------------------------------------
    | The primitives in this package — session window, route middleware,
    | #[RequiresSudo] attribute — are stateless and have no view of how the
    | OTP step-up actually happens. The consumer (nawasara/core) wires the
    | controller, the Keycloak handshake, and the redirect; this package
    | just enforces the resulting window.
    |
    | window_minutes:
    |   How long a successful step-up stays valid before the user is asked
    |   again. Mirrors GitHub's 15 minutes.
    |
    | acr:
    |   The ACR value the integration layer requests and asserts on the
    |   callback. Kept here so any package can read it without hard-coding.
    */
    'sudo' => [
        'window_minutes' => (int) env('NAWASARA_SUDO_WINDOW_MINUTES', 15),
        'acr' => env('NAWASARA_SUDO_ACR', 'sudo'),
    ],
];
