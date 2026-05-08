<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Reporters;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Reporters\Contracts\ReporterInterface;

final class ConsoleReporter implements ReporterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $lines = [
            'Preflight Security Audit',
            '',
            'Generated At: ' . ($report['meta']['generated_at'] ?? 'unknown'),
            'Project: ' . ($report['meta']['project_path'] ?? 'unknown'),
            'PHP: ' . ($report['meta']['php_version'] ?? PHP_VERSION),
            '',
            'Score: ' . $report['score'] . '/100',
            'Total Issues: ' . $report['total_issues'],
        ];

        if (($report['filtered'] ?? false) === true) {
            $lines[] = 'Severity Filter: ' . $report['severity_filter'];
            $lines[] = 'Visible Issues: ' . ($report['visible_issues'] ?? count($report['results'])) . ' / ' . $report['total_issues'];
        }

        $lines[] = '';

        foreach ($this->orderedSeverities() as $severity) {
            $lines[] = ucfirst($severity->value) . ': ' . ($report['counts'][$severity->value] ?? 0);
        }

        if (($report['baseline_ignored_issues'] ?? 0) > 0) {
            $lines[] = '';
            $lines[] = 'Baseline ignored issues: ' . $report['baseline_ignored_issues'];
        }

        if (($report['scanner_timings'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = 'Scanner Timing:';

            foreach ($report['scanner_timings'] as $scanner => $seconds) {
                $lines[] = '- ' . $scanner . ': ' . number_format((float) $seconds, 3) . 's';
            }
        }

        foreach ($this->orderedSeverities() as $severity) {
            $items = array_values(array_filter(
                $report['results'],
                static fn (AuditResult $result): bool => $result->severity === $severity
            ));

            if ($items === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = '[' . strtoupper($severity->value) . ']';

            foreach ($items as $item) {
                $lines[] = '- ' . $item->title;
                $lines[] = '  Code: ' . $item->code;
                $lines[] = '  Confidence: ' . $item->confidence;
                $lines[] = '  ' . $item->message;

                if ($item->file !== null) {
                    $file = $item->line !== null ? $item->file . ':' . $item->line : $item->file;
                    $lines[] = '  File: ' . $file;
                }

                if ($item->recommendation !== null) {
                    $lines[] = '  Recommendation: ' . $item->recommendation;
                }
            }
        }

        $lines[] = '';
        $score = (int) $report['score'];
        if ($score >= 80) {
            $lines[] = 'Status: Passed';
            $lines[] = 'Next: Review medium and low findings before deployment.';
        } elseif ($score >= 60) {
            $lines[] = 'Status: Warning';
            $lines[] = 'Next: Fix critical/high findings or create a reviewed baseline.';
        } else {
            $lines[] = 'Status: Failed';
            $lines[] = 'Next: Fix critical/high findings before deployment.';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<int, Severity>
     */
    private function orderedSeverities(): array
    {
        return [
            Severity::Critical,
            Severity::High,
            Severity::Medium,
            Severity::Low,
            Severity::Info,
        ];
    }
}
