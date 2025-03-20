<?php

declare(strict_types=1);

namespace Phalcon\Sentry;

use Phalcon\Cache\AbstractCache;
use Phalcon\Config\Adapter\Ini;
use Phalcon\Config\Adapter\Php;
use Phalcon\Config\Adapter\Yaml;
use Phalcon\Config\Config;
use Phalcon\Config\Exception;
use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Di\DiInterface;
use Phalcon\Di\ServiceProviderInterface;
use Phalcon\Events\EventsAwareInterface;
use Phalcon\Events\Manager;
use Phalcon\Http\RequestInterface;
use Phalcon\Http\ResponseInterface;
use Phalcon\Mvc\View;
use Phalcon\Sentry\Events\EventHandlerInterface;
use Phalcon\Sentry\Helpers\Sentry;
use RuntimeException;
use Sentry\SentrySdk;
use Sentry\State\HubInterface;
use Sentry\Tracing\SpanStatus;
use Sentry\Tracing\TransactionSource;
use Throwable;

use function Sentry\captureMessage;
use function Sentry\continueTrace;
use function Sentry\init;

class ServiceProvider implements ServiceProviderInterface
{
    protected const SPAN_OP_HTTP_SERVER = 'http.server';

    private string | Config | null $configOrPath;

    private ?DiInterface $container = null;

    private ?HubInterface $hub = null;

    /** @var EventHandlerInterface[] */
    private array $handlers = [];

    public function __construct(string | Config | null $configPath = null)
    {
        $this->configOrPath = $configPath;
    }

    /**
     * Registers the service provider.
     *
     * @throws \Exception
     */
    public function register(DiInterface $di): void
    {
        $this->container = $di;

        $config = $this->mergeConfig($di);

        $this->boot($di, $config);
    }

    public function boot(DiInterface $di, Config $config): void
    {
        // The event manager is needed to attach listeners to db and view events.
        if ($di->has('eventsManager') === false) {
            $eventsManager = new Manager();
            $di->set('eventsManager', $eventsManager, true);
        }

        /** @var Config $options */
        $options = $config->path('sentry.options', [])->toArray();

        $default = [];
        foreach ($options as $key => $value) {
            // @phpstan-ignore-next-line
            $default[(string)$key] = $value;
        }

        if (empty($default['dsn']) === true) {
            // Without sentry dsn we can't trace anything. Abort.
            throw new RuntimeException(
                'phalcon-sentry: No valid sentry dsn found in options.'
            );
        }

        // @phpstan-ignore-next-line
        init($default);
        $this->hub = SentrySdk::getCurrentHub();
        register_shutdown_function([$this, 'finish']);

        $this->firstSpan($di);
        $this->attach($di, $config);
    }

    public function finish(): void
    {
        $transaction = $this->hub?->getTransaction();
        if ($transaction === null) {
            return;
        }

        foreach ($this->handlers as $handler) {
            foreach ($handler->getOpenSpans() as $openSpan) {
                $openSpan->finish();
            }
        }

        if (
            $this->container instanceof DiInterface === false ||
            $this->container->has('response') === false
        ) {
            $transaction->finish();

            return;
        }

        $response = $this->container->get('response');
        if ($response instanceof ResponseInterface === false) {
            $transaction->finish();

            return;
        }

        $transaction->setStatus(
            SpanStatus::createFromHttpStatusCode(
                $response->getStatusCode() ?? 200
            )
        );

        $transaction->finish();
    }

    /**
     * Merge config.
     *
     * @throws Exception
     */
    protected function mergeConfig(DiInterface $di): Config
    {
        if ($this->configOrPath instanceof Config) {
            $di->set('phalcon-sentry.config', $this->configOrPath, true);

            return $this->configOrPath;
        }

        $baseConfig = new Php(__DIR__ . '/config/sentry.php');
        if ($this->configOrPath === null) {
            return $baseConfig;
        }

        if (
            file_exists($this->configOrPath) === false ||
            is_readable($this->configOrPath) === false
        ) {
            throw new RuntimeException(
                'phalcon-sentry: Config file ' . $this->configOrPath . ' does not exist or is not readable.'
            );
        }

        $parts     = explode('.', $this->configOrPath);
        $extension = end($parts);
        match ($extension) {
            'php' => $baseConfig->merge(new Php($this->configOrPath)),
            'ini' => $baseConfig->merge(new Ini($this->configOrPath)),
            'yml', 'yaml' => $baseConfig->merge(new Yaml($this->configOrPath)),
            default => throw new RuntimeException(
                'phalcon-sentry: Only .php/.ini/.yml/.yaml config files are supported'
            )
        };

        $di->set('phalcon-sentry.config', $baseConfig, true);

        return $baseConfig;
    }

    private function firstSpan(DiInterface $di): void
    {
        if ($di->has('request') === false) {
            return;
        }

        $span = $this->hub?->getSpan();
        if ($span !== null) {
            return;
        }

        /** @var RequestInterface $request */
        $request = $di->get('request');
        $uri     = $request->getURI(true);

        $requestStartTime = (float)$request->getServer('REQUEST_TIME_FLOAT') ?: microtime(true);

        $context = continueTrace(
            $request->getHeader('sentry-trace') ?: '',
            $request->getHeader('baggage') ?: '',
        );
        $context->setOp(self::SPAN_OP_HTTP_SERVER);
        $context->setName($uri);
        $context->setDescription($request->getMethod() . ' ' . $uri);
        $context->setSource(TransactionSource::route());
        $context->setStartTimestamp($requestStartTime);

        $this->hub?->setSpan($this->hub->startTransaction($context));
    }

    private function attach(DiInterface $di, Config $config): void
    {
        $this->attachCache($di, $config);
        $this->attachDb($di, $config);
        $this->attachView($di, $config);
    }

    private function attachCache(DiInterface $di, Config $config): void
    {
        if ($config->get('cache', true) === false || $di->has('cache') === false) {
            return;
        }

        $services = $config->path('options.cache.services', ['cache']);
        foreach ($services as $service) {
            $cache = $di->get($service);
            if ($cache instanceof AbstractCache === false) {
                captureMessage(
                    sprintf(
                        'phalcon-sentry: service %s needs to be an instance of AbstractCache',
                        $service
                    )
                );

                continue;
            }

            // we need at least Phalcon 5.8.* for that
            // @phpstan-ignore-next-line
            if ($cache instanceof EventsAwareInterface === false) {
                continue;
            }

            $eventsManager = $cache->getEventsManager();
            if ($cache->getEventsManager() === null) {
                $eventsManager = $di->get('eventsManager');
                $cache->setEventsManager($eventsManager);
            }

            $this->handlers[$service] = new Events\CacheEventHandler($di, $this->hub, $config);

            $eventsManager->attach($service, $this->handlers[$service]);
        }
    }

    private function attachDb(DiInterface $di, Config $config): void
    {
        if ($config->get('db', true) === false || $di->has('db') === false) {
            return;
        }

        $db = $di->get('db');
        if ($db instanceof AbstractPdo === false) {
            captureMessage('phalcon-sentry: service db needs to be an instance of AbstractPdo');

            return;
        }

        try {
            $eventsManager = $db->getEventsManager();
        } catch (Throwable) {
            $eventsManager = $di->get('eventsManager');
            $db->setEventsManager($eventsManager);
        }

        $this->handlers['db'] = new Events\DBQueryEventHandler($di, $this->hub, $config);

        $eventsManager->attach('db', $this->handlers['db']);
    }

    private function attachView(DiInterface $di, Config $config): void
    {
        if (
            $config->get('view', true) === false ||
            $di->has('view') === false
        ) {
            return;
        }

        $view = $di->get('view');
        if ($view instanceof View === false) {
            captureMessage('phalcon-sentry: service view needs to be an instance of View');

            return;
        }

        new Sentry($di, $view, $config);

        try {
            $eventsManager = $view->getEventsManager();
        } catch (Throwable) {
            $eventsManager = $di->get('eventsManager');
            $view->setEventsManager($eventsManager);
        }

        $this->handlers['view'] = new Events\ViewRenderEventHandler($di, $this->hub, $config);

        $eventsManager->attach('view', $this->handlers['view']);
    }
}
