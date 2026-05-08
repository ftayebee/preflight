<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Reporters\Contracts;

interface ReporterInterface
{
    /**
     * @param array<string, mixed> $report
     */
    public function render(array $report): string;
}
