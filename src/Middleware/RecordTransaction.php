<?php

namespace PhilKra\ElasticApmLaravel\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use PhilKra\Agent;

class RecordTransaction
{
    /**
     * @var \PhilKra\Agent
     */
    protected $agent;
    /**
     * @var float
     */
    private $startTime;

    /**
     * RecordTransaction constructor.
     * @param Agent $agent
     */
    public function __construct(Agent $agent, float $startTime)
    {
        $this->agent = $agent;
        $this->startTime = $startTime;
    }

    /**
     * [handle description]
     * @param  Request $request [description]
     * @param  Closure $next [description]
     * @return [type]           [description]
     */
    public function handle($request, Closure $next)
    {
        $transaction = $this->agent->startTransaction(
            $this->getTransactionName($request),
            [],
            $this->startTime
        );

        // await the outcome
        $response = $next($request);

        $transaction->setResponse([
            'finished' => true,
            'headers_sent' => true,
            'status_code' => $response->getStatusCode(),
            'headers' => $this->formatHeaders($response->headers->all()),
        ]);

        $transaction->setUserContext([
            'id' => optional($request->user())->id,
            'email' => optional($request->user())->email,
        ]);

        $transaction->setMeta([
            'result' => $response->getStatusCode(),
            'type' => 'HTTP'
        ]);

        $transaction->setSpans(app('query-log')->toArray());

        if (config('elastic-apm.transactions.use_route_uri')) {
            $transaction->setTransactionName($this->getRouteUriTransactionName($request));
        }

        $transaction->stop();

        return $response;
    }

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Symfony\Component\HttpFoundation\Response $response
     *
     * @return void
     */
    public function terminate($request, $response)
    {
        try {
            $this->agent->send();
        }
        catch(\Throwable $t) {
            Log::error($t);
        }
    }

    /**
     * @param  \Illuminate\Http\Request $request
     *
     * @return string
     */
    protected function getTransactionName(\Illuminate\Http\Request $request): string
    {
        // fix leading /
        $path = ($request->server->get('REQUEST_URI') == '') ? '/' : $request->server->get('REQUEST_URI');

        return sprintf(
            "%s %s",
            $request->server->get('REQUEST_METHOD'),
            $path
        );
    }

    /**
     * @param  \Illuminate\Http\Request $request
     *
     * @return string
     */
    protected function getRouteUriTransactionName(\Illuminate\Http\Request $request): string
    {
        $path = ($request->route()->uri === '/') ? '' : $request->route()->uri;

        return sprintf(
            "%s /%s",
            $request->server->get('REQUEST_METHOD'),
            $path
        );
    }

    /**
     * @param $start
     *
     * @return float
     */
    protected function getDuration($start): float
    {
        $diff = microtime(true) - $start;
        $corrected = $diff * 1000; // convert to miliseconds

        return round($corrected, 3);
    }

    /**
     * @param array $headers
     *
     * @return array
     */
    protected function formatHeaders(array $headers): array
    {
        return collect($headers)->map(function ($values, $header) {
            return head($values);
        })->toArray();
    }
}
