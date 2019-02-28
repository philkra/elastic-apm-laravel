<?php

namespace PhilKra\ElasticApmLaravel\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use PhilKra\Agent;
use PhilKra\Helper\Timer;

class RecordTransaction
{
    /**
     * @var \PhilKra\Agent
     */
    protected $agent;
    /**
     * @var Timer
     */
    private $timer;

    /**
     * RecordTransaction constructor.
     * @param Agent $agent
     */
    public function __construct(Agent $agent, Timer $timer)
    {
        $this->agent = $agent;
        $this->timer = $timer;
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
            $this->getTransactionName($request)
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

        $transaction->stop($this->timer->getElapsedInMilliseconds());

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
        $path = ($request->path() === '/') ? '' : $request->path();

        return sprintf(
            "%s /%s",
            $request->server->get('REQUEST_METHOD'),
            $path
        );
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
