<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

final class AuditResult
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $code,
        public readonly string $scanner,
        public readonly Severity $severity,
        public readonly string $title,
        public readonly string $message,
        public readonly ?string $file = null,
        public readonly ?int $line = null,
        public readonly ?string $recommendation = null,
        public readonly string $confidence = 'medium',
        public readonly array $metadata = [],
    ) {
        if (! in_array($this->confidence, ['high', 'medium', 'low'], true)) {
            throw new \InvalidArgumentException('Audit result confidence must be high, medium, or low.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'scanner' => $this->scanner,
            'severity' => $this->severity->value,
            'title' => $this->title,
            'message' => $this->message,
            'file' => $this->file,
            'line' => $this->line,
            'recommendation' => $this->recommendation,
            'confidence' => $this->confidence,
            'metadata' => $this->metadata,
        ];
    }

    public function isCritical(): bool
    {
        return $this->severity === Severity::Critical;
    }

    public function isHigh(): bool
    {
        return $this->severity === Severity::High;
    }

    public function isMedium(): bool
    {
        return $this->severity === Severity::Medium;
    }

    public function isLow(): bool
    {
        return $this->severity === Severity::Low;
    }

    public function withSeverity(Severity $severity): self
    {
        return new self(
            $this->code,
            $this->scanner,
            $severity,
            $this->title,
            $this->message,
            $this->file,
            $this->line,
            $this->recommendation,
            $this->confidence,
            $this->metadata,
        );
    }
}
