<?php


namespace PhilKra\ElasticApmLaravel\Contracts;


interface VersionResolver
{
    public function getVersion(): string;
}
