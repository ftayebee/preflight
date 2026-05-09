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
php artisan preflight:audit --explain
php artisan preflight:audit --preset=relaxed
php artisan preflight:audit --preset=strict --fail-on-severity=high
```

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan preflight:audit` | Run the audit and generate a report. |
| `php artisan preflight:baseline` | Show, generate, prune, or clear the baseline. |
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
- `html`

SARIF output can be uploaded to GitHub Code Scanning:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif
```

## HTML Reports

HTML reports are static, shareable audit reports for local review, clients, and CI artifacts. They are standalone files with inline CSS and no external assets.

```bash
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html --explain
```

If `--format=html` is used without `--output`, Preflight saves to the configured default path: `storage/app/preflight-report.html`.

## Explainability And Presets

Use `--explain` when reviewing findings locally:

```bash
php artisan preflight:audit --explain
```

Expanded output includes why the finding matters, how to fix it, bad and better examples when available, false-positive guidance, and a rule docs URL.

Use presets to adjust runtime reporting without changing config:

```bash
php artisan preflight:audit --preset=relaxed
php artisan preflight:audit --preset=strict --fail-on-severity=high
```

- `relaxed` hides low-confidence findings for first adoption runs.
- `default` preserves normal behavior.
- `strict` includes all enabled rules and low-confidence findings for mature CI.

Reports include `confidence_counts` so teams can see how many findings are high, medium, or low confidence.

## Changed Files Mode

Changed files mode is useful for pull requests. It uses Git diff output to scan changed files with file-based scanners, while still allowing project-wide checks such as env, routes, and auth.

```bash
php artisan preflight:audit --changed --base-ref=origin/main
```

The default base ref and fallback behavior are configurable. See [Changed files mode](docs/changed-files.md).

## False-Positive Guidance

Preflight reports include confidence levels and, with `--explain`, rule-specific guidance for handling false positives. Prefer tuning scanner options or using a reviewed baseline before disabling broad rule groups.

Examples:

```bash
php artisan preflight:audit --explain
php artisan preflight:audit --preset=relaxed
```

See [False positives](docs/false-positives.md) and [Explainability](docs/explainability.md).

## Tested Against

Preflight is tested using fixture projects and real Laravel project structures, including:

- Fresh Laravel-style project structure
- Laravel API-style project structure
- Laravel admin-panel style project structure
- Risky project fixtures with known findings
- Clean project fixtures with expected passing results

These tests improve rule reliability, but they do not claim complete security coverage.

## Scanners

- `EnvScanner`
- `RouteScanner`
- `ControllerScanner`
- `ModelScanner`
- `MigrationScanner`
- `RequestScanner`
- `ComposerScanner`
- `PolicyScanner`
- `BladeScanner`
- `AuthScanner`

`BLADE_UNGUARDED_ADMIN_ACTION` is disabled by default because Blade authorization hints are heuristic and may be noisy.

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

## Baseline Management

Baselines help teams adopt Preflight on existing projects without failing CI on every reviewed historical finding.

Generate a baseline:

```bash
php artisan preflight:baseline generate
php artisan preflight:audit --baseline
```

Show the current baseline:

```bash
php artisan preflight:baseline show
```

Remove entries for issues that have been fixed:

```bash
php artisan preflight:baseline prune
```

Clear the baseline:

```bash
php artisan preflight:baseline clear --force
```

Run while ignoring baseline findings:

```bash
php artisan preflight:audit --use-baseline
```

Recommended first-time flow:

```bash
php artisan preflight:audit
php artisan preflight:baseline generate
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

CI can enforce that no new unbaselined issues were introduced:

```bash
php artisan preflight:baseline show --fail-on-new
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
- [Changed files](docs/changed-files.md)
- [Examples](docs/examples.md)
- [Explainability](docs/explainability.md)
- [Fix examples](docs/fixes.md)
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
- It uses static and convention-based checks, so confidence and false-positive guidance should be reviewed alongside severity.

## Roadmap

- More scanner accuracy improvements.
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
