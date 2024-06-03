<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Events;

use Phalcon\Events\Event;
use Phalcon\Mvc\ViewInterface;
use Sentry\Tracing\SpanContext;

class ViewRenderEventHandler extends AbstractEventHandler
{
    protected const SPAN_OP_VIEW_RENDER = 'view.render';

    public function beforeRenderView(Event $event, ViewInterface $view): void
    {
        $span = $this->getHub()?->getSpan();
        if ($span === null) {
            return;
        }

        $data = [];
        if ($this->getConfig()->path('options.view.parameters', false) === true) {
            // @todo add parameters
            $data['params'] = [];
        }

        $spanContext = new SpanContext();
        $spanContext->setOp(self::SPAN_OP_VIEW_RENDER);
        $spanContext->setDescription($view->getActiveRenderPath());
        $spanContext->setData($data);

        $this->spans[$view->getActiveRenderPath()] = $span->startChild($spanContext);
    }

    public function afterRenderView(Event $event, ViewInterface $view): void
    {
        $span = $this->spans[$view->getActiveRenderPath()] ?? null;
        $span?->finish();
        unset($this->spans[$view->getActiveRenderPath()]);
    }
}
