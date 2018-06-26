<?php

namespace PhilKra\ElasticApmLaravel\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use PhilKra\Agent;

class ElasticApmServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/elastic-apm.php' => config_path('elastic-apm.php'),
        ], 'config');

        if (config('elastic-apm.enabled') === true) {
            $this->listenForQueries();
        }
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/elastic-apm.php',
            'elastic-apm'
        );

        $this->app->singleton(Agent::class, function ($app) {
            return new Agent(
                array_merge(
                    [
                        'framework' => 'Laravel',
                        'frameworkVersion' => app()->version(),
                    ],
                    config('elastic-apm.app'),
                    config('elastic-apm.server')
                )
            );
        });
        
        $this->app->alias(Agent::class, 'elastic-apm');
        $this->app->instance('query-log', collect([]));
    }

    protected function stripVendorTraces(Collection $stackTrace): Collection
    {
        return collect($stackTrace)->filter(function ($trace) {
            return !starts_with(array_get($trace, 'file'), [
                base_path().'/vendor',
            ]);
        });
    }

    protected function getSourceCode(array $stackTrace): Collection
    {
        if (config('elastic-apm.spans.renderSource', false) === false) {
            return collect([]);
        }

        if (empty(array_get($stackTrace, 'file'))) {
            return collect([]);
        }

        $fileLines = file(array_get($stackTrace, 'file'));
        return collect($fileLines)->filter(function ($code, $line) use ($stackTrace) {
            $lineStart = array_get($stackTrace, 'line') - 5;
            $lineStop = array_get($stackTrace, 'line') + 5;
            
            return $line >= $lineStart && $line <= $lineStop;
        })->groupBy(function ($code, $line) use ($stackTrace) {
            if ($line < array_get($stackTrace, 'line')) {
                return 'pre_context';
            }

            if ($line == array_get($stackTrace, 'line')) {
                return 'context_line';
            }

            if ($line > array_get($stackTrace, 'line')) {
                return 'post_context';
            }

            return 'trash';
        });
    }

    protected function listenForQueries()
    {
        $this->app->events->listen(QueryExecuted::class, function (QueryExecuted $query) {
            $stackTrace = $this->stripVendorTraces(
                collect(
                    debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, config('elastic-apm.spans.backtraceDepth', 50))
                )
            );

            $stackTrace = $stackTrace->map(function ($trace) {
                $sourceCode = $this->getSourceCode($trace);

                return [
                    'function' => array_get($trace, 'function').array_get($trace, 'type').array_get($trace, 'function'),
                    'abs_path' => array_get($trace, 'file'),
                    'filename' => basename(array_get($trace, 'file')),
                    'lineno' => array_get($trace, 'line', 0),
                    'library_frame' => false,
                    'vars' => $vars ?? null,
                    'pre_context' => optional($sourceCode->get('pre_context'))->toArray(),
                    'context_line' => optional($sourceCode->get('context_line'))->first(),
                    'post_context' => optional($sourceCode->get('post_context'))->toArray(),
                ];
            })->values();
            
            $query = [
                'name' => 'Eloquent Query',
                'type' => 'db.mysql.query',
                'start' => round((microtime(true) - $query->time/1000 - LARAVEL_START) * 1000, 3), // calculate start time from duration
                'duration' => round($query->time, 3),
                'stacktrace' => $stackTrace,
                'context' => [
                    'db' => [
                        'instance' => $query->connection->getDatabaseName(),
                        'statement' => $query->sql,
                        'type' => 'sql',
                        'user' => $query->connection->getConfig('username'),
                    ],
                ],
            ];
        
            app('query-log')->push($query);
        });
    }
}
