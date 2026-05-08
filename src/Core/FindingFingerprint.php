<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

final class FindingFingerprint
{
    public function primary(AuditResult $result): string
    {
        return hash('sha256', json_encode([
            'code' => $result->code,
            'scanner' => $this->normalize($result->scanner),
            'file' => $this->normalizePath($result->file),
            'title' => $this->normalize($result->title),
        ], JSON_THROW_ON_ERROR));
    }

    public function secondary(AuditResult $result): string
    {
        return hash('sha256', json_encode([
            'code' => $result->code,
            'scanner' => $this->normalize($result->scanner),
            'file' => $this->normalizePath($result->file),
            'title' => $this->normalize($result->title),
            'line' => $result->line,
        ], JSON_THROW_ON_ERROR));
    }

    private function normalize(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return preg_replace('/\s+/', ' ', strtolower(trim($value))) ?: '';
    }

    private function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        return ltrim(str_replace('\\', '/', $path), './');
    }
}
