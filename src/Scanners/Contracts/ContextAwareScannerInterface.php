<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners\Contracts;

use FahimTayebee\Preflight\Core\ScannerContext;

interface ContextAwareScannerInterface
{
    public function setContext(ScannerContext $context): void;
}
