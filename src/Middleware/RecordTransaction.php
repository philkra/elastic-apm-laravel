<?php

namespace PhilKra\ElasticApmLaravel\Middleware;

use Closure;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use PhilKra\Agent;

class RecordTransaction
{
    /**
     * @var \PhilKra\Agent
     */
    protected $agent;

    public function __construct(Agent $agent)
    {
        $this->agent = $agent;
    }

    /**
     * [handle description]
     * @param  Request  $request [description]
     * @param  Closure $next    [description]
     * @return [type]           [description]
     */
    public function handle($request, Closure $next)
    {
        $transaction = $this->agent->startTransaction(
            $this->getTransactionName($request->route())
        );

        // await the outcome
        $response = $next($request);

        $transaction->setResponse([
            'finished'     => true,
            'headers_sent' => true,
            'status_code'  => $response->getStatusCode(),
            'headers'      => $this->formatHeaders($response->headers->all()),
        ]);

        $transaction->setUserContext([
            'id'    => optional($request->user())->id,
            'email' => optional($request->user())->email,
         ]);

        $transaction->setMeta([
            'result' => $response->getStatusCode(),
            'type'   => 'HTTP'
         ]);

        $transaction->setSpans(app('query-log')->toArray());

        $transaction->stop(
            $this->getDuration(LARAVEL_START)
        );

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     *
     * @return void
     */
    public function terminate($request, $response)
    {
        $this->agent->send();
    }

    protected function getTransactionName(Route $route)
    {
        // fix leading /
        if ($route->uri !== '/') {
            $route->uri = '/'.$route->uri;
        }

        return sprintf(
            "%s %s",
            head($route->methods),
            $route->uri
        );
    }

    protected function getDuration($start): float
    {
        $diff = microtime(true) - $start;
        $corrected = $diff * 1000; // convert to miliseconds

        return round($corrected, 3);
    }

    protected function formatHeaders(array $headers): array
    {
        return collect($headers)->map(function ($values, $header) {
            return head($values);
        })->toArray();
    }
}
