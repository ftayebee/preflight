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
            'Preset: ' . ($report['meta']['preset'] ?? 'default'),
            '',
            'Score: ' . $report['score'] . '/100',
            'Total Issues: ' . $report['total_issues'],
        ];

        if (($report['filtered'] ?? false) === true) {
            $lines[] = 'Severity Filter: ' . $report['severity_filter'];
            $lines[] = 'Visible Issues: ' . ($report['visible_issues'] ?? count($report['results'])) . ' / ' . $report['total_issues'];
        } elseif (($report['visible_issues'] ?? $report['total_issues']) !== $report['total_issues']) {
            $lines[] = 'Visible Issues: ' . ($report['visible_issues'] ?? count($report['results'])) . ' / ' . $report['total_issues'];
        }

        $lines[] = '';

        foreach ($this->orderedSeverities() as $severity) {
            $lines[] = ucfirst($severity->value) . ': ' . ($report['counts'][$severity->value] ?? 0);
        }

        if (($report['changed_files']['enabled'] ?? false) === true) {
            $lines[] = '';
            $lines[] = 'Changed Files Mode:';
            $lines[] = '- Enabled: yes';
            $lines[] = '- Base Ref: ' . ($report['changed_files']['base_ref'] ?? 'unknown');
            $lines[] = '- Changed Files: ' . ($report['changed_files']['count'] ?? 0);
            $lines[] = '- Fallback Used: ' . (($report['changed_files']['fallback_used'] ?? false) ? 'yes' : 'no');

            if (($report['changed_files']['fallback_used'] ?? false) === true) {
                $lines[] = 'Changed mode warning: Could not resolve changed files. Falling back to full scan.';
            }

            if (($report['explain'] ?? false) === true && ($report['changed_files']['files'] ?? []) !== []) {
                $lines[] = '- Files:';
                foreach ($report['changed_files']['files'] as $file) {
                    $lines[] = '  - ' . $file;
                }
            }

            if (($report['changed_files']['count'] ?? 0) === 0 && ($report['meta']['scanner_count'] ?? 0) === 0) {
                $lines[] = 'No changed files found.';
            }
        }

        $lines[] = '';
        $lines[] = 'Confidence:';
        $lines[] = '- High: ' . ($report['confidence_counts']['high'] ?? 0);
        $lines[] = '- Medium: ' . ($report['confidence_counts']['medium'] ?? 0);
        $lines[] = '- Low: ' . ($report['confidence_counts']['low'] ?? 0);

        if (($report['baseline']['used'] ?? false) === true) {
            $lines[] = '';
            $lines[] = 'Baseline ignored issues: ' . ($report['baseline']['ignored'] ?? $report['baseline_ignored_issues'] ?? 0);
            $lines[] = 'New issues after baseline: ' . ($report['baseline']['new'] ?? count($report['results']));
            $resolved = (int) ($report['baseline']['resolved'] ?? 0);
            $lines[] = 'Resolved baseline entries: ' . $resolved;

            if ($resolved > 0) {
                $lines[] = 'Run php artisan preflight:baseline prune to remove resolved baseline entries.';
            }
        } elseif (($report['baseline_ignored_issues'] ?? 0) > 0) {
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

                if (($report['explain'] ?? false) === true) {
                    $this->appendExplanation($lines, $item);
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
     * @param array<int, string> $lines
     */
    private function appendExplanation(array &$lines, AuditResult $item): void
    {
        $impact = $item->metadata['impact'] ?? null;
        $bad = $item->metadata['fix_example_bad'] ?? null;
        $good = $item->metadata['fix_example_good'] ?? null;
        $docsUrl = $item->metadata['docs_url'] ?? null;
        $falsePositive = $item->metadata['false_positive_guidance'] ?? null;

        if (is_string($impact) && $impact !== '') {
            $lines[] = '  Why this matters: ' . $impact;
        }

        if (is_string($item->recommendation) && $item->recommendation !== '') {
            $lines[] = '  How to fix: ' . $item->recommendation;
        }

        if (is_string($bad) && $bad !== '') {
            $lines[] = '  Bad example:';
            foreach (explode("\n", $bad) as $line) {
                $lines[] = '    ' . $line;
            }
        }

        if (is_string($good) && $good !== '') {
            $lines[] = '  Better example:';
            foreach (explode("\n", $good) as $line) {
                $lines[] = '    ' . $line;
            }
        }

        if (is_string($falsePositive) && $falsePositive !== '') {
            $lines[] = '  False-positive guidance: ' . $falsePositive;
        }

        if (is_string($docsUrl) && $docsUrl !== '') {
            $lines[] = '  Docs: ' . $docsUrl;
        }
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
