# False Positives

Preflight uses static scanning. It reads files, routes, configuration, and dependency metadata without executing your application logic. That keeps scans safe and CI-friendly, but it also means some findings may be false positives.

## Use Explanations First

Run with `--explain` when a finding is unclear:

```bash
php artisan preflight:audit --explain
```

Explain mode adds impact, fix guidance, examples, documentation links, and false-positive guidance when rule metadata is available.

## Interpret Confidence

Confidence is separate from severity:

- High confidence means Preflight found direct evidence.
- Medium confidence means the pattern is strong but project context may matter.
- Low confidence means the finding is heuristic and should be reviewed before treating it as a release blocker.

Use confidence to decide review priority. Do not ignore a high-severity issue only because another finding in the same report is noisy.

## Use Presets For Adoption

The relaxed preset hides low-confidence findings from displayed/exported results without permanently changing config:

```bash
php artisan preflight:audit --preset=relaxed
```

Strict mode is useful once the team wants all enabled findings visible:

```bash
php artisan preflight:audit --preset=strict
```

## Start With A Baseline

For existing projects, generate a baseline first:

```bash
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline
```

This lets your team focus on new findings while gradually reviewing older ones.

Recommended first-time workflow:

```bash
php artisan preflight:audit
php artisan preflight:audit --baseline
php artisan preflight:audit --use-baseline --fail-on-severity=critical
```

Use a baseline for existing reviewed findings. Do not use a baseline to suppress new critical issues without review.

To regenerate a baseline:

```bash
php artisan preflight:audit --baseline
```

Review baseline diffs before committing. In CI, combine baselines with severity gates:

```bash
php artisan preflight:audit --use-baseline --fail-on-severity=high
```

## Disable A Rule

If a rule does not apply to your project, disable it:

```php
'rules' => [
    'ROUTE_NO_MIDDLEWARE' => [
        'enabled' => false,
    ],
],
```

Prefer disabling rules only when the check is not relevant to the project.

## Override Severity

If a rule is useful but too noisy at its default severity, override it:

```php
'rules' => [
    'REQUEST_AUTHORIZE_ALWAYS_TRUE' => [
        'enabled' => true,
        'severity' => 'medium',
    ],
],
```

Severity overrides affect score, reports, CI gates, and SARIF levels.

## Customize Scanner Options

Scanner options are often better than disabling a rule:

```php
'scanners' => [
    'routes' => [
        'options' => [
            'auth_middleware_keywords' => ['auth', 'tenant-auth', 'verified', 'can:'],
        ],
    ],
],
```

Tune rules as narrowly as possible before disabling broad categories. Examples:

- add public routes to `public_route_allowlist`
- add custom controller authorization helpers to `authorization_keywords`
- add intentionally public FormRequests to `allow_authorize_true_for`
- add ignored Blade paths to `ignored_view_paths`
- customize Composer debug package names with `risky_packages_in_require`

## Reporting A False Positive

Include:

- Preflight version
- Laravel version
- PHP version
- rule code
- relevant file snippet
- expected behavior
- actual finding

Example:

```text
Rule: CONTROLLER_MISSING_AUTHORIZATION
File: app/Http/Controllers/InvoiceController.php
Expected: should pass because authorization is handled by custom tenant middleware.
Actual: Preflight reported missing authorization.
Config workaround: added tenant-auth to auth_middleware_keywords.
```
