<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Core\AuditManager;
use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\BaselineManager;
use FahimTayebee\Preflight\Core\ScannerContext;
use FahimTayebee\Preflight\Reporters\ConsoleReporter;
use FahimTayebee\Preflight\Reporters\Contracts\ReporterInterface;
use FahimTayebee\Preflight\Reporters\JsonReporter;
use FahimTayebee\Preflight\Reporters\MarkdownReporter;
use FahimTayebee\Preflight\Reporters\SarifReporter;
use FahimTayebee\Preflight\Support\GitChangedFilesResolver;
use FahimTayebee\Preflight\Support\PathResolver;
use Illuminate\Console\Command;

final class AuditCommand extends Command
{
    /** @var array<int, string> */
    private array $formats = ['console', 'json', 'md', 'markdown', 'sarif'];

    /** @var array<int, string> */
    private array $severities = ['critical', 'high', 'medium', 'low', 'info'];

    /** @var array<int, string> */
    private array $presets = ['relaxed', 'default', 'strict'];

    protected $signature = 'preflight:audit
        {--format=console : Output format: console, json, md, or sarif}
        {--output= : Save report output to a file path}
        {--severity= : Only show issues at or above this severity level}
        {--fail-under= : Exit with failure if score is under this value}
        {--fail-on-severity= : Return exit code 1 if any issue exists at or above this severity}
        {--only=* : Run only specific scanners}
        {--skip=* : Skip specific scanners}
        {--baseline : Generate a baseline file from current issues}
        {--use-baseline : Ignore issues already stored in baseline}
        {--explain : Include expanded explanation, examples, docs, and false-positive guidance}
        {--preset=default : Runtime preset: relaxed, default, or strict}
        {--changed : Audit only changed files where scanners support it}
        {--base-ref= : Git base ref for changed-files mode}';

    protected $description = 'Run a Preflight security audit against the host Laravel project.';

    public function handle(
        AuditManager $manager,
        BaselineManager $baseline,
        ConsoleReporter $consoleReporter,
        JsonReporter $jsonReporter,
        MarkdownReporter $markdownReporter,
        SarifReporter $sarifReporter,
        GitChangedFilesResolver $changedFilesResolver,
        PathResolver $paths,
    ): int {
        $format = strtolower((string) ($this->option('format') ?: config('preflight.default_format', 'console')));
        $severityFilter = $this->normalizedSeverityOption('severity');
        $failOnSeverity = $this->normalizedSeverityOption('fail-on-severity');
        $failUnder = $this->option('fail-under') ?? config('preflight.fail_under');
        $preset = strtolower((string) ($this->option('preset') ?: 'default'));
        $changedMode = (bool) $this->option('changed');
        $baseRef = (string) ($this->option('base-ref') ?: config('preflight.changed_files.default_base_ref', 'origin/main'));
        $changedFilesMeta = [
            'enabled' => $changedMode,
            'base_ref' => $changedMode ? $baseRef : null,
            'count' => 0,
            'files' => [],
            'error' => null,
            'fallback_used' => false,
        ];

        if (! in_array($format, $this->formats, true)) {
            $this->error('Invalid format. Supported formats: console, json, md, sarif');

            return self::FAILURE;
        }

        if ($severityFilter === false || $failOnSeverity === false) {
            $this->error('Invalid severity. Supported severities: critical, high, medium, low, info');

            return self::FAILURE;
        }

        if (! in_array($preset, $this->presets, true)) {
            $this->error('Invalid preset. Supported presets: relaxed, default, strict');

            return self::FAILURE;
        }

        $context = null;
        if ($changedMode) {
            $changedFiles = $changedFilesResolver->changedFiles($baseRef);
            $error = $changedFilesResolver->lastError();
            $fallbackToFullScan = (bool) config('preflight.changed_files.fallback_to_full_scan', true);

            $changedFilesMeta = [
                'enabled' => true,
                'base_ref' => $baseRef,
                'count' => count($changedFiles),
                'files' => $changedFiles,
                'error' => $error,
                'fallback_used' => false,
            ];

            if ($error !== null) {
                if (! $fallbackToFullScan) {
                    $this->error('Changed mode error: ' . $error);

                    return self::FAILURE;
                }

                $changedFilesMeta['fallback_used'] = true;
                $context = new ScannerContext(false, [], $baseRef, $paths->base(), (array) $this->option('only'), (array) $this->option('skip'));
            } else {
                $context = new ScannerContext(true, $changedFiles, $baseRef, $paths->base(), (array) $this->option('only'), (array) $this->option('skip'));
            }
        }

        $report = $manager->run(
            (array) $this->option('only'),
            (array) $this->option('skip'),
            (bool) $this->option('use-baseline'),
            $severityFilter,
            $preset,
            $context,
            $changedFilesMeta
        );
        $report['explain'] = (bool) $this->option('explain');

        if ((bool) $this->option('baseline')) {
            try {
                $baseline->save($report['all_results']);
            } catch (\Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }

            $this->line('Baseline generated: ' . $this->relativePath($baseline->path()));
            $this->line('Issues stored: ' . count($report['all_results']));

            return self::SUCCESS;
        }

        $reporter = $this->reporter($format, $consoleReporter, $jsonReporter, $markdownReporter, $sarifReporter);
        $output = $reporter->render($report);
        $outputPath = $this->option('output');

        if ($outputPath !== null && $outputPath !== '') {
            if (! $this->writeReport((string) $outputPath, $output)) {
                return self::FAILURE;
            }

            $this->line('Report saved to: ' . $outputPath);
        } else {
            $this->line($output);
        }

        if ($failUnder !== null && $failUnder !== '' && (int) $report['score'] < (int) $failUnder) {
            return self::FAILURE;
        }

        if (is_string($failOnSeverity) && $this->hasIssueAtOrAboveSeverity($report['all_results'], $failOnSeverity)) {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function reporter(
        string $format,
        ConsoleReporter $consoleReporter,
        JsonReporter $jsonReporter,
        MarkdownReporter $markdownReporter,
        SarifReporter $sarifReporter,
    ): ReporterInterface {
        return match ($format) {
            'console' => $consoleReporter,
            'json' => $jsonReporter,
            'md', 'markdown' => $markdownReporter,
            'sarif' => $sarifReporter,
        };
    }

    private function normalizedSeverityOption(string $option): string|false|null
    {
        $value = $this->option($option);

        if ($value === null || $value === '') {
            return null;
        }

        $severity = strtolower((string) $value);

        return in_array($severity, $this->severities, true) ? $severity : false;
    }

    /**
     * @param array<int, AuditResult> $results
     */
    private function hasIssueAtOrAboveSeverity(array $results, string $severity): bool
    {
        $levels = (array) config('preflight.severity_levels', []);
        $minimum = (int) ($levels[$severity] ?? 0);

        foreach ($results as $result) {
            if ((int) ($levels[$result->severity->value] ?? 0) >= $minimum) {
                return true;
            }
        }

        return false;
    }

    private function writeReport(string $path, string $contents): bool
    {
        $resolvedPath = $this->resolveOutputPath($path);
        $directory = dirname($resolvedPath);

        try {
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                $this->error('Unable to create report directory: ' . $directory);

                return false;
            }

            if (@file_put_contents($resolvedPath, $contents) === false) {
                $this->error('Unable to write report file: ' . $path);

                return false;
            }
        } catch (\Throwable $exception) {
            $this->error('Unable to write report file: ' . $exception->getMessage());

            return false;
        }

        return true;
    }

    private function resolveOutputPath(string $path): string
    {
        if (preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1 || str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return $path;
        }

        return base_path($path);
    }

    private function relativePath(string $path): string
    {
        $base = rtrim(str_replace('\\', '/', base_path()), '/');
        $normalized = str_replace('\\', '/', $path);

        if (str_starts_with($normalized, $base)) {
            return ltrim(substr($normalized, strlen($base)), '/');
        }

        return $path;
    }
}
