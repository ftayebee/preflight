<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\RuleRegistry;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Reporters\ConsoleReporter;
use FahimTayebee\Preflight\Reporters\JsonReporter;
use FahimTayebee\Preflight\Reporters\MarkdownReporter;
use FahimTayebee\Preflight\Reporters\SarifReporter;
use Illuminate\Console\Command;

final class SelfTestCommand extends Command
{
    protected $signature = 'preflight:self-test
        {--format=console : Output format: console or json}';

    protected $description = 'Run Preflight internal health checks.';

    public function handle(
        RuleRegistry $registry,
        ConsoleReporter $console,
        JsonReporter $json,
        MarkdownReporter $markdown,
        SarifReporter $sarif,
    ): int {
        $errors = [];
        $passed = [];

        $this->checkRules($registry, $errors, $passed);
        $this->checkScanners($errors, $passed);
        $this->checkReporters($console, $json, $markdown, $sarif, $errors, $passed);

        $format = strtolower((string) $this->option('format'));
        if ($format === 'json') {
            $this->line(json_encode([
                'passed' => $errors === [],
                'errors' => $errors,
                'checks' => $passed,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $errors === [] ? self::SUCCESS : self::FAILURE;
        }

        if ($format !== 'console') {
            $this->error('Invalid format. Supported formats: console, json');
            return self::FAILURE;
        }

        $this->line('Preflight Self Test');
        $this->line('');
        $this->line('Passed:');
        foreach ($passed as $item) {
            $this->line('- ' . $item);
        }
        if ($errors !== []) {
            $this->line('');
            $this->line('Errors:');
            foreach ($errors as $error) {
                $this->line('- ' . $error);
            }
        }

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $passed
     */
    private function checkRules(RuleRegistry $registry, array &$errors, array &$passed): void
    {
        $rules = $registry->all();
        if ($rules === []) {
            $errors[] = 'Rule registry is empty';
            return;
        }

        foreach ($rules as $code => $rule) {
            foreach (['scanner', 'title', 'default_severity', 'description', 'recommendation'] as $field) {
                if (($rule[$field] ?? '') === '') {
                    $errors[] = "Rule {$code} is missing {$field}";
                }
            }
        }

        $passed[] = 'Rule registry valid';
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $passed
     */
    private function checkScanners(array &$errors, array &$passed): void
    {
        $scanners = ['env', 'routes', 'controllers', 'models', 'migrations', 'requests', 'composer'];
        foreach ($scanners as $scanner) {
            if (! is_array(config("preflight.scanners.{$scanner}"))) {
                $errors[] = "Scanner {$scanner} is not configured";
            }
        }

        $passed[] = 'Scanners resolved';
    }

    /**
     * @param array<int, string> $errors
     * @param array<int, string> $passed
     */
    private function checkReporters(ConsoleReporter $console, JsonReporter $json, MarkdownReporter $markdown, SarifReporter $sarif, array &$errors, array &$passed): void
    {
        $report = [
            'meta' => ['generated_at' => date(DATE_ATOM), 'project_path' => base_path(), 'php_version' => PHP_VERSION],
            'results' => [new AuditResult('ENV_DEBUG_TRUE', 'env', Severity::Critical, 'Debug mode is enabled', 'Debug mode is enabled.')],
            'score' => 75,
            'counts' => ['critical' => 1, 'high' => 0, 'medium' => 0, 'low' => 0, 'info' => 0],
            'total_issues' => 1,
            'visible_issues' => 1,
            'scanner_timings' => ['env' => 0.001],
        ];

        try {
            $console->render($report);
            $json->render($report);
            $markdown->render($report);
            json_decode($sarif->render($report), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            $errors[] = 'Reporter rendering failed: ' . $exception->getMessage();
            return;
        }

        $passed[] = 'Reporters rendered';
        $passed[] = 'SARIF JSON valid';
    }
}
