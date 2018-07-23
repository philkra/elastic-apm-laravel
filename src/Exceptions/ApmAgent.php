<?php

namespace PhilKra\ElasticApmLaravel\Exceptions;

/**
 *
 * Overloaded Exception Handler for Elastic APM Agent
 *
 * @link https://laravel.com/docs/5.6/errors
 *
 */

use Exception;
use App\Exceptions\Handler;
use \PhilKra\Agent;
use Illuminate\Support\Facades\Auth;

class ApmAgent extends Handler
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
     * @see Handler::report()
     */
    public function report(Exception $exception)
    {
        $this->agent->captureThrowable($exception);
        $this->agent->send();

        parent::report($exception);
    }
}
