<?php

declare(strict_types=1);

namespace Phalcon\Sentry\Events;

use Phalcon\Db\Adapter\Pdo\AbstractPdo;
use Phalcon\Events\Event;
use Sentry\Tracing\SpanContext;

class DBQueryEventHandler extends AbstractEventHandler
{
    protected const SPAN_OP_CONN_PREPARE = 'db.sql.prepare';
    protected const SPAN_OP_CONN_QUERY = 'db.sql.query';
    protected const SPAN_OP_CONN_EXEC = 'db.sql.exec';
    protected const SPAN_OP_CONN_BEGIN_TRANSACTION = 'db.sql.transaction.begin';
    protected const SPAN_OP_TRANSACTION_COMMIT = 'db.sql.transaction.commit';
    protected const SPAN_OP_TRANSACTION_ROLLBACK = 'db.sql.transaction.rollback';

    public function beforeQuery(Event $event, AbstractPdo $db): void
    {
        $span = $this->getHub()?->getSpan();
        if ($span === null) {
            return;
        }

        $id = $db->getSQLStatement() . serialize($db->getSQLVariables());

        $backtrace = [];
        if ($this->getConfig()->path('options.db.backtrace', true)) {
            $backtrace = $this->getBacktrace();
        }

        $spanContext = new SpanContext();
        $spanContext->setOp(self::SPAN_OP_CONN_QUERY);
        $spanContext->setDescription($db->getSQLStatement());
        $spanContext->setData(
            [
                'db.system'       => $this->getDriver($db),
                'code.stacktrace' => implode("\n", $backtrace),
            ]
        );

        $this->spans[$id] = $span->startChild($spanContext);
    }

    public function afterQuery(Event $event, AbstractPdo $db): void
    {
        $id = $db->getSQLStatement() . serialize($db->getSQLVariables());

        $span = $this->spans[$id] ?? null;
        $span?->finish();
        unset($this->spans[$id]);
    }

    public function getOpenSpans(): array
    {
        return $this->spans;
    }

    private function getDriver(AbstractPdo $db): string
    {
        return match (true) {
            $db->getDialectType() === 'mysql' => 'mysql',
            $db->getDialectType() === 'postgresql' => 'postgresql',
            $db->getDialectType() === 'sqlite' => 'sqlite',
            default => 'other_sql',
        };
    }

    /**
     * @return string[]
     */
    private function getBacktrace(): array
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
