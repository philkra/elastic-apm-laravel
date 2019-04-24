<?php

namespace PhilKra\ElasticApmLaravel\Apm;


use PhilKra\Helper\Timer;

/*
 * Eventually this class could be a proxy for a Span provided by the
 * Elastic APM package.
 */
class Span
{
    /** @var Timer */
    private $timer;
    /** @var SpanCollection  */
    private $collection;

    private $name = 'Transaction Span';
    private $type = 'app.span';
    private $context = [];

    private $start;

    public function __construct(Timer $timer, SpanCollection $collection)
    {
        $this->timer = $timer;
        $this->collection = $collection;

        $this->start = $timer->getElapsedInMilliseconds();
    }

    public function setName(string $name)
    {
        $this->name = $name;
    }

    public function setType(string $type)
    {
        $this->type = $type;
    }

    public function setDbContext(SpanDbContext $context)
    {
        $this->context['db'] = $context->toArray();
    }

    public function setTags(SpanTagCollection $collection)
    {
        $this->context['tags'] = $collection->toArray();
    }

    public function end()
    {
        $duration = round($this->timer->getElapsedInMilliseconds() - $this->start, 3);
        $this->collection->push([
            'name' => $this->name,
            'type' => $this->type,
            'start' => $this->start,
            'duration' => $duration,
            'context' => $this->context,
        ]);
    }
}