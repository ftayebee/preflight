<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use Illuminate\Console\Command;

final class ConfigCheckCommand extends Command
{
    protected $signature = 'preflight:config-check
        {--format=console : Output format: console or json}';

    protected $description = 'Validate Preflight configuration.';

    /** @var array<int, string> */
    private array $severities = ['critical', 'high', 'medium', 'low', 'info'];

    /** @var array<int, string> */
    private array $formats = ['console', 'json', 'md', 'markdown', 'sarif'];

    public function handle(): int
    {
        $errors = [];
        $warnings = [];
        $passed = ['Config file loaded'];

        $this->validateSeverityLevels($errors, $passed);
        $this->validateRules($warnings);
        $this->validateScanners($errors, $warnings);
        $this->validateGeneral($errors);

        $format = strtolower((string) $this->option('format'));

        if ($format === 'json') {
            $this->line(json_encode([
                'passed' => $errors === [],
                'errors' => $errors,
                'warnings' => $warnings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($format !== 'console') {
            $this->error('Invalid format. Supported formats: console, json');

            return self::FAILURE;
        }

        $this->line('Preflight Config Check');
        $this->line('');
        $this->line('Passed:');
        foreach ($passed as $item) {
            $this->line('- ' . $item);
        }

        $this->line('');
        $this->line('Warnings:');
        foreach ($warnings === [] ? ['None'] : $warnings as $item) {
            $this->line('- ' . $item);
        }

        $this->line('');
        $this->line('Errors:');
        foreach ($errors === [] ? ['None'] : $errors as $item) {
            $this->line('- ' . $item);
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $passed
     */
    private function validateSeverityLevels(array &$errors, array &$passed): void
    {
        $levels = config('preflight.severity_levels');

        if (! is_array($levels)) {
            $errors[] = 'severity_levels must be an array';
            return;
        }

        foreach ($this->severities as $severity) {
            if (! array_key_exists($severity, $levels)) {
                $errors[] = "severity_levels must contain {$severity}";
            }
        }

        if ($errors === []) {
            $passed[] = 'Severity levels valid';
        }
    }

    /**
     * @param array<int, string> $warnings
     */
    private function validateRules(array &$warnings): void
    {
        foreach ((array) config('preflight.rules', []) as $code => $rule) {
            if (! is_array($rule)) {
                $warnings[] = "Rule {$code} config should be an array";
                continue;
            }

            if (array_key_exists('enabled', $rule) && ! is_bool($rule['enabled'])) {
                $warnings[] = "Rule {$code} enabled value should be boolean";
            }

            if (array_key_exists('severity', $rule) && (! is_string($rule['severity']) || ! in_array($rule['severity'], $this->severities, true))) {
                $warnings[] = 'Rule ' . $code . ' has invalid severity "' . (string) $rule['severity'] . '"';
            }
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $warnings
     */
    private function validateScanners(array &$errors, array &$warnings): void
    {
        foreach ((array) config('preflight.scanners', []) as $scanner => $settings) {
            if (! is_array($settings)) {
                $errors[] = "Scanner {$scanner} config must be an array";
                continue;
            }

            if (array_key_exists('enabled', $settings) && ! is_bool($settings['enabled'])) {
                $errors[] = "Scanner {$scanner} enabled value must be boolean";
            }

            if (array_key_exists('options', $settings) && ! is_array($settings['options'])) {
                $errors[] = "Scanner {$scanner} options must be an array";
            }

            foreach ((array) ($settings['options'] ?? []) as $key => $value) {
                if ($this->optionShouldBeArray((string) $key) && ! is_array($value)) {
                    $warnings[] = "Scanner {$scanner} option {$key} should be an array";
                }
            }
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function validateGeneral(array &$errors): void
    {
        $defaultFormat = config('preflight.default_format');
        if (! is_string($defaultFormat) || ! in_array($defaultFormat, $this->formats, true)) {
            $errors[] = 'default_format must be one of console, json, md, sarif';
        }

        if (! is_string(config('preflight.sarif.information_uri'))) {
            $errors[] = 'sarif.information_uri must be a string';
        }

        $failUnder = config('preflight.fail_under');
        if ($failUnder !== null && ! is_numeric($failUnder)) {
            $errors[] = 'fail_under must be numeric or null';
        }

        if (! is_string(config('preflight.baseline_file'))) {
            $errors[] = 'baseline_file must be a string';
        }
    }

    private function optionShouldBeArray(string $key): bool
    {
        return str_ends_with($key, '_keywords')
            || str_ends_with($key, '_patterns')
            || str_ends_with($key, '_fields')
            || str_ends_with($key, '_columns')
            || str_ends_with($key, '_names')
            || str_ends_with($key, '_methods')
            || str_ends_with($key, '_directives')
            || str_ends_with($key, '_paths')
            || in_array($key, ['allow_authorize_true_for', 'risky_packages_in_require', 'suspicious_columns', 'public_route_allowlist', 'ignored_models'], true);
    }
}
