# Changelog

All notable changes to `preflight` will be documented in this file.

## [0.5.0] - Unreleased

### Added

- Static HTML report format.
- Standalone offline report rendering.
- HTML report support for summary cards, issue details, baseline metadata, changed-files metadata, scanner timing, and explainability.
- Optional --open flag for local report viewing.
- HTML report documentation and CI artifact example.

## [0.4.0] - Unreleased

### Added

- Changed-files audit mode with --changed.
- Base ref support with --base-ref.
- Scanner context for changed-file filtering.
- Changed mode metadata in reports.
- Changed-files documentation.

### Improved

- CI and pull request workflow documentation.

## [0.3.0] - Unreleased

### Added

- Dedicated preflight:baseline command.
- Baseline show, generate, prune, and clear actions.
- Baseline diff metadata.
- Baseline CI enforcement with --fail-on-new.
- Resolved baseline entry detection.

### Improved

- preflight:audit --use-baseline now reports ignored, new, and resolved baseline counts.
- Baseline documentation for existing project adoption.

## [0.2.0] - Unreleased

### Added

- PolicyScanner.
- BladeScanner.
- AuthScanner.
- Fix examples documentation.
- Explainable audit output with --explain.
- Confidence summary in reports.
- Audit presets: relaxed, default, and strict.
- SARIF rule help URLs.
- False-positive guidance metadata.
- Rule fixture tests for important findings.
- Explainability documentation.
- Tested-against README section.

### Improved

- Laravel-specific audit coverage.
- Rule recommendations.

## [0.1.0] - 2026-05-09

### Added

- Laravel project audit command.
- Console, JSON, Markdown, and SARIF report formats.
- GitHub Code Scanning compatible SARIF output.
- Env, route, controller, model, migration, request, and Composer scanners.
- Rule registry with stable issue codes.
- Rule enable/disable configuration.
- Rule severity overrides.
- Scanner-specific options.
- Baseline generation and baseline filtering.
- Severity filtering.
- Score-based CI gate.
- Severity-based CI gate.
- Config validation command.
- Rule listing command.
- Doctor command.
- Self-test command.
- GitHub Actions example workflow.
- CI, configuration, commands, examples, false-positive, limitation, and release docs.
