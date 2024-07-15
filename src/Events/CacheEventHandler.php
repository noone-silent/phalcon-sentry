<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Events;

use Phalcon\Cache\AbstractCache;
use Phalcon\Cache\Adapter\Apcu;
use Phalcon\Cache\Adapter\Libmemcached;
use Phalcon\Cache\Adapter\Memory;
use Phalcon\Cache\Adapter\Redis;
use Phalcon\Cache\Adapter\Stream;
use Phalcon\Events\Event;
use Sentry\Tracing\SpanContext;

class CacheEventHandler extends AbstractEventHandler
{
    protected const SPAN_OP_GET_ITEM = 'cache.get';
    protected const SPAN_OP_HAS_ITEM = 'cache.has';
    protected const SPAN_OP_DELETE_ITEM = 'cache.remove';
    protected const SPAN_OP_SAVE = 'cache.put';
    protected const SPAN_OP_CLEAR = 'cache.flush';

    public function beforeSet(Event $event, AbstractCache $cache): void
    {
        $this->beforeEvent(self::SPAN_OP_SAVE, $event, $cache);
    }

    public function afterSet(Event $event, AbstractCache $cache): void
    {
        $this->afterEvent($event, $cache);
    }

    public function beforeGet(Event $event, AbstractCache $cache): void
    {
        $this->beforeEvent(self::SPAN_OP_GET_ITEM, $event, $cache);
    }

    public function afterGet(Event $event, AbstractCache $cache): void
    {
        $this->afterEvent($event, $cache);
    }

    public function beforeHas(Event $event, AbstractCache $cache): void
    {
        $this->beforeEvent(self::SPAN_OP_HAS_ITEM, $event, $cache);
    }

    public function afterHas(Event $event, AbstractCache $cache): void
    {
        $this->afterEvent($event, $cache);
    }

    public function beforeDelete(Event $event, AbstractCache $cache): void
    {
        $this->beforeEvent(self::SPAN_OP_DELETE_ITEM, $event, $cache);
    }

    public function afterDelete(Event $event, AbstractCache $cache): void
    {
        $this->afterEvent($event, $cache);
    }

    private function beforeEvent(string $operation, Event $event, AbstractCache $cache): void
    {
        $span = $this->getHub()?->getSpan();
        if ($span === null) {
            return;
        }

        $data = $event->getData();
        $id = implode(',', is_array($data) ? $data : [(string)$data]);

        $backtrace = [];
        if ($this->getConfig()->path('options.cache.backtrace', true)) {
            $backtrace = $this->getBacktrace();
        }
        $data = [
            'cache.key'       => $id,
            'cache.system'    => $this->getDriver($cache),
            'code.stacktrace' => implode("\n", $backtrace),
        ];

        if ($operation === self::SPAN_OP_GET_ITEM) {
            $data['cache.hit'] = 1;
            $data['cache.item_size'] = 1;
        }
        if ($operation === self::SPAN_OP_SAVE) {
            $data['cache.item_size'] = 1;
        }

        $spanContext = new SpanContext();
        $spanContext->setOp($operation);
        $spanContext->setDescription($event->getData());
        $spanContext->setData($data);

        $this->spans[$id] = $span->startChild($spanContext);
    }

    private function afterEvent(Event $event, AbstractCache $cache): void
    {
        $data = $event->getData();
        $id = implode(',', is_array($data) ? $data : [(string)$data]);

        $span = $this->spans[$id] ?? null;
        $span?->finish();
        unset($this->spans[$id]);
    }

    private function getDriver(AbstractCache $cache): string
    {
        return match (true) {
            $cache->getAdapter() instanceof Redis => 'redis',
            $cache->getAdapter() instanceof Memory => 'memory',
            $cache->getAdapter() instanceof Apcu => 'apcu',
            $cache->getAdapter() instanceof Libmemcached => 'memcached',
            $cache->getAdapter() instanceof Stream => 'stream',
            default => 'custom',
        };
    }
}
