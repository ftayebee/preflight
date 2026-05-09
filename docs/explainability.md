# Explainability

Preflight findings are meant to be reviewable, tunable, and honest about uncertainty.

## Severity

Severity estimates the possible impact if a finding is real.

- `critical`: likely production security exposure or credential-sensitive risk.
- `high`: strong security concern that should be reviewed before release.
- `medium`: meaningful hardening or correctness issue.
- `low`: useful signal, often contextual.
- `info`: hygiene or maintainability signal.

Severity can be overridden in `config/preflight.php` when a team has a different risk model.

## Confidence

Confidence estimates how likely the scanner is to be correct.

- `high`: direct evidence, such as `APP_DEBUG=true` or raw Blade output.
- `medium`: strong pattern, but project context may matter.
- `low`: heuristic signal that is useful for review but may be noisy.

Reports include `confidence_counts` so teams can separate high-certainty findings from review hints.

## False Positives

False positives happen because Preflight scans static project structure, route metadata, and code patterns. It does not understand every custom authorization system, deployment environment, package wrapper, or template convention.

When a finding is intentional, prefer tuning scanner options before disabling broad coverage.

## Using --explain

Run:

```bash
php artisan preflight:audit --explain
```

Console output expands each issue with:

- why it matters
- how to fix it
- bad and better examples when available
- false-positive guidance
- rule documentation URL

JSON, Markdown, and SARIF reports include explanation metadata where available.

## Presets

Use presets to change runtime reporting behavior without changing published config.

```bash
php artisan preflight:audit --preset=relaxed
php artisan preflight:audit --preset=default
php artisan preflight:audit --preset=strict --fail-on-severity=high
```

`relaxed` hides low-confidence findings and is suitable for a first run.

`default` preserves normal Preflight behavior.

`strict` includes all enabled rules and low-confidence findings, which is better for mature CI.

## Tuning Noisy Rules

Common tuning options:

```php
'ignored_issue_codes' => [
    'ROUTE_NO_MIDDLEWARE',
],

'scanners' => [
    'controllers' => [
        'options' => [
            'authorization_keywords' => ['tenantCan', 'checksPermission'],
        ],
    ],
    'blade' => [
        'options' => [
            'ignored_view_paths' => ['resources/views/vendor/*'],
        ],
    ],
],
```

For adoption, a baseline can help separate existing reviewed findings from new regressions:

```bash
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline
```

## Reporting False Positives

When reporting a false positive, include:

- rule code
- relevant route, file, or config snippet
- expected behavior
- why the current finding is intentional
- framework/package versions when relevant

Report issues at:

https://github.com/fahimtayebee/preflight/issues
