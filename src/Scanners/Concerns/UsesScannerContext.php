<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners\Concerns;

use FahimTayebee\Preflight\Core\ScannerContext;

trait UsesScannerContext
{
    private ?ScannerContext $context = null;

    public function setContext(ScannerContext $context): void
    {
        $this->context = $context;
    }

    private function shouldScanFile(string $path): bool
    {
        return $this->context?->shouldScanFile($path) ?? true;
    }

    private function isChangedMode(): bool
    {
        return $this->context?->isChangedMode() ?? false;
    }
}
