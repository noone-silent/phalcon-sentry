<?php

declare(strict_types=1);

// Uncomment to use it with use ($di) on before send hook.
//$di = $di ?? \Phalcon\Di\Di::getDefault();

return [
    // Enable cache tracing
    'cache'   => true,
    // Enable db query tracing
    'db'      => true,
    // Enable view render tracing
    'view'    => true,
    // Some options for tracing
    'options' => [
        'cache'      => [
            'backtrace' => true,
            'services'  => [
                'cache',
            ],
        ],
        'db'         => [
            'backtrace' => true,
        ],
        // Change the name of the helper assigned to your Volt templates.
        'helperName' => 'sentryHelper',
    ],
    // Default sentry options for \Sentry\init()
    'sentry'  => [
        'options' => [
            'dsn' => null,
            /*'before_send' => static function (\Sentry\Event $event) use ($di) {
                return $event;
            },*/
        ],
        // Options for a different setup for the frontend part (e.g. Volt)
        'view'    => [
            'dsn' => null,
        ],
        'browser' => [
            // You can find all possible scripts here: https://docs.sentry.io/platforms/javascript/install/loader/#cdn
            'script' => 'https://browser.sentry-cdn.com/8.7.0/bundle.tracing.replay.feedback.min.js',
            // Should the sha of the above script be added to the script?
            'addSha' => true,
            // Set the sha value of the script here.
            'sha'    => 'sha384-EdTlDs1y0B2z6oDPxEhsi9MkH/ilAGCs4oLmreRceSbJ2TlSjo5020c315FWNIYJ',
        ],
    ],
];
