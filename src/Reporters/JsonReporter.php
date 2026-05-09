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
            'confidence_counts' => $report['confidence_counts'] ?? ['high' => 0, 'medium' => 0, 'low' => 0],
            'scanner_timings' => $report['scanner_timings'] ?? [],
            'baseline_ignored_issues' => $report['baseline_ignored_issues'] ?? 0,
            'baseline' => $report['baseline'] ?? [
                'used' => false,
                'ignored' => 0,
                'new' => $report['total_issues'],
                'resolved' => 0,
                'file' => null,
            ],
            'changed_files' => $report['changed_files'] ?? [
                'enabled' => false,
                'base_ref' => null,
                'count' => 0,
                'files' => [],
                'error' => null,
                'fallback_used' => false,
            ],
            'results' => array_map(
                static fn (AuditResult $result): array => self::result($result, (bool) ($report['explain'] ?? false)),
                $report['results']
            ),
        ];

        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private static function result(AuditResult $result, bool $explain): array
    {
        $item = $result->toArray();

        if ($explain) {
            $item['explanation'] = [
                'impact' => $result->metadata['impact'] ?? null,
                'fix_example_bad' => $result->metadata['fix_example_bad'] ?? null,
                'fix_example_good' => $result->metadata['fix_example_good'] ?? null,
                'docs_url' => $result->metadata['docs_url'] ?? null,
                'false_positive_guidance' => $result->metadata['false_positive_guidance'] ?? null,
            ];
        }

        return $item;
    }
}
