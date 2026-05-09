# Preflight Commands

## preflight:audit

Runs the project audit.

Options:

- `--format=console|json|md|sarif`
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

## preflight:rules

Lists known rules and configured severities.

Options:

- `--format=console|json|md`

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
