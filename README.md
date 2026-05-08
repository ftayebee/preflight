# Preflight

A Laravel project audit and security checker for catching common risks before deployment.

> Public Preview: Preflight is currently in public preview. It uses static/project scanning and may produce false positives.

[![Tests](https://github.com/fahimtayebee/preflight/actions/workflows/tests.yml/badge.svg)](https://github.com/fahimtayebee/preflight/actions/workflows/tests.yml)
[![Latest Version](https://img.shields.io/packagist/v/fahimtayebee/preflight.svg)](https://packagist.org/packages/fahimtayebee/preflight)
[![License](https://img.shields.io/packagist/l/fahimtayebee/preflight.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/fahimtayebee/preflight.svg)](composer.json)
[![Laravel](https://img.shields.io/badge/Laravel-11%20%7C%2012-red.svg)](composer.json)

## Why Preflight Exists

Laravel projects often repeat risky mistakes before production deployment: unprotected routes, weak environment settings, unsafe controller patterns, sensitive migration fields, risky Composer packages, and missing request authorization.

Preflight scans an existing Laravel application and reports findings in the terminal, JSON, Markdown, or SARIF. It is scanner/report only: no dashboard UI, no database tables, and no host application modifications.

## Installation

```bash
composer require fahimtayebee/preflight --dev
```

Publish the config:

```bash
php artisan vendor:publish --tag=preflight-config
```

## Quick Start

```bash
php artisan preflight:audit
```

## Example Commands

```bash
php artisan preflight:audit
php artisan preflight:audit --format=json
php artisan preflight:audit --format=md --output=storage/app/preflight-report.md
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline
php artisan preflight:audit --fail-under=80
php artisan preflight:audit --fail-on-severity=critical
```

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan preflight:audit` | Run the audit and generate a report. |
| `php artisan preflight:rules` | List known rules and configured severities. |
| `php artisan preflight:config-check` | Validate Preflight configuration. |
| `php artisan preflight:doctor` | Check whether Preflight is ready in the host project. |
| `php artisan preflight:self-test` | Run Preflight internal health checks. |

See [docs/commands.md](docs/commands.md) for all command options.

## Report Formats

Preflight supports:

- `console`
- `json`
- `md`
- `sarif`

SARIF output can be uploaded to GitHub Code Scanning:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif
```

## Scanners

- `EnvScanner`
- `RouteScanner`
- `ControllerScanner`
- `ModelScanner`
- `MigrationScanner`
- `RequestScanner`
- `ComposerScanner`

## CI Usage

Use score-based gating:

```bash
php artisan preflight:audit --format=json --output=storage/app/preflight-report.json --fail-under=80
```

Use severity-based gating:

```bash
php artisan preflight:audit --fail-on-severity=critical
```

Generate SARIF for GitHub Code Scanning:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif --fail-on-severity=critical
```

An example GitHub Code Scanning workflow is included at [.github/workflows/preflight.yml.example](.github/workflows/preflight.yml.example).

## Baseline Usage

Baselines help teams adopt Preflight on existing projects without failing CI on every reviewed historical finding.

Generate a baseline:

```bash
php artisan preflight:audit --baseline
```

Run while ignoring baseline findings:

```bash
php artisan preflight:audit --use-baseline
```

Recommended first-time flow:

```bash
php artisan preflight:audit
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

Review baseline changes before committing them.

## Rule Configuration

Rules can be disabled or have their severity changed in `config/preflight.php`:

```php
'rules' => [
    'ROUTE_NO_MIDDLEWARE' => [
        'enabled' => false,
    ],

    'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
        'enabled' => true,
        'severity' => 'medium',
    ],
],
```

Disabled rules are removed before reporting, scoring, baselines, SARIF, and CI gates. Severity overrides affect reports, score calculation, `--fail-on-severity`, and SARIF levels.

## Scanner Options

Scanner behavior can be tuned without disabling entire rules:

```php
'scanners' => [
    'routes' => [
        'enabled' => true,
        'options' => [
            'auth_middleware_keywords' => ['auth', 'auth:sanctum', 'tenant-auth'],
        ],
    ],
],
```

## Documentation

- [Configuration](docs/configuration.md)
- [CI](docs/ci.md)
- [Commands](docs/commands.md)
- [Examples](docs/examples.md)
- [False positives](docs/false-positives.md)
- [Limitations](docs/limitations.md)
- [Rules](docs/rules.md)
- [Release checklist](docs/release-checklist.md)

## Limitations

Preflight is honest about what it is and is not:

- It does not replace a professional security review.
- It may produce false positives.
- It does not guarantee a project is secure.
- It does not perform dependency vulnerability database scanning.
- It does not execute application code or perform runtime testing.

## Roadmap

- More scanner accuracy improvements.
- Better Laravel policy detection.
- Blade scanner.
- More framework-specific checks.
- Stable v1.0 rule API.

## Contributing

Contributions are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

Before submitting a pull request, run:

```bash
composer validate --strict
vendor/bin/phpunit
```

## Security

Please report security concerns privately. See [SECURITY.md](SECURITY.md).

## License

Preflight is open-sourced software licensed under the [MIT license](LICENSE).
