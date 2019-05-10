<?php

namespace PhilKra\ElasticApmLaravel\Apm;


use PhilKra\Helper\Timer;

/*
 * Eventually this class could be a proxy for a Transaction provided by the
 * Elastic APM package.
 */
class Transaction
{
    /** @var SpanCollection  */
    private $collection;
    /** @var Timer  */
    private $timer;

    public function __construct(SpanCollection $collection, Timer $timer)
    {
        $this->collection = $collection;
        $this->timer = $timer;
    }

    public function startNewSpan(string $name = null, string $type = null): Span
    {
        $span = new Span($this->timer, $this->collection);

        if (null !== $name) {
            $span->setName($name);
        }

        if (null !== $type) {
            $span->setType($type);
        }

        return $span;
    }
}