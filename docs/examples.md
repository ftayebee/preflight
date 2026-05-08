# Preflight Examples

## Fresh Laravel Project Audit

```bash
composer require fahimtayebee/preflight --dev
php artisan vendor:publish --tag=preflight-config
php artisan preflight:doctor
php artisan preflight:audit
```

## Existing Laravel Admin Panel With Baseline

```bash
php artisan preflight:audit
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

## Laravel API Project With Sanctum

```php
'auth_middleware_keywords' => ['auth', 'auth:sanctum', 'verified', 'can:'],
```

```bash
php artisan preflight:audit --only=routes --only=requests
```

## CI Usage With SARIF

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif --fail-on-severity=critical
```

## Ignoring Noisy Rules

```php
'rules' => [
    'ROUTE_NO_MIDDLEWARE' => ['enabled' => false],
],
```

## Overriding Severity

```php
'rules' => [
    'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
        'enabled' => true,
        'severity' => 'medium',
    ],
],
```

## Custom Auth Middleware

```php
'scanners' => [
    'routes' => [
        'enabled' => true,
        'options' => [
            'auth_middleware_keywords' => ['auth', 'tenant-auth', 'verified', 'can:'],
        ],
    ],
],
```
