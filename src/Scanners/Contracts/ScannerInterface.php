<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners\Contracts;

interface ScannerInterface
{
    public function name(): string;

    /**
     * @return array<int, \FahimTayebee\Preflight\Core\AuditResult>
     */
    public function scan(): array;
}
