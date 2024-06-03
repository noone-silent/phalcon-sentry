<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Events;

use Sentry\Tracing\Span;

interface EventHandlerInterface
{
    /**
     * @return Span[]
     */
    public function getOpenSpans(): array;
}
