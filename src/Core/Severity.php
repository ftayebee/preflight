<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Core;

enum Severity: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';
    case Info = 'info';
}
