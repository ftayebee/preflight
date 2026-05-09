<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Route;

final class ReportUiDisabledTest extends TestCase
{
    public function test_ui_routes_are_not_registered_when_disabled(): void
    {
        $this->assertFalse(Route::has('preflight.index'));
        $this->assertFalse(Route::has('preflight.latest'));
        $this->assertFalse(Route::has('preflight.report'));
    }
}
