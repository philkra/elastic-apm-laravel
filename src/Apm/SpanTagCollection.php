<?php


namespace PhilKra\ElasticApmLaravel\Apm;


use Illuminate\Support\Collection;

class SpanTagCollection extends Collection
{
    public function __construct(array $items = [])
    {
        $this->ensureTags($items);

        parent::__construct($items);
    }

    private function ensureTags($items)
    {
        foreach ($items as $key => $value) {
            if (!preg_match('/^[^.*"]*$/', $key)) {
                throw new \InvalidArgumentException(sprintf('Tag name %s is invalid for APM Span', $key));
            }

            if (strlen($value) > 1024) {
                throw new \InvalidArgumentException('Tag value cannot be greater than 1024 characters for APM Span');
            }
        }
    }
}