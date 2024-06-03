# Phalcon Sentry

[![Packagist Version](https://img.shields.io/packagist/v/noone-silent/phalcon-sentry)]
(https://packagist.org/packages/noone-silent/phalcon-sentry)

This directory contains the PHP source code for the Phalcon Sentry integration.

## Requirements

- PHP 8.0 or higher
- [Composer](https://getcomposer.org/)
- Phalcon Framework in version 5.0.0 or higher

## Documentation

## Installation

```Bash
composer install noone-silent/phalcon-sentry
```

## Usage

Create a copy of the sentry configuration found under `config/sentry.php`.

> [!TIP]
> It is recommended, that you set your events manager before setting the service provider.

Add the following code to your entry script:

```php
<?php
$di->register(
    new Phalcon\Sentry\ServiceProvider(
        $pathTo . '/config/sentry.php'
    )
);
```

If you're using the Volt template engine, you can use the provided helper to place scripts and tags.

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
    ['new Sentry.BrowserTracing()'],
    static_url('/js/bundle.tracing.min.js')
) }}
```
