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
ElasticApm::captureThrowable($exception)->send();
```

Make sure to import the facade at the top of your file:

```php
use ElasticApm;
```

### Lumen
not tested yet.
