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

### Policy Scanner

```php
'policies' => [
    'enabled' => true,
    'options' => [
        'important_model_names' => ['User', 'Admin', 'Payment', 'Order'],
        'ignored_models' => ['AuditLog'],
        'required_policy_methods' => ['viewAny', 'view', 'create', 'update', 'delete'],
        'allow_policy_before_true' => true,
    ],
],
```

Policy detection is convention-based: `app/Models/Order.php` maps to `app/Policies/OrderPolicy.php`.

### Blade Scanner

```php
'blade' => [
    'enabled' => true,
    'options' => [
        'authorization_directives' => ['@can', '@cannot', '@role', '@permission', '@auth', '@unless'],
        'admin_action_keywords' => ['delete', 'destroy', 'edit', 'role', 'permission', 'admin', 'payment'],
        'ignored_view_paths' => ['resources/views/vendor/*'],
    ],
],
```

The `BLADE_UNGUARDED_ADMIN_ACTION` rule is disabled by default because it is intentionally heuristic and can be noisy.

### Auth Scanner

```php
'auth' => [
    'enabled' => true,
    'options' => [
        'auth_route_keywords' => ['login', 'register', 'password', 'forgot-password', 'reset-password'],
        'rate_limit_keywords' => ['throttle', 'rate'],
        'admin_authorization_keywords' => ['can:', 'permission:', 'role:', 'abilities:', 'ability:'],
        'api_auth_keywords' => ['auth:sanctum', 'auth:api', 'auth:passport', 'token', 'ability:', 'abilities:'],
        'public_route_allowlist' => ['internal/login-preview'],
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

    'BLADE_UNGUARDED_ADMIN_ACTION' => [
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

## Changed Files

Configure pull request changed-files mode:

```php
'changed_files' => [
    'enabled' => true,
    'default_base_ref' => 'origin/main',
    'fallback_to_full_scan' => true,
    'include_project_scanners' => true,
    'file_scanners' => [
        'controllers',
        'models',
        'migrations',
        'requests',
        'composer',
        'blade',
        'policies',
    ],
    'project_scanners' => [
        'env',
        'routes',
        'auth',
    ],
],
```

`default_base_ref` is used when `--base-ref` is not passed.

`fallback_to_full_scan` controls whether Git diff failures fall back to a normal full audit or fail the command.

`include_project_scanners` controls whether project-wide scanners still run in changed mode.

`file_scanners` lists scanners that can be filtered to changed files.

`project_scanners` lists scanners that inspect project-level state.

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
