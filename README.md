# Phalcon Sentry

![Packagist Version](https://img.shields.io/packagist/v/noone-silent/phalcon-sentry)

This directory contains the PHP source code for the Phalcon Sentry integration.

## Requirements

- [PHP 8.0](https://www.php.net/) or higher
- [Composer](https://getcomposer.org/)
- [Phalcon Framework](https://phalcon.io) in version 5.0.0 or higher

## Installation

```Bash
composer require noone-silent/phalcon-sentry
```

## Usage

Create a copy of the sentry configuration found under `config/sentry.php`.

> [!TIP]
> It is recommended, that you set your events manager before setting the service provider.

```php
$di->set('eventsManager', new \Phalcon\Events\Manager(), true);
```

Add the following code to your entry script:

```php
$di->register(
    new \Phalcon\Sentry\ServiceProvider(
        $pathTo . '/config/sentry.php'
    )
);
```

If you're using the Volt template engine, you can use the provided helper to place scripts and tags.
The name of `sentryHelper` is configurable in `config/sentry.php`.

```php
// Add this between your <head></head>
{{ sentryHelper.getMetaTag() }}
```

```php
// Add this right before your </body>
{{ sentryHelper.getScript() }}
```

You can also pass options to the `getScript()` helper:

```php
{{ sentryHelper.getScript(
    [
        'Sentry.browserTracingIntegration()',
        'Sentry.replayIntegration()',
        'Sentry.feedbackIntegration()'
    ],
    static_url('/js/bundle.tracing.replay.feedback.min.js')
) }}
```

> [!NOTICE]
> If you do not self-host the sentry browser script,
> you should add `browser.sentry-cdn.com` to your allowed `script-src`.
>
> You can read more about this here: [Sentry Content Security Policy](https://docs.sentry.io/platforms/javascript/install/loader/#content-security-policy)
