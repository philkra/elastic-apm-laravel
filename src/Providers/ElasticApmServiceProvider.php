<?php

namespace PhilKra\ElasticApmLaravel\Providers;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use PhilKra\Agent;
use PhilKra\ElasticApmLaravel\Contracts\VersionResolver;
use PhilKra\Helper\Timer;

class ElasticApmServiceProvider extends ServiceProvider
{
    /** @var float */
    private $startTime;
    /** @var Timer */
    private $timer;
    /** @var string  */
    private $sourceConfigPath = __DIR__ . '/../../config/elastic-apm.php';

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        if (class_exists('Illuminate\Foundation\Application', false)) {
            $this->publishes([
                realpath($this->sourceConfigPath) => config_path('elastic-apm.php'),
            ], 'config');
        }

        if (config('elastic-apm.active') === true ) {
            $this->listenForCommands();
            $this->listenForJobs();
        }
        if (config('elastic-apm.active') === true && config('elastic-apm.spans.querylog.enabled') !== false) {
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
            realpath($this->sourceConfigPath),
            'elastic-apm'
        );

        $this->app->singleton(Agent::class, function ($app) {
            return new Agent(
                array_merge(
                    [
                        'framework' => 'Laravel',
                        'frameworkVersion' => app()->version(),
                    ],
                    [
                        'active' => config('elastic-apm.active'),
                        'httpClient' => config('elastic-apm.httpClient'),
                    ],
                    $this->getAppConfig(),
                    config('elastic-apm.env'),
                    config('elastic-apm.server')
                )
            );
        });

        $this->startTime = $this->app['request']->server('REQUEST_TIME_FLOAT') ?? microtime(true);
        $this->timer = new Timer($this->startTime);

        $this->app->instance(Timer::class, $this->timer);

        $this->app->alias(Agent::class, 'elastic-apm');
        $this->app->instance('query-log', collect([]));
    }

    /**
     * @return array
     */
    protected function getAppConfig(): array
    {
        $config = config('elastic-apm.app');

        if ($this->app->bound(VersionResolver::class)) {
            $config['appVersion'] = $this->app->make(VersionResolver::class)->getVersion();
        }

        return $config;
    }

    /**
     * @param Collection $stackTrace
     * @return Collection
     */
    protected function stripVendorTraces(Collection $stackTrace): Collection
    {
        return collect($stackTrace)->filter(function ($trace) {
            return !starts_with(array_get($trace, 'file'), [
                base_path() . '/vendor',
            ]);
        });
    }

    /**
     * @param array $stackTrace
     * @return Collection
     */
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
            //file starts counting from 0, debug_stacktrace from 1
            $stackTraceLine = array_get($stackTrace, 'line') - 1;

            $lineStart = $stackTraceLine - 5;
            $lineStop = $stackTraceLine + 5;

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
            if (config('elastic-apm.spans.querylog.enabled') === 'auto') {
                if ($query->time < config('elastic-apm.spans.querylog.threshold')) {
                    return;
                }
            }

            $stackTrace = $this->stripVendorTraces(
                collect(
                    debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, config('elastic-apm.spans.backtraceDepth', 50))
                )
            );

            $stackTrace = $stackTrace->map(function ($trace) {
                $sourceCode = $this->getSourceCode($trace);

                return [
                    'function' => array_get($trace, 'function') . array_get($trace, 'type') . array_get($trace,
                            'function'),
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
                'start' => round((microtime(true) - $query->time / 1000 - $this->startTime) * 1000, 3),
                // calculate start time from duration
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

    protected function getCmdTransName($command)
    {
        return $command->command ? ('artisan ' . $command->command) : 'artisan help';
    }

    protected function getJobTransName($job)
    {
        return $job->job ? ($job->connectionName.'-job ' . json_decode($job->job->getRawBody())->displayName) : 'job';
    }

    protected function listenForCommands()
    {
        $this->app->events->listen(\Illuminate\Console\Events\CommandStarting::class, function ($commandStarting) {

            app(Agent::class)->startTransaction(
                $this->getCmdTransName($commandStarting)
            );
        });

        $this->app->events->listen(\Illuminate\Console\Events\CommandFinished::class, function ($commandFinished) {

            $agent = app(Agent::class);
            $transaction = $agent->getTransaction(
                $this->getCmdTransName($commandFinished)
            );
            $transaction->setResponse([
                'finished' => true,
                'status_code' => $commandFinished->exitCode,
            ]);

            $transaction->setUserContext([
                'id' => getmyuid(),
                'username' => get_current_user(),
            ]);

            $transaction->setMeta([
                'result' => $commandFinished->exitCode,
                'type' => 'COMMAND'
            ]);

            $transaction->setSpans(app('query-log')->toArray());

            $transaction->stop();
            $agent->send();
        });
    }

    protected function listenForJobs()
    {
        $this->app->queue->before(function (JobProcessing $job) {
            app(Agent::class)->startTransaction(
                $this->getJobTransName($job)
            );
        });

        $this->app->queue->after(function (JobProcessed $job) {
            $agent = app(Agent::class);
            $transaction = $agent->getTransaction(
                $this->getJobTransName($job)
            );
            $transaction->setUserContext([
                'id' => getmyuid(),
                'username' => get_current_user(),
            ]);
            $transaction->setMeta([
                'type' => 'JOB'
            ]);
            $transaction->setSpans(app('query-log')->toArray());

            $transaction->stop();
            $agent->send();
        });

        $this->app->queue->failing(function (JobFailed $job) {
            $agent = app(Agent::class);
            $agent->captureThrowable(new \Exception('failed job'));
            $agent->send();
        });
    }
}
