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

## Auditing Blade Templates

```bash
php artisan preflight:audit --only=blade
```

Useful for reviewing raw output, missing CSRF directives, and destructive forms:

```blade
<form method="POST" action="/users/{{ $user->id }}/delete">
    @csrf
    @method('DELETE')
    <button>Delete</button>
</form>
```

`BLADE_UNGUARDED_ADMIN_ACTION` is disabled by default. Enable it when you want low-confidence hints for sensitive links, buttons, and forms.

## Auditing Policies

```bash
php artisan preflight:audit --only=policies
```

Tune important models for your domain:

```php
'policies' => [
    'options' => [
        'important_model_names' => ['User', 'Team', 'Project', 'Invoice'],
        'ignored_models' => ['AuditLog'],
    ],
],
```

## Auditing Auth Routes

```bash
php artisan preflight:audit --only=auth
```

Typical protected admin routes combine identity and authorization:

```php
Route::get('/admin/users', [AdminUserController::class, 'index'])
    ->middleware(['auth', 'permission:users.view']);
```

## Generate A Local HTML Report

```bash
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html
```

## Generate An Explainable HTML Report

```bash
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html --explain
```

## Generate A CI Artifact HTML Report

```bash
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html --preset=relaxed
```

## Generate HTML With Changed Files Mode

```bash
php artisan preflight:audit --changed --base-ref=origin/main --format=html --output=storage/app/preflight-report.html
```
