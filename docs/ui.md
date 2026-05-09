# Optional Report UI

Preflight includes an optional Laravel route UI for viewing generated static HTML reports in a browser.

The UI is read-only. It lists existing HTML reports and displays the latest or a selected report. It does not run audits from the browser, create database tables, store report history, or add authentication scaffolding.

## Why It Is Disabled By Default

Audit reports can contain sensitive project details such as file paths, route names, controller names, and security findings. For that reason, the route UI is disabled by default and should only be enabled in local, development, testing, or otherwise protected environments.

## Enable The UI

Publish the config:

```bash
php artisan vendor:publish --tag=preflight-config
```

Then enable the UI in `config/preflight.php`:

```php
'ui' => [
    'enabled' => true,
    'path' => 'preflight',
    'middleware' => ['web', 'auth'],
    'allowed_environments' => ['local', 'testing'],
    'reports_directory' => storage_path('app/preflight'),
    'fallback_report' => storage_path('app/preflight-report.html'),
    'allow_run_audit_from_ui' => false,
],
```

The `allow_run_audit_from_ui` option is reserved for future use and remains disabled in this version.

## Routes

When enabled, Preflight registers:

- `GET /preflight`
- `GET /preflight/latest`
- `GET /preflight/report/{filename}`

If you change `ui.path`, the route prefix changes too.

## Generate Reports

The UI only reads generated HTML reports. Generate reports from the CLI:

```bash
php artisan preflight:audit --format=html
php artisan preflight:audit --format=html --output=storage/app/preflight/report.html
```

Then visit:

```text
/preflight
/preflight/latest
```

## Middleware Protection

Use middleware to protect the UI in shared environments:

```php
'middleware' => ['web', 'auth'],
```

Preflight does not add authentication scaffolding. It uses the middleware already available in your Laravel application.

## Environment Protection

The UI checks `ui.allowed_environments` on every request:

```php
'allowed_environments' => ['local', 'testing'],
```

By default, production is blocked. If access is attempted from a disallowed environment, Preflight returns HTTP `403`.

## Publish Views

Package views work without publishing. To customize them:

```bash
php artisan vendor:publish --tag=preflight-views
```

Published views are stored under `resources/views/vendor/preflight`.

## File Safety

The UI only serves `.html` and `.htm` files from the configured reports directory or the configured fallback report path. Path traversal and non-HTML files are rejected.

## Limitations

- The UI is read-only.
- It does not run audits.
- It does not store history in a database.
- It should not be exposed publicly unless protected by middleware and environment restrictions.
- It only displays generated static HTML reports.
