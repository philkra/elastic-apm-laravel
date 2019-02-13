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

| Variable         | Description |
|------------------|-------------|
|APM_ACTIVE        | `true` or `false` defaults to `true`. If `false`, the agent will collect, but not send, transaction data. |
|APM_APPNAME       | Name of the app as it will appear in APM. |
|APM_APPVERSION    | Version of the app as it will appear in APM. |
|APM_SERVERURL     | URL to the APM intake service. |
|APM_SECRETTOKEN   | Secret token, if required. |
|APM_APIVERSION    | APM API version, defaults to `v1` (only v1 is supported at this time). |
|APM_USEROUTEURI   | `true` or `false` defaults to `false`. The default behavior is to record the URL as sent in the request. This can result in excessive unique entries in APM. Set to `true` to have the agent use the route URL instead. |
|APM_QUERYLOG      | `true` or `false` defaults to 'true'. Set to `false` to completely disable query logging, or to `auto` if you would like to use the threshold feature. |
|APM_THRESHOLD     | Query threshold in milliseconds, defaults to `200`. If a query takes longer then 200ms, we enable the query log. Make sure you set `APM_QUERYLOG=auto`. |

You may also publish the `elastic-apm.php` configuration file to change additional settings:

```bash
php artisan vendor:publish --tag=config --provider="PhilKra\ElasticApmLaravel\Providers\ElasticApmService
Provider"
```

Once published, open the `config/elastic-apm.php` file and review the various settings.
