# Preflight CI Usage

Preflight is designed to run before deployment so teams can catch risky Laravel configuration, routes, controllers, models, migrations, and environment settings early.

CI is a good fit because Preflight can:

- fail a build when the score is too low
- fail only when serious severities appear
- export JSON or Markdown artifacts
- export SARIF for GitHub Code Scanning
- use a baseline while teams pay down existing findings

## Basic CI Command

```bash
php artisan preflight:audit
```

## Fail By Score

Use `--fail-under` to fail when the audit score is below a threshold:

```bash
php artisan preflight:audit --fail-under=80
```

This is useful when you want one broad quality gate across all severities.

## Fail By Severity

Use `--fail-on-severity` to fail when any issue exists at or above a severity:

```bash
php artisan preflight:audit --fail-on-severity=critical
php artisan preflight:audit --fail-on-severity=high
```

Severity order is:

```text
critical > high > medium > low > info
```

For example, `--fail-on-severity=high` fails on critical or high issues.

## Generate SARIF

SARIF output can be uploaded to GitHub Code Scanning:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif
```

You can combine SARIF output with a severity gate:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif --fail-on-severity=critical
```

## Upload SARIF To GitHub Code Scanning

Use the example workflow at:

```text
.github/workflows/preflight.yml.example
```

Copy it to:

```text
.github/workflows/preflight.yml
```

The workflow installs dependencies, runs Preflight, writes a SARIF file, and uploads it with:

```yaml
uses: github/codeql-action/upload-sarif@v3
```

## Baseline Usage In CI

For existing projects, generate a baseline first:

```bash
php artisan preflight:audit --baseline
```

Commit `preflight-baseline.json` if your team wants CI to ignore known existing findings.

Then run CI against new or changed findings:

```bash
php artisan preflight:audit --use-baseline --fail-on-severity=high
```

## Rule Override Strategy

Use rule overrides when your team agrees a rule should be treated differently from Preflight's default:

```php
'rules' => [
    'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
        'enabled' => true,
        'severity' => 'medium',
    ],
],
```

Severity overrides affect score calculation, reports, SARIF levels, and `--fail-on-severity`.

## Temporarily Disable Noisy Rules

When adopting Preflight, disable noisy rules sparingly and leave a code review note explaining why:

```php
'rules' => [
    'ROUTE_NO_MIDDLEWARE' => [
        'enabled' => false,
    ],
],
```

Prefer baselines for existing findings and rule disables for checks that are not relevant to the project.

## Baselines With Rule Config

Rule config is applied before baseline generation and filtering. A recommended adoption flow is:

```bash
php artisan preflight:rules
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

If you later change a rule severity, the baseline fingerprint stays stable because it is based on issue identity, not severity.

## Severity Filtering

Use `--severity` to narrow report output without changing the real score:

```bash
php artisan preflight:audit --severity=high
```

This displays critical and high issues while hiding medium, low, and info issues. Score calculation still uses all non-baselined and non-ignored findings.

## Recommended Command For First-Time Users

For a first CI rollout on an existing project:

```bash
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

This lets the team block new critical issues without immediately failing on all historical findings.

## Recommended Command For Mature Projects

For projects ready for stricter gates:

```bash
php artisan preflight:audit --use-baseline --fail-on-severity=high
```

For GitHub Code Scanning:

```bash
php artisan preflight:audit --format=sarif --output=storage/app/preflight.sarif --use-baseline --fail-on-severity=high
```
