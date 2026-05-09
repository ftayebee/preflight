# Preflight Commands

## preflight:audit

Runs the project audit.

Options:

- `--format=console|json|md|sarif|html`
- `--output=path`
- `--baseline`
- `--use-baseline`
- `--severity=critical|high|medium|low|info`
- `--fail-under=80`
- `--fail-on-severity=critical|high|medium|low|info`
- `--only=env`
- `--skip=models`
- `--explain`
- `--preset=relaxed|default|strict`
- `--changed`
- `--base-ref=origin/main`
- `--open`

HTML examples:

```bash
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html
php artisan preflight:audit --format=html --output=storage/app/preflight-report.html --explain
php artisan preflight:audit --format=html --open
```

## preflight:rules

Lists known rules and configured severities.

Options:

- `--format=console|json|md`

## preflight:baseline

Manages the baseline file used to adopt Preflight on existing projects without hiding new issues.

Signature:

```bash
php artisan preflight:baseline {action=show}
```

Actions:

- `show`: display the current baseline file path, entry count, and entries grouped by issue code.
- `generate`: run an audit and store the current issue fingerprints in the baseline file.
- `prune`: remove baseline entries that no longer appear in the current audit.
- `clear`: delete the baseline file.

Options:

- `--format=console|json`
- `--force`
- `--fail-on-new`
- `--fail-on-resolved`

Examples:

```bash
php artisan preflight:baseline show
php artisan preflight:baseline generate
php artisan preflight:baseline prune
php artisan preflight:baseline clear --force
php artisan preflight:baseline show --format=json
php artisan preflight:baseline show --fail-on-new
```

`--fail-on-new` returns exit code `1` when current audit results contain issues that are not in the baseline.

`--fail-on-resolved` returns exit code `1` when prune removes resolved baseline entries.

## preflight:config-check

Validates Preflight config.

Options:

- `--format=console|json`

## preflight:doctor

Checks host project readiness.

Options:

- `--format=console|json`
- `--output=path`

## preflight:self-test

Runs Preflight internal health checks.

Options:

- `--format=console|json`
