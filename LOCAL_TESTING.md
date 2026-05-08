# Local Testing Preflight

Use this guide to test Preflight inside a fresh Laravel project before publishing it to Packagist.

The examples assume this package lives at:

```text
D:\000 PERSONAL XAMPP\00-PROJECTS\00-LARAGUARD
```

Adjust the path if your local checkout is somewhere else.

## 1. Create A Fresh Laravel Project

From the directory where you keep test projects, run:

```bash
composer create-project laravel/laravel preflight-test
cd preflight-test
```

## 2. Add The Local Path Repository

Open the fresh Laravel project's `composer.json` and add a `repositories` section:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "D:/000 PERSONAL XAMPP/00-PROJECTS/00-LARAGUARD",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

If `composer.json` already has a `repositories` section, add the Preflight entry to the existing array.

On macOS or Linux, the path may look more like:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../00-LARAGUARD",
            "options": {
                "symlink": true
            }
        }
    ]
}
```

## 3. Require The Local Package

Install Preflight from the local path repository:

```bash
composer require fahimtayebee/preflight:@dev --dev
```

Composer should install the package from your local filesystem path instead of Packagist.

## 4. Confirm Auto-Discovery

Check that Laravel discovered the package provider:

```bash
php artisan package:discover
```

You should see package discovery complete without errors.

## 5. Publish The Config

Publish Preflight's config file:

```bash
php artisan vendor:publish --tag=preflight-config
```

This creates:

```text
config/preflight.php
```

## 6. Run The Audit Command

Run the default console report:

```bash
php artisan preflight:audit
```

Run JSON output:

```bash
php artisan preflight:audit --format=json
```

Save a Markdown report:

```bash
php artisan preflight:audit --format=md --output=storage/app/preflight-report.md
```

Run with a deployment score gate:

```bash
php artisan preflight:audit --fail-under=80
```

Show only high and critical issues:

```bash
php artisan preflight:audit --severity=high
```

Fail when critical issues exist:

```bash
php artisan preflight:audit --fail-on-severity=critical
```

Save a SARIF report:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif
```

Run only selected scanners:

```bash
php artisan preflight:audit --only=env --only=routes
```

Skip selected scanners:

```bash
php artisan preflight:audit --skip=models
```

Generate a baseline from current issues:

```bash
php artisan preflight:audit --baseline
```

Run while ignoring baseline issues:

```bash
php artisan preflight:audit --use-baseline
```

List Preflight rules:

```bash
php artisan preflight:rules
php artisan preflight:rules --format=json
php artisan preflight:rules --format=md
php artisan preflight:config-check
php artisan preflight:config-check --format=json
```

Example rule config:

```php
'ROUTE_NO_MIDDLEWARE' => [
    'enabled' => false,
],

'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
    'enabled' => true,
    'severity' => 'medium',
],
```

Example scanner option customizations:

```php
'routes' => [
    'enabled' => true,
    'options' => [
        'auth_middleware_keywords' => ['auth', 'tenant-auth', 'verified', 'can:'],
    ],
],

'composer' => [
    'enabled' => true,
    'options' => [
        'risky_packages_in_require' => [
            'vendor/internal-debug-tool' => 'medium',
        ],
    ],
],

'requests' => [
    'enabled' => true,
    'options' => [
        'allow_authorize_true_for' => ['PublicContactRequest'],
    ],
],
```

## 7. Test A Finding

In the fresh Laravel project's `.env`, temporarily set:

```dotenv
APP_DEBUG=true
```

Then run:

```bash
php artisan preflight:audit --only=env
```

Preflight should report debug mode as a critical issue.

Set it back afterward:

```dotenv
APP_DEBUG=false
```

## 8. Refresh Local Package Changes

When you edit Preflight locally, the Laravel test project should see changes immediately if Composer symlinked the package.

If changes are not reflected, refresh Composer autoload files in the Laravel test project:

```bash
composer dump-autoload
php artisan optimize:clear
```

## Troubleshooting

If Composer cannot find the package, confirm:

- The local path in `repositories.url` points to the Preflight package root.
- Preflight's package root contains `composer.json`.
- The package name is `fahimtayebee/preflight`.
- You used `composer require fahimtayebee/preflight:@dev --dev`.

If `php artisan preflight:audit` is missing, run:

```bash
composer dump-autoload
php artisan package:discover
php artisan optimize:clear
```
