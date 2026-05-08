# Preflight Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag=preflight-config
```

Then edit:

```text
config/preflight.php
```

## Test Or Alternate Project Root

By default, Preflight scans Laravel's `base_path()`. Tests or advanced integrations can override the root:

```php
'base_path' => '/path/to/project',
```

When `base_path` is set, scanners resolve `.env`, `composer.json`, `app`, `routes`, and `database` paths from that directory.

## Scanner Enablement

Enable or disable scanners without removing them from the package:

```php
'scanners' => [
    'routes' => [
        'enabled' => true,
    ],
    'composer' => [
        'enabled' => false,
    ],
],
```

Selection priority is:

```text
--only / --skip > scanners config > enabled_scanners fallback
```

## Scanner Options

Each scanner supports an `options` array. These options let teams tune Preflight without disabling full rules.

### Custom Authorization Keywords

```php
'routes' => [
    'enabled' => true,
    'options' => [
        'auth_middleware_keywords' => [
            'auth',
            'tenant-auth',
            'verified',
            'can:',
        ],
    ],
],
```

### Public Route Allowlist

```php
'routes' => [
    'options' => [
        'public_route_allowlist' => [
            '/',
            'login',
            'health',
            'status/*',
        ],
    ],
],
```

### Custom Risky Composer Packages

```php
'composer' => [
    'options' => [
        'risky_packages_in_require' => [
            'barryvdh/laravel-debugbar' => 'high',
            'vendor/internal-debug-tool' => 'medium',
        ],
    ],
],
```

### Allow Public FormRequests

```php
'requests' => [
    'options' => [
        'allow_authorize_true_for' => [
            'PublicContactRequest',
        ],
    ],
],
```

## Rule Enablement

Disable a rule when it does not apply to a project:

```php
'rules' => [
    'ROUTE_NO_MIDDLEWARE' => [
        'enabled' => false,
    ],
],
```

Disabled rules are removed before reporting, scoring, baselines, SARIF, and CI gates.

## Rule Severity Overrides

Override a rule's severity:

```php
'rules' => [
    'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
        'enabled' => true,
        'severity' => 'medium',
    ],
],
```

Severity overrides affect reports, scoring, `--fail-on-severity`, and SARIF levels.

## Ignored Issue Codes

Ignore exact issue codes:

```php
'ignored_issue_codes' => [
    'ROUTE_NO_MIDDLEWARE',
],
```

## Baseline File

Change the baseline path:

```php
'baseline_file' => base_path('preflight-baseline.json'),
```

## SARIF Config

Customize SARIF tool metadata:

```php
'sarif' => [
    'tool_name' => 'Preflight',
    'information_uri' => 'https://github.com/fahimtayebee/preflight',
],
```

## Config Check

Validate Preflight config:

```bash
php artisan preflight:config-check
php artisan preflight:config-check --format=json
```

Warnings do not fail the command. Errors return exit code `1`.
