<?php
namespace PhilKra\Middleware\Elastic;

/**
 *
 * Middleware for Elastic APM Agent
 *
 * @link https://laravel.com/docs/5.6/middleware
 *
 */

use Closure;

use \PhilKra\Agent;
use Illuminate\Support\Facades\Auth;

class ApmAgent
{

    /**
     * @var PhilKra\Agent
     */
    private $agent;

    /**
     * @var string
     */
    private $transactionName;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Bind User Context
        $context = [];
        if( Auth::check() === false )
        {
            $context['user'] = [ 'id' => Auth::id(), ];
        }

        // Init Agent
        $this->agent = new Agent( config( 'services.apm_agent' ), $context );
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
        $trx = sprintf( '%s /%s', $request->method(), $request->path() );
        $this->agent->startTransaction( $trx );

        $response = $next( $request );

        // Stop the Transaction
        $this->agent->stopTransaction( $trx, [
            'result' => $response->status(),
            'type'   => 'demo'
        ] );
        $this->agent->getTransaction( $trx )->setResponse( [
            'finished'     => true,
            'headers_sent' => true,
            'status_code'  => $response->status(),
            'headers'      => [
                'content-type' => $response->headers->get( 'content-type' )
            ]
        ] );
        $this->agent->send();

        return $response;
    }

}
