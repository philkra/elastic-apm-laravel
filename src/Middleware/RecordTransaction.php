<?php

namespace PhilKra\ElasticApmLaravel\Middleware;

use Closure;
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
        // Start Transaction
        $transactionName = sprintf('%s /%s', $request->method(), $request->path());
        $this->agent->startTransaction($transactionName);

        $response = $next($request);

        // Stop the Transaction
        $this->agent->stopTransaction($transactionName, [
            'result' => $response->status(),
            'type'   => 'demo'
        ]);

        $transaction = $this->agent->getTransaction($transactionName);

        $transaction->setResponse([
            'finished'     => true,
            'headers_sent' => true,
            'status_code'  => $response->status(),
            'headers'      => [
                'content-type' => $response->headers->get('content-type')
            ]
        ]);

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
}
