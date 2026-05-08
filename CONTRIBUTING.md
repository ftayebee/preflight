# Contributing

Thanks for helping improve Preflight.

Preflight is a static Laravel project audit helper. Contributions should keep the package scanner/report only and must not mutate the host Laravel application.

## Development Setup

Install dependencies:

```bash
composer install
```

Run Composer validation:

```bash
composer validate --strict
```

Run tests:

```bash
vendor/bin/phpunit
```

Run PHP lint:

```bash
find src config tests -name "*.php" -print0 | xargs -0 -n1 php -l
```

On Windows PowerShell:

```powershell
$files = Get-ChildItem -Recurse -Filter *.php | Where-Object { $_.FullName -notlike '*\vendor\*' }; foreach ($file in $files) { php -l $file.FullName }
```

## Adding A Scanner

- Add the scanner class under `src/Scanners`.
- Implement `Scanners\Contracts\ScannerInterface`.
- Keep the scanner read-only.
- Handle missing files and directories gracefully.
- Register the scanner in `AuditManager`.
- Add scanner defaults to `config/preflight.php`.
- Add tests with controlled fixtures where possible.
- Update docs and README scanner lists.

## Adding A Rule

- Add the issue code to `src/Core/RuleRegistry.php`.
- Add the matching default rule config to `config/preflight.php`.
- Use a stable, uppercase issue code.
- Include scanner, default severity, description, and recommendation.
- Add or update tests that prove the rule can be produced.
- Update `docs/rules.md`.

## Adding Scanner Options

- Add safe defaults in `config/preflight.php`.
- Read values through `ScannerOptionResolver`.
- Keep fallback defaults in the scanner when appropriate.
- Update `docs/configuration.md`.
- Add tests for custom option behavior.

## Documentation

Update documentation whenever behavior, commands, options, or rules change:

- `README.md`
- `docs/configuration.md`
- `docs/commands.md`
- `docs/ci.md`
- `docs/rules.md`
- `docs/false-positives.md`

## Pull Request Checklist

- Tests added or updated.
- Documentation updated.
- RuleRegistry updated if issue codes changed.
- Config updated if scanner options changed.
- No host application mutation added.
- `composer validate --strict` passes.
- `vendor/bin/phpunit` passes.

## Code Style

- Use strict types where appropriate.
- Prefer small, focused classes.
- Keep scanners independent.
- Avoid hardcoded absolute paths.
- Do not introduce unsafe Composer scripts.
- Do not add dashboards, database tables, or host app mutations.
