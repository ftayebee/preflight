<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class RuleManager
{
    public function __construct(
        private readonly ConfigRepository $config,
    ) {
    }

    public function isEnabled(string $code): bool
    {
        return (bool) $this->config->get("preflight.rules.{$code}.enabled", true);
    }

    public function isIgnored(string $code): bool
    {
        $ignored = array_map(
            static fn (mixed $item): string => strtoupper(trim((string) $item)),
            (array) $this->config->get('preflight.ignored_issue_codes', [])
        );

        return in_array(strtoupper($code), $ignored, true);
    }

    public function resolveSeverity(AuditResult $result): Severity
    {
        $configured = $this->config->get("preflight.rules.{$result->code}.severity");

        if (! is_string($configured) || $configured === '') {
            return $result->severity;
        }

        return Severity::tryFrom(strtolower($configured)) ?? $result->severity;
    }

    /**
     * @param array<int, AuditResult> $results
     * @return array<int, AuditResult>
     */
    public function apply(array $results): array
    {
        $applied = [];

        foreach ($results as $result) {
            if (! $this->isEnabled($result->code) || $this->isIgnored($result->code)) {
                continue;
            }

            $applied[] = $result->withSeverity($this->resolveSeverity($result));
        }

        return $applied;
    }
}
