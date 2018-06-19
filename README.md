# elastic-apm-laravel

## Middleware

### Laravel
Register as (e.g.) global middleware to be called with every request. https://laravel.com/docs/5.6/middleware#global-middleware

Register the middleware in app/Http/Kernel.php

protected $middleware = [
    // ... more middleware
    \PhilKra\Middleware\Elastic\ApmAgent::class,
];


### Lumen
bootstrap/app.php

$app->middleware([
    PhilKra\Middleware\Elastic\ApmAgent::class
]);

## Error/Exception Handling

### Laravel

Replace the default exception handler with overloading APM handler. The APM class is
extending Laravel's "default" exception handler `app/Exceptions/Handler`.
In `bootstrap/app.php` remove the default handler `App\Exceptions\Handler::class` with `PhilKra\Exceptions\Elastic\ApmAgent::class`.

```
$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    PhilKra\Exceptions\Elastic\ApmAgent::class
);
```
