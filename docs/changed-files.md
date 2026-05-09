# Changed Files Mode

Changed files mode is designed for pull request workflows where teams want fast feedback on files touched by a branch.

```bash
php artisan preflight:audit --changed
php artisan preflight:audit --changed --base-ref=origin/main
php artisan preflight:audit --changed --preset=relaxed --fail-on-severity=critical
php artisan preflight:audit --changed --use-baseline --fail-on-severity=high
```

## How It Works

Preflight runs:

```bash
git diff --name-only origin/main...HEAD
```

The base ref can be changed with `--base-ref` or `changed_files.default_base_ref`.

File-based scanners only inspect matching changed files. Project-wide scanners can still run because some findings depend on route registration, environment state, or application-wide middleware.

## File And Project Scanners

File scanners can be filtered by changed files:

- `controllers`
- `models`
- `migrations`
- `requests`
- `composer`
- `blade`
- `policies`

Project scanners run when `changed_files.include_project_scanners` is true:

- `env`
- `routes`
- `auth`

Use `--only` and `--skip` to override the default scanner selection for a specific run.

## Configuration

```php
'changed_files' => [
    'enabled' => true,
    'default_base_ref' => 'origin/main',
    'fallback_to_full_scan' => true,
    'include_project_scanners' => true,
    'file_scanners' => ['controllers', 'models', 'migrations', 'requests', 'composer', 'blade', 'policies'],
    'project_scanners' => ['env', 'routes', 'auth'],
],
```

If Git diff fails and `fallback_to_full_scan` is true, Preflight runs a normal full audit and reports a changed mode warning. If it is false, the command exits with code `1`.

## GitHub Actions

Use full history when comparing with `origin/main`:

```yaml
- uses: actions/checkout@v4
  with:
    fetch-depth: 0

- name: Run Preflight on changed files
  run: php artisan preflight:audit --changed --base-ref=origin/main --preset=relaxed --fail-on-severity=critical
```

SARIF example:

```bash
php artisan preflight:audit --changed --base-ref=origin/main --format=sarif --output=storage/app/preflight.sarif
```

## GitLab CI

```yaml
preflight:
  script:
    - composer install --no-interaction --prefer-dist --no-progress
    - php artisan preflight:audit --changed --base-ref=origin/main --fail-on-severity=critical
```

## Limitations

Changed mode depends on Git history. Shallow clones may need deeper fetch settings.

Some findings require a full project scan. Route, env, and auth checks are project-wide by default.

Composer checks only run when `composer.json` or `composer.lock` changed.

Run a scheduled full audit alongside PR changed-files checks for best coverage.
