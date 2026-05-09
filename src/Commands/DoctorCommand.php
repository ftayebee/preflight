<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Core\RuleRegistry;
use FahimTayebee\Preflight\Support\PackageInfo;
use FahimTayebee\Preflight\Support\PathResolver;
use Illuminate\Console\Command;

final class DoctorCommand extends Command
{
    protected $signature = 'preflight:doctor
        {--format=console : Output format: console or json}
        {--output= : Optional output file path to check writability}';

    protected $description = 'Check whether Preflight is ready to run in this Laravel project.';

    /** @var array<int, string> */
    private array $severities = ['critical', 'high', 'medium', 'low', 'info'];

    public function handle(PathResolver $paths, RuleRegistry $registry, PackageInfo $package): int
    {
        $errors = [];
        $warnings = [];
        $checks = [];

        $this->pass($checks, 'Config loaded');

        version_compare(PHP_VERSION, '8.2.0', '>=') ? $this->pass($checks, 'PHP version supported') : $errors[] = 'PHP version is not supported';
        $package->laravelVersion() !== null ? $this->pass($checks, 'Laravel version detected') : $warnings[] = 'Laravel version could not be detected';

        is_file($paths->composerJson()) ? $this->pass($checks, 'Composer file found') : $errors[] = 'composer.json not found';
        is_dir($paths->routes()) ? $this->pass($checks, 'Routes directory found') : $warnings[] = 'routes directory not found';
        is_dir($paths->app()) ? $this->pass($checks, 'App directory found') : $warnings[] = 'app directory not found';
        is_dir($paths->database('migrations')) ? $this->pass($checks, 'Migrations directory found') : $warnings[] = 'database/migrations directory not found';

        $this->validateScanners($errors, $checks);
        $this->validateSeverities($errors, $registry, $checks);
        $this->validateBaseline($warnings);
        $ui = $this->uiStatus();
        $this->validateOutputPath($errors);

        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line(json_encode([
                'passed' => $errors === [],
                'errors' => $errors,
                'warnings' => $warnings,
                'checks' => $checks,
                'ui' => $ui,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($format !== 'console') {
            $this->error('Invalid format. Supported formats: console, json');
            return self::FAILURE;
        }

        $this->line('Preflight Doctor');
        $this->line('');
        $this->line('Passed:');
        foreach ($checks as $check) {
            $this->line('- ' . $check);
        }
        $this->line('');
        $this->line('Warnings:');
        foreach ($warnings === [] ? ['None'] : $warnings as $warning) {
            $this->line('- ' . $warning);
        }
        $this->line('');
        $this->line('Errors:');
        foreach ($errors === [] ? ['None'] : $errors as $error) {
            $this->line('- ' . $error);
        }
        $this->line('');
        $this->line('UI:');
        $this->line('- UI enabled: ' . ($ui['enabled'] ? 'yes' : 'no'));
        $this->line('- UI path: ' . $ui['path']);
        $this->line('- UI allowed environments: ' . implode(', ', $ui['allowed_environments']));
        $this->line('- Current environment allowed: ' . ($ui['current_environment_allowed'] ? 'yes' : 'no'));
        $this->line('- Reports directory exists/readable: ' . ($ui['reports_directory_readable'] ? 'yes' : 'no'));

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<int, string> $checks
     */
    private function pass(array &$checks, string $message): void
    {
        $checks[] = $message;
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $checks
     */
    private function validateScanners(array &$errors, array &$checks): void
    {
        $valid = ['env', 'routes', 'controllers', 'models', 'migrations', 'requests', 'composer', 'policies', 'blade', 'auth'];

        foreach ((array) config('preflight.scanners', []) as $scanner => $settings) {
            if (! in_array((string) $scanner, $valid, true)) {
                $errors[] = "Unknown scanner configured: {$scanner}";
            }

            if (is_array($settings) && array_key_exists('enabled', $settings) && ! is_bool($settings['enabled'])) {
                $errors[] = "Scanner {$scanner} enabled value must be boolean";
            }
        }

        if (! array_filter($errors, fn (string $error): bool => str_contains($error, 'scanner') || str_contains($error, 'Scanner'))) {
            $checks[] = 'Configured scanners valid';
        }
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $checks
     */
    private function validateSeverities(array &$errors, RuleRegistry $registry, array &$checks): void
    {
        foreach ((array) config('preflight.rules', []) as $code => $rule) {
            if (is_array($rule) && isset($rule['severity']) && (! is_string($rule['severity']) || ! in_array($rule['severity'], $this->severities, true))) {
                $errors[] = 'Invalid severity value "' . (string) $rule['severity'] . "\" for {$code}";
            }
        }

        $registry->all() !== [] ? $checks[] = 'Rule registry valid' : $errors[] = 'Rule registry is empty';
    }

    /**
     * @param array<int, string> $warnings
     */
    private function validateBaseline(array &$warnings): void
    {
        $path = config('preflight.baseline_file');
        if (! is_string($path) || $path === '') {
            $warnings[] = 'Baseline file path is not configured';
            return;
        }

        if (! is_file($path)) {
            $warnings[] = 'No baseline file found';
            return;
        }

        if (! is_readable($path) || ! is_writable($path)) {
            $warnings[] = 'Baseline file is not readable and writable';
        }
    }

    /**
     * @param array<int, string> $errors
     */
    private function validateOutputPath(array &$errors): void
    {
        $output = $this->option('output');
        if (! is_string($output) || $output === '') {
            return;
        }

        $directory = dirname($output);
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            $errors[] = 'Output directory cannot be written: ' . $directory;
        }
    }

    /**
     * @return array{
     *     enabled: bool,
     *     path: string,
     *     allowed_environments: array<int, string>,
     *     current_environment_allowed: bool,
     *     reports_directory: string|null,
     *     reports_directory_readable: bool
     * }
     */
    private function uiStatus(): array
    {
        $allowed = array_values(array_map('strval', (array) config('preflight.ui.allowed_environments', [])));
        $directory = config('preflight.ui.reports_directory', config('preflight.reports.directory'));
        $directory = is_string($directory) ? $directory : null;

        return [
            'enabled' => config('preflight.ui.enabled') === true,
            'path' => trim((string) config('preflight.ui.path', 'preflight'), '/'),
            'allowed_environments' => $allowed,
            'current_environment_allowed' => $allowed !== [] && app()->environment($allowed),
            'reports_directory' => $directory,
            'reports_directory_readable' => is_string($directory) && is_dir($directory) && is_readable($directory),
        ];
    }
}
