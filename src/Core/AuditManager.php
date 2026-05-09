<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Scanners\AuthScanner;
use FahimTayebee\Preflight\Scanners\BladeScanner;
use FahimTayebee\Preflight\Scanners\ControllerScanner;
use FahimTayebee\Preflight\Scanners\ComposerScanner;
use FahimTayebee\Preflight\Scanners\EnvScanner;
use FahimTayebee\Preflight\Scanners\MigrationScanner;
use FahimTayebee\Preflight\Scanners\ModelScanner;
use FahimTayebee\Preflight\Scanners\PolicyScanner;
use FahimTayebee\Preflight\Scanners\RequestScanner;
use FahimTayebee\Preflight\Scanners\RouteScanner;
use FahimTayebee\Preflight\Support\PackageInfo;
use FahimTayebee\Preflight\Support\PathResolver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\Container;

final class AuditManager
{
    /** @var array<string, class-string<ScannerInterface>> */
    private array $scannerMap = [
        'env' => EnvScanner::class,
        'routes' => RouteScanner::class,
        'controllers' => ControllerScanner::class,
        'models' => ModelScanner::class,
        'migrations' => MigrationScanner::class,
        'requests' => RequestScanner::class,
        'composer' => ComposerScanner::class,
        'policies' => PolicyScanner::class,
        'blade' => BladeScanner::class,
        'auth' => AuthScanner::class,
    ];

    public function __construct(
        private readonly ConfigRepository $config,
        private readonly Container $container,
        private readonly BaselineManager $baseline,
        private readonly RuleManager $rules,
        private readonly RuleRegistry $registry,
        private readonly FindingFingerprint $fingerprints,
        private readonly PackageInfo $package,
        private readonly PathResolver $paths,
    ) {
    }

    /**
     * @param array<int, string> $only
     * @param array<int, string> $skip
     * @return array<string, mixed>
     */
    public function run(array $only = [], array $skip = [], bool $useBaseline = false, ?string $severityFilter = null, string $preset = 'default'): array
    {
        $results = [];
        $timings = [];

        foreach ($this->scanners($only, $skip) as $scanner) {
            $startedAt = microtime(true);

            foreach ($scanner->scan() as $result) {
                if ($result instanceof AuditResult) {
                    $results[] = $result;
                }
            }

            $timings[$scanner->name()] = round(microtime(true) - $startedAt, 3);
        }

        $results = $this->rules->apply($results);
        $results = $this->enrich($results);
        $results = $this->deduplicate($results);
        $baselineIgnored = 0;
        $baselineResolved = 0;
        $baselineFile = $this->baseline->path();

        if ($useBaseline) {
            $baselineDiff = $this->baseline->diff($results);
            $baselineResult = $this->baseline->filter($results);
            $results = $baselineResult['results'];
            $baselineIgnored = $baselineResult['ignored'];
            $baselineResolved = (int) ($baselineDiff['counts']['resolved'] ?? 0);
        }

        $visibleResults = $this->applyPreset($results, $preset);
        $visibleResults = $severityFilter === null
            ? $visibleResults
            : $this->filterBySeverity($visibleResults, $severityFilter);

        return [
            'meta' => [
                'package' => $this->package->name(),
                'version' => $this->package->version(),
                'generated_at' => date(DATE_ATOM),
                'project_path' => $this->paths->base(),
                'php_version' => $this->package->phpVersion(),
                'laravel_version' => $this->package->laravelVersion(),
                'scanner_count' => count($timings),
                'enabled_scanners' => array_keys($timings),
                'skipped_scanners' => array_values(array_diff(array_keys($this->scannerMap), array_keys($timings))),
                'preset' => $preset,
            ],
            'results' => $visibleResults,
            'all_results' => $results,
            'score' => $this->score($results),
            'counts' => $this->counts($results),
            'confidence_counts' => $this->confidenceCounts($results),
            'total_issues' => count($results),
            'visible_issues' => count($visibleResults),
            'filtered' => $severityFilter !== null,
            'severity_filter' => $severityFilter,
            'scanner_timings' => $timings,
            'baseline_ignored_issues' => $baselineIgnored,
            'baseline' => [
                'used' => $useBaseline,
                'ignored' => $baselineIgnored,
                'new' => count($results),
                'resolved' => $baselineResolved,
                'file' => $baselineFile,
            ],
        ];
    }

    /**
     * @param array<int, string> $only
     * @param array<int, string> $skip
     * @return array<int, ScannerInterface>
     */
    private function scanners(array $only, array $skip): array
    {
        $enabled = $this->configuredScanners();
        $only = $this->normalizeList($only);
        $skip = $this->normalizeList($skip);

        if ($only !== []) {
            $enabled = array_values(array_intersect($enabled, $only));
        }

        if ($skip !== []) {
            $enabled = array_values(array_diff($enabled, $skip));
        }

        $scanners = [];

        foreach ($enabled as $name) {
            if (! isset($this->scannerMap[$name])) {
                continue;
            }

            $scanners[] = $this->container->make($this->scannerMap[$name]);
        }

        return $scanners;
    }

    /**
     * @param array<int, AuditResult> $results
     */
    private function score(array $results): int
    {
        $score = 100;
        $weights = (array) $this->config->get('preflight.severity_score', []);

        foreach ($results as $result) {
            $score -= (int) ($weights[$result->severity->value] ?? 0);
        }

        return max(0, $score);
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<string, int>
     */
    private function counts(array $results): array
    {
        $counts = [
            Severity::Critical->value => 0,
            Severity::High->value => 0,
            Severity::Medium->value => 0,
            Severity::Low->value => 0,
            Severity::Info->value => 0,
        ];

        foreach ($results as $result) {
            $counts[$result->severity->value]++;
        }

        return $counts;
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<string, int>
     */
    private function confidenceCounts(array $results): array
    {
        $counts = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($results as $result) {
            $counts[$result->confidence] = ($counts[$result->confidence] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, AuditResult>
     */
    private function filterBySeverity(array $results, string $severity): array
    {
        $levels = (array) $this->config->get('preflight.severity_levels', []);
        $minimum = (int) ($levels[$severity] ?? 0);

        if ($minimum === 0) {
            return $results;
        }

        return array_values(array_filter(
            $results,
            static fn (AuditResult $result): bool => (int) ($levels[$result->severity->value] ?? 0) >= $minimum
        ));
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, AuditResult>
     */
    private function deduplicate(array $results): array
    {
        $deduped = [];

        foreach ($results as $result) {
            $fingerprint = $this->fingerprints->primary($result);

            if (! isset($deduped[$fingerprint])) {
                $deduped[$fingerprint] = $result;
                continue;
            }

            $deduped[$fingerprint] = $this->preferStronger($deduped[$fingerprint], $result);
        }

        return array_values($deduped);
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, AuditResult>
     */
    private function enrich(array $results): array
    {
        return array_map(function (AuditResult $result): AuditResult {
            $rule = $this->registry->get($result->code);

            if ($rule === []) {
                return $result;
            }

            return $result->withMetadata([
                'impact' => $rule['impact'] ?? null,
                'fix_example_bad' => $rule['fix_example_bad'] ?? null,
                'fix_example_good' => $rule['fix_example_good'] ?? null,
                'docs_url' => $rule['docs_url'] ?? null,
                'false_positive_guidance' => $rule['false_positive_guidance'] ?? null,
            ]);
        }, $results);
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, AuditResult>
     */
    private function applyPreset(array $results, string $preset): array
    {
        if ($preset !== 'relaxed') {
            return $results;
        }

        return array_values(array_filter(
            $results,
            static fn (AuditResult $result): bool => $result->confidence !== 'low'
        ));
    }

    private function preferStronger(AuditResult $first, AuditResult $second): AuditResult
    {
        $levels = (array) $this->config->get('preflight.severity_levels', []);
        $firstSeverity = (int) ($levels[$first->severity->value] ?? 0);
        $secondSeverity = (int) ($levels[$second->severity->value] ?? 0);

        if ($secondSeverity > $firstSeverity) {
            return $second;
        }

        if ($secondSeverity === $firstSeverity && $this->confidenceLevel($second->confidence) > $this->confidenceLevel($first->confidence)) {
            return $second;
        }

        return $first;
    }

    private function confidenceLevel(string $confidence): int
    {
        return ['low' => 1, 'medium' => 2, 'high' => 3][$confidence] ?? 0;
    }

    /**
     * @param array<int, mixed> $items
     * @return array<int, string>
     */
    private function normalizeList(array $items): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $item): string => strtolower(trim((string) $item)),
            $items
        ))));
    }

    /**
     * @return array<int, string>
     */
    private function configuredScanners(): array
    {
        $enabled = $this->normalizeList((array) $this->config->get('preflight.enabled_scanners', array_keys($this->scannerMap)));
        $scanners = (array) $this->config->get('preflight.scanners', []);

        if ($scanners === []) {
            return $enabled;
        }

        return array_values(array_filter(
            $enabled,
            static fn (string $name): bool => (bool) ($scanners[$name]['enabled'] ?? true)
        ));
    }
}
