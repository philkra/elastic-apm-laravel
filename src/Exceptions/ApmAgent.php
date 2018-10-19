<?php

namespace PhilKra\ElasticApmLaravel\Exceptions;

/**
 *
 * Overloaded Exception Handler for Elastic APM Agent
 *
 * @link https://laravel.com/docs/5.6/errors
 * @link https://github.com/philkra/elastic-apm-laravel/issues/9
 *
 */

use Exception;
use App\Exceptions\Handler;
use \PhilKra\Agent;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
        try {
            $this->agent->captureThrowable($exception);
            $this->agent->send();
        }
        catch(\Throwable $t) {
            Log::error($t);
        }

        parent::report($exception);
    }
}
