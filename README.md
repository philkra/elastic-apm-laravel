# PHP Elastic APM for Laravel & Lumen
Laravel package of the https://github.com/philkra/elastic-apm-php-agent library, automatically handling transactions and errors/exceptions. If using `Illuminate\Support\Facades\Auth` the user Id added to the context.
Tested with Laravel `5.6.*` and the philkra/elastic-apm-php-agent version `6.2.*`.

## Install
```
composer require philkra/elastic-apm-laravel
```

## Middleware
### Laravel
Register as (e.g.) global middleware to be called with every request. https://laravel.com/docs/5.6/middleware#global-middleware

Register the middleware in `app/Http/Kernel.php`
```php
protected $middleware = [
    // ... more middleware
    \PhilKra\ElasticApmLaravel\Middleware\RecordTransaction::class,
];
```

### Lumen
In `bootstrap/app.php` register `PhilKra\ElasticApmLaravel\Middleware\RecordTransaction::class` as middleware:
```php
$app->middleware([
    PhilKra\ElasticApmLaravel\Middleware\RecordTransaction::class
]);
```

## Service Provider
### Laravel
No need to register service provider manually. It is registered automatically by [package discovery](https://laravel.com/docs/5.6/packages#package-discovery).
### Lumen
In `bootstrap/app.php` register `\PhilKra\ElasticApmLaravel\Providers\ElasticApmServiceProvider::class` as service provider:
```php
$app->register(\PhilKra\ElasticApmLaravel\Providers\ElasticApmServiceProvider::class);
```

## Spans
### Laravel
A Transaction object is made available via the dependency container and can be used to start a
new span at any point in the application. The Span will automatically add itself to the Transaction
when it is ended.

```php
// Use any normal Laravel method of resolving the dependency
$transaction = app(\PhilKra\ElasticApmLaravel\Apm\Transaction::class);

$span = $transaction->startNewSpan('My Span', 'app.component_name');

// do some stuff

$span->end();
```
### Lumen

pending

## Error/Exception Handling

### Laravel

In `app/Exceptions/Handler`, add the following to the `report` method:

```php
ElasticApm::captureThrowable($exception);
ElasticApm::send();
```

Make sure to import the facade at the top of your file:

```php
use ElasticApm;
```

### Lumen
not tested yet.

## Agent Configuration

### Laravel

The following environment variables are supported in the default configuration:

| Variable          | Description |
|-------------------|-------------|
|APM_ACTIVE         | `true` or `false` defaults to `true`. If `false`, the agent will collect, but not send, transaction data. |
|APM_APPNAME        | Name of the app as it will appear in APM. |
|APM_APPVERSION     | Version of the app as it will appear in APM. |
|APM_SERVERURL      | URL to the APM intake service. |
|APM_SECRETTOKEN    | Secret token, if required. |
|APM_APIVERSION     | APM API version, defaults to `v1` (only v1 is supported at this time). |
|APM_USEROUTEURI    | `true` or `false` defaults to `false`. The default behavior is to record the URL as sent in the request. This can result in excessive unique entries in APM. Set to `true` to have the agent use the route URL instead. |
|APM_QUERYLOG       | `true` or `false` defaults to 'true'. Set to `false` to completely disable query logging, or to `auto` if you would like to use the threshold feature. |
|APM_THRESHOLD      | Query threshold in milliseconds, defaults to `200`. If a query takes longer then 200ms, we enable the query log. Make sure you set `APM_QUERYLOG=auto`. |
|APM_BACKTRACEDEPTH | Defaults to `25`. Depth of backtrace in query span. |
|APM_RENDERSOURCE   | Defaults to `true`. Include source code in query span. |

You may also publish the `elastic-apm.php` configuration file to change additional settings:

```bash
php artisan vendor:publish --tag=config
```

Once published, open the `config/elastic-apm.php` file and review the various settings.

### Laravel Test Setup

Laravel provides classes to support running unit and feature tests with PHPUnit. In most cases, you will want to explicitly disable APM during testing since it is enabled by default. Refer to the Laravel documentation for more information (https://laravel.com/docs/5.7/testing).

Because the APM agent checks it's active status using a strict boolean type, you must ensure your `APM_ACTIVE` value is a boolean `false` rather than simply a falsy value. The best way to accomplish this is to create an `.env.testing` file and include `APM_ACTIVE=false`, along with any other environment settings required for your tests. This file should be safe to include in your SCM.
