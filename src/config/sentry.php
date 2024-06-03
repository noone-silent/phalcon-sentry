<?php

declare(strict_types=1);

// Uncomment to use it with use ($di) on before send hook.
//$di = $di ?? \Phalcon\Di\Di::getDefault();

return [
    // Enable db query tracing
    'db'      => true,
    // Enable view render tracing
    'view'    => true,
    // Some options for tracing
    'options' => [
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
    ],
];
