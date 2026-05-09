<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Commands;

use FahimTayebee\Preflight\Core\AuditManager;
use FahimTayebee\Preflight\Core\BaselineManager;
use Illuminate\Console\Command;

final class BaselineCommand extends Command
{
    protected $signature = 'preflight:baseline
        {action=show : Action to run: generate, show, prune, clear}
        {--format=console : Output format: console or json}
        {--force : Confirm destructive actions without prompt}
        {--fail-on-new : Return exit code 1 if new non-baselined issues exist}
        {--fail-on-resolved : Return exit code 1 if baseline contains resolved issues}';

    protected $description = 'Manage the Preflight baseline file.';

    /** @var array<int, string> */
    private array $actions = ['generate', 'show', 'prune', 'clear'];

    /** @var array<int, string> */
    private array $formats = ['console', 'json'];

    public function handle(BaselineManager $baseline, AuditManager $audit): int
    {
        $action = strtolower((string) $this->argument('action'));
        $format = strtolower((string) $this->option('format'));

        if (! in_array($action, $this->actions, true)) {
            $this->error('Invalid action. Supported actions: generate, show, prune, clear');

            return self::FAILURE;
        }

        if (! in_array($format, $this->formats, true)) {
            $this->error('Invalid format. Supported formats: console, json');

            return self::FAILURE;
        }

        return match ($action) {
            'generate' => $this->generate($baseline, $audit, $format),
            'show' => $this->show($baseline, $audit, $format),
            'prune' => $this->prune($baseline, $audit, $format),
            'clear' => $this->clear($baseline, $format),
        };
    }

    private function show(BaselineManager $baseline, AuditManager $audit, string $format): int
    {
        $entries = $baseline->entries();
        $payload = [
            'exists' => $baseline->exists(),
            'file' => $this->relativePath($baseline->path()),
            'entries' => $entries,
            'count' => count($entries),
        ];

        $newCount = null;
        if ((bool) $this->option('fail-on-new')) {
            $diff = $baseline->diff($audit->run()['all_results']);
            $newCount = (int) $diff['counts']['new'];
            $payload['new_count'] = $newCount;
        }

        if ($format === 'json') {
            $this->line($this->json($payload));
        } elseif (! $baseline->exists()) {
            $this->line('No baseline file found.');
            $this->line('Run: php artisan preflight:baseline generate');
            if ($newCount !== null) {
                $this->line('New issues found: ' . $newCount);
            }
        } else {
            $this->line('Baseline file: ' . $payload['file']);
            $this->line('Entries: ' . $payload['count']);

            foreach ($this->groupByCode($entries) as $code => $items) {
                $this->line('');
                $this->line($code . ' (' . count($items) . ')');

                foreach ($items as $entry) {
                    $this->line('- Fingerprint: ' . $entry['fingerprint']);
                    $this->line('  Scanner: ' . (string) ($entry['scanner'] ?? 'unknown'));
                    $this->line('  File: ' . (string) ($entry['file'] ?? ''));
                    $this->line('  Title: ' . (string) ($entry['title'] ?? ''));
                    if (($entry['created_at'] ?? null) !== null) {
                        $this->line('  Created At: ' . (string) $entry['created_at']);
                    }
                }
            }

            if ($newCount !== null) {
                $this->line('');
                $this->line('New issues found: ' . $newCount);
            }
        }

        return $newCount !== null && $newCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function generate(BaselineManager $baseline, AuditManager $audit, string $format): int
    {
        $results = $audit->run()['all_results'];
        $newCount = null;

        if ((bool) $this->option('fail-on-new')) {
            $diff = $baseline->diff($results);
            $newCount = (int) $diff['counts']['new'];
        }

        $baseline->save($results);
        $entries = $baseline->entries();
        $payload = [
            'generated' => true,
            'file' => $this->relativePath($baseline->path()),
            'count' => count($entries),
            'entries' => $entries,
        ];

        if ($newCount !== null) {
            $payload['new_count'] = $newCount;
        }

        if ($format === 'json') {
            $this->line($this->json($payload));
        } else {
            $this->line('Baseline generated: ' . $payload['file']);
            $this->line('Issues stored: ' . $payload['count']);
            if ($newCount !== null) {
                $this->line('New issues found: ' . $newCount);
            }
        }

        return $newCount !== null && $newCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function prune(BaselineManager $baseline, AuditManager $audit, string $format): int
    {
        $previous = $baseline->entries();
        $diff = $baseline->diff($audit->run()['all_results']);
        $resolved = array_flip(array_map(
            static fn (array $entry): string => (string) ($entry['fingerprint'] ?? ''),
            $diff['resolved']
        ));
        $remaining = array_values(array_filter(
            $previous,
            static fn (array $entry): bool => ! isset($resolved[(string) ($entry['fingerprint'] ?? '')])
        ));

        $baseline->saveEntries($remaining);

        $payload = [
            'pruned' => true,
            'previous_count' => count($previous),
            'removed_count' => count($previous) - count($remaining),
            'remaining_count' => count($remaining),
        ];

        if ($format === 'json') {
            $this->line($this->json($payload));
        } else {
            $this->line('Baseline pruned.');
            $this->line('Removed resolved entries: ' . $payload['removed_count']);
            $this->line('Remaining entries: ' . $payload['remaining_count']);
        }

        if ((bool) $this->option('fail-on-resolved') && $payload['removed_count'] > 0) {
            if ($format === 'console') {
                $this->line('Resolved baseline entries found: ' . $payload['removed_count']);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function clear(BaselineManager $baseline, string $format): int
    {
        $path = $baseline->path();
        $payload = [
            'cleared' => true,
            'file' => $this->relativePath($path),
        ];

        if (! $baseline->exists()) {
            $payload['cleared'] = false;
            if ($format === 'json') {
                $this->line($this->json($payload));
            } else {
                $this->line('No baseline file found.');
            }

            return self::SUCCESS;
        }

        if (! (bool) $this->option('force') && ! $this->confirm('Delete the Preflight baseline file?')) {
            $payload['cleared'] = false;
            if ($format === 'json') {
                $this->line($this->json($payload));
            } else {
                $this->line('Baseline clear cancelled.');
            }

            return self::SUCCESS;
        }

        @unlink($path);

        if ($format === 'json') {
            $this->line($this->json($payload));
        } else {
            $this->line('Baseline cleared: ' . $payload['file']);
        }

        return self::SUCCESS;
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function groupByCode(array $entries): array
    {
        $grouped = [];

        foreach ($entries as $entry) {
            $code = (string) ($entry['code'] ?? 'unknown');
            $grouped[$code][] = $entry;
        }

        ksort($grouped);

        return $grouped;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function json(array $payload): string
    {
        return json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
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
