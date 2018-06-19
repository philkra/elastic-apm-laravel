<?php
namespace PhilKra\Exceptions\Elastic;

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
     * @see Handler::report()
     */
    public function report(Exception $exception)
    {
        // Bind User Context
        $context = [];
        if( Auth::check() === false )
        {
            $context['user'] = [ 'id' => Auth::id(), ];
        }

        $agent = new Agent( config( 'services.apm_agent' ), $context );
        $agent->captureThrowable( $exception );
        $agent->send();

        parent::report($exception);
    }

}
