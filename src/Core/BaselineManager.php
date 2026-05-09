<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class BaselineManager
{
    public function __construct(
        private readonly ConfigRepository $config,
        private readonly FindingFingerprint $fingerprints,
    ) {
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<string, mixed>
     */
    public function generate(array $results): array
    {
        $entries = $this->generateFromResults($results);

        return [
            'version' => (int) $this->config->get('preflight.baseline.format_version', 1),
            'generated_at' => date(DATE_ATOM),
            'count' => count($entries),
            'entries' => $entries,
            'fingerprints' => array_column($entries, 'fingerprint'),
        ];
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    /**
     * @param array<int, AuditResult> $results
     */
    public function save(array $results): void
    {
        $this->writePayload($this->generate($results));
    }

    /**
     * @param array<int, array<string, mixed>> $entries
     */
    public function saveEntries(array $entries): void
    {
        $this->writePayload([
            'version' => (int) $this->config->get('preflight.baseline.format_version', 1),
            'generated_at' => date(DATE_ATOM),
            'count' => count($entries),
            'entries' => array_values($entries),
            'fingerprints' => array_values(array_filter(array_map(
                static fn (array $entry): string => (string) ($entry['fingerprint'] ?? ''),
                $entries
            ))),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writePayload(array $payload): void
    {
        $path = $this->path();
        $directory = dirname($path);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new \RuntimeException('Unable to create baseline directory: ' . $directory);
        }

        $written = @file_put_contents(
            $path,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
        );

        if ($written === false) {
            throw new \RuntimeException('Unable to write baseline file: ' . $path);
        }
    }

    /**
     * @return array<int, string>
     */
    public function load(): array
    {
        return array_values(array_filter(array_map(
            static fn (array $entry): string => (string) ($entry['fingerprint'] ?? ''),
            $this->entries()
        )));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entries(): array
    {
        $path = $this->path();

        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        try {
            $payload = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        $entries = $payload['entries'] ?? null;

        if (is_array($entries)) {
            return array_values(array_filter(array_map(
                fn (mixed $entry): ?array => $this->normalizeEntry($entry),
                $entries
            )));
        }

        $fingerprints = $payload['fingerprints'] ?? [];

        if (! is_array($fingerprints)) {
            return [];
        }

        return array_values(array_filter(
            array_map(static fn (mixed $fingerprint): array => [
                'fingerprint' => (string) $fingerprint,
                'code' => null,
                'scanner' => null,
                'severity' => null,
                'confidence' => null,
                'file' => null,
                'line' => null,
                'title' => null,
                'message' => null,
                'created_at' => null,
            ], $fingerprints),
            static fn (array $entry): bool => ($entry['fingerprint'] ?? '') !== ''
        ));
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array{results: array<int, AuditResult>, ignored: int}
     */
    public function filter(array $results): array
    {
        $diff = $this->diff($results);
        $baseline = array_flip(array_column($diff['baselined'], 'fingerprint'));
        $filtered = [];
        $ignored = 0;

        foreach ($results as $result) {
            if (isset($baseline[$this->fingerprint($result)])) {
                $ignored++;
                continue;
            }

            $filtered[] = $result;
        }

        return [
            'results' => $filtered,
            'ignored' => $ignored,
        ];
    }

    public function fingerprint(AuditResult $result): string
    {
        return $this->fingerprints->primary($result);
    }

    public function path(): string
    {
        $configured = $this->config->get('preflight.baseline.file');
        $legacy = $this->config->get('preflight.baseline_file');
        $default = base_path('preflight-baseline.json');

        if (is_string($legacy) && $legacy !== '' && $legacy !== $default && $configured === $default) {
            return $legacy;
        }

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return is_string($legacy) && $legacy !== '' ? $legacy : $default;
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, array<string, mixed>>
     */
    public function generateFromResults(array $results): array
    {
        $entries = [];

        foreach ($results as $result) {
            $fingerprint = $this->fingerprint($result);

            if (isset($entries[$fingerprint])) {
                continue;
            }

            $entries[$fingerprint] = [
                'fingerprint' => $fingerprint,
                'code' => $result->code,
                'scanner' => $result->scanner,
                'severity' => $result->severity->value,
                'confidence' => $result->confidence,
                'file' => $result->file,
                'line' => $result->line,
                'title' => $result->title,
                'message' => $result->message,
                'created_at' => date(DATE_ATOM),
            ];
        }

        return array_values($entries);
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, string>
     */
    public function currentFingerprints(array $results): array
    {
        return array_values(array_unique(array_map(
            fn (AuditResult $result): string => $this->fingerprint($result),
            $results
        )));
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<string, mixed>
     */
    public function diff(array $results): array
    {
        $baselineEntries = $this->entries();
        $baselineByFingerprint = [];

        foreach ($baselineEntries as $entry) {
            $fingerprint = (string) ($entry['fingerprint'] ?? '');

            if ($fingerprint !== '') {
                $baselineByFingerprint[$fingerprint] = $entry;
            }
        }

        $currentEntries = $this->generateFromResults($results);
        $currentByFingerprint = [];

        foreach ($currentEntries as $entry) {
            $currentByFingerprint[(string) $entry['fingerprint']] = $entry;
        }

        $baselined = [];
        $new = [];

        foreach ($currentByFingerprint as $fingerprint => $entry) {
            if (isset($baselineByFingerprint[$fingerprint])) {
                $baselined[] = array_merge($entry, [
                    'created_at' => $baselineByFingerprint[$fingerprint]['created_at'] ?? $entry['created_at'],
                ]);
                continue;
            }

            $new[] = $entry;
        }

        $resolved = [];
        foreach ($baselineByFingerprint as $fingerprint => $entry) {
            if (! isset($currentByFingerprint[$fingerprint])) {
                $resolved[] = $entry;
            }
        }

        return [
            'baselined' => $baselined,
            'new' => $new,
            'resolved' => $resolved,
            'counts' => [
                'baselined' => count($baselined),
                'new' => count($new),
                'resolved' => count($resolved),
                'total_current' => count($currentByFingerprint),
                'total_baseline' => count($baselineByFingerprint),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeEntry(mixed $entry): ?array
    {
        if (! is_array($entry)) {
            return null;
        }

        $fingerprint = (string) ($entry['fingerprint'] ?? '');

        if ($fingerprint === '') {
            return null;
        }

        return [
            'fingerprint' => $fingerprint,
            'code' => $entry['code'] ?? null,
            'scanner' => $entry['scanner'] ?? null,
            'severity' => $entry['severity'] ?? null,
            'confidence' => $entry['confidence'] ?? null,
            'file' => $entry['file'] ?? null,
            'line' => $entry['line'] ?? null,
            'title' => $entry['title'] ?? null,
            'message' => $entry['message'] ?? null,
            'created_at' => $entry['created_at'] ?? null,
        ];
    }
}
