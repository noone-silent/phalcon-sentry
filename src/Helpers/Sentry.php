<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Helpers;

use Phalcon\Config\Config;
use Phalcon\Di\AbstractInjectionAware;
use Phalcon\Di\DiInterface;
use Phalcon\Mvc\View;
use Phalcon\Mvc\View\Engine\Volt;
use Throwable;

use function Sentry\getBaggage;
use function Sentry\getTraceparent;

class Sentry extends AbstractInjectionAware
{
    public const DEFAULT_HELPER_NAME = 'sentryHelper';
    public const DEFAULT_SENTRY_SCRIPT = 'https://browser.sentry-cdn.com/8.7.0/bundle.tracing.replay.feedback.min.js';

    private static Config $config;

    public function __construct(DiInterface $di, View $view, Config $config)
    {
        self::$config = $config;

        $this->setDI($di);

        $this->attachHelperToView($di, $view);
    }

    public function attachHelperToView(DiInterface $di, View $view): void
    {
        $helperName = self::$config->path('sentry.options.helperName', 'sentryHelper');
        if (trim($helperName) === '') {
            $helperName = self::DEFAULT_HELPER_NAME;
        }

        $engines = $view->getRegisteredEngines();
        foreach ($engines as $engine) {
            if ($engine instanceof Volt === false) {
                continue;
            }

            $compiler = $engine->getCompiler();
            $compiler->addFunction(
                'phalconSentryGetScript',
                static fn ($ra) => self::class . '::getScript(' . $ra . ')'
            );
            $compiler->addFunction(
                'phalconSentryGetMetaTag',
                self::class . '::getMetaTag'
            );

            $view->setVar($helperName, $this);

            // Overwrite the view, else our settings are lost, because the view is not shared.
            $di->set('view', $view);

            break;
        }
    }

    public static function getMetaTag(): string
    {
        return implode(
            "\n",
            [
                '<meta name="sentry-trace" content="' . getTraceparent() . '"/>',
                '<meta name="baggage" content="' . getBaggage() . '"/>',
            ]
        );
    }

    /**
     * @param string[] $integrations
     */
    public static function getScript(array $integrations = [], ?string $scriptUrl = null): string
    {
        $viewOptions = self::$config->path('sentry.view', []);
        if (count($viewOptions) === 0) {
            $viewOptions = self::$config->path('sentry.options', []);
        }

        $viewOptions = $viewOptions instanceof Config ? $viewOptions->toArray() : $viewOptions;
        if (count($viewOptions) === 0) {
            return '';
        }

        $options = self::addIntegrations($viewOptions, $integrations);
        if ($options === '') {
            return '';
        }

        return implode(
            "\n",
            [
                sprintf(
                    '<script src="%1$s" %2$s crossorigin="anonymous"></script>',
                    self::getScriptUrl($scriptUrl),
                    self::getIntegrityAttribute()
                ),
                sprintf(
                    '<script>window.addEventListener("DOMContentLoaded", () => {Sentry.init(%s)});</script>',
                    $options
                ),
            ]
        );
    }

    /**
     * @param string[] $viewOptions
     * @param string[] $integrations
     */
    private static function addIntegrations(array $viewOptions, array $integrations): string
    {
        try {
            $renderOptions = json_encode(
                $viewOptions,
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
        } catch (Throwable) {
            return '';
        }

        return substr_replace(
            $renderOptions,
            ', integrations: [' . implode(',', $integrations) . ']}',
            -1
        );
    }

    private static function getScriptUrl(?string $scriptUrl = null): string
    {
        if ($scriptUrl !== null && trim($scriptUrl) !== '') {
            return $scriptUrl;
        }

        return self::$config->path(
            'sentry.browser.script',
            self::DEFAULT_SENTRY_SCRIPT
        );
    }

    private static function getIntegrityAttribute(): string
    {
        if (self::$config->path('sentry.browser.addSha', false) === false) {
            return '';
        }

        $sha = self::$config->path('sentry.browser.sha', '');
        return trim($sha) === '' ? '' : ' integrity="' . $sha . '"';
    }
}
