<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Reporters;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Reporters\Contracts\ReporterInterface;

final class JsonReporter implements ReporterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string
    {
        $payload = [
            'meta' => $report['meta'] ?? [],
            'score' => $report['score'],
            'total_issues' => $report['total_issues'],
            'visible_issues' => $report['visible_issues'] ?? $report['total_issues'],
            'filtered' => $report['filtered'] ?? false,
            'severity_filter' => $report['severity_filter'] ?? null,
            'counts' => $report['counts'],
            'scanner_timings' => $report['scanner_timings'] ?? [],
            'baseline_ignored_issues' => $report['baseline_ignored_issues'] ?? 0,
            'results' => array_map(
                static fn (AuditResult $result): array => $result->toArray(),
                $report['results']
            ),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
