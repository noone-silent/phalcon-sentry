<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Events;

use Phalcon\Config\Config;
use Phalcon\Di\DiInterface;
use Phalcon\Di\Injectable;
use Sentry\State\HubInterface;
use Sentry\Tracing\Span;

class AbstractEventHandler extends Injectable implements EventHandlerInterface
{
    /** @var Span[] */
    protected array $spans = [];

    protected Config $config;

    protected ?HubInterface $hub;

    public function __construct(DiInterface $container, ?HubInterface $hub, Config $config)
    {
        $this->container = $container;
        $this->hub = $hub;
        $this->config = $config;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getHub(): ?HubInterface
    {
        return $this->hub;
    }

    public function getContainer(): DiInterface
    {
        return $this->container;
    }

    public function getOpenSpans(): array
    {
        return $this->spans;
    }

    /**
     * @return string[]
     */
    protected function getBacktrace(): array
    {
        $stack = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 50);
        array_shift($stack);

        $sources = [];
        foreach ($stack as $item) {
            if (empty($item['line']) === true || empty($item['file']) === true) {
                continue;
            }

            $sources[] = "at {$item['file']}:{$item['line']}";
        }

        return array_values(array_filter($sources));
    }
}
