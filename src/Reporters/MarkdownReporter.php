<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Reporters;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Reporters\Contracts\ReporterInterface;

final class MarkdownReporter implements ReporterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $lines = [
            '# Preflight Security Audit Report',
            '',
            'Generated At: ' . ($report['meta']['generated_at'] ?? 'unknown') . '  ',
            'Project Path: ' . ($report['meta']['project_path'] ?? 'unknown') . '  ',
            'PHP Version: ' . ($report['meta']['php_version'] ?? PHP_VERSION) . '  ',
            'Score: ' . $report['score'] . '/100  ',
            'Total Issues: ' . $report['total_issues'],
        ];

        if (($report['filtered'] ?? false) === true) {
            $lines[] = 'Severity Filter: ' . $report['severity_filter'] . '  ';
            $lines[] = 'Visible Issues: ' . ($report['visible_issues'] ?? count($report['results'])) . ' / ' . $report['total_issues'];
        }

        $lines = array_merge($lines, [
            '',
            '## Summary',
            '',
            '| Severity | Count |',
            '|---|---:|',
        ]);

        foreach ($this->orderedSeverities() as $severity) {
            $lines[] = '| ' . ucfirst($severity->value) . ' | ' . ($report['counts'][$severity->value] ?? 0) . ' |';
        }

        if (($report['baseline_ignored_issues'] ?? 0) > 0) {
            $lines[] = '';
            $lines[] = '**Baseline ignored issues:** ' . $report['baseline_ignored_issues'];
        }

        if (($report['scanner_timings'] ?? []) !== []) {
            $lines[] = '';
            $lines[] = '## Scanner Timing';
            $lines[] = '';

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
            $lines[] = '## ' . ucfirst($severity->value) . ' Issues';
            $lines[] = '';

            foreach ($items as $index => $item) {
                $lines[] = '### ' . ($index + 1) . '. ' . $this->escape($item->title);
                $lines[] = '**Code:** ' . $item->code . '  ';
                $lines[] = '**Scanner:** ' . $item->scanner . '  ';
                $lines[] = '**Confidence:** ' . $item->confidence . '  ';

                if ($item->file !== null) {
                    $file = $item->line !== null ? $item->file . ':' . $item->line : $item->file;
                    $lines[] = '**File:** ' . $file . '  ';
                }

                if ($item->recommendation !== null) {
                    $lines[] = '**Recommendation:** ' . $this->escape($item->recommendation) . '  ';
                }

                $lines[] = '';
                $lines[] = $this->escape($item->message);
                $lines[] = '';
            }
        }

        return rtrim(implode(PHP_EOL, $lines)) . PHP_EOL;
    }

    private function escape(string $value): string
    {
        return str_replace('|', '\|', $value);
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
