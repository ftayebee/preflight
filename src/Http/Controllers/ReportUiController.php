<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Http\Controllers;

use FahimTayebee\Preflight\Support\ReportRepository;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\Response;

final class ReportUiController
{
    public function __construct(private readonly ReportRepository $reports)
    {
    }

    public function index(): View
    {
        $this->ensureEnvironmentAllowed();

        return view('preflight::index', [
            'reports' => $this->reports->reports(),
            'latest' => $this->reports->latest(),
            'path' => trim((string) config('preflight.ui.path', 'preflight'), '/'),
        ]);
    }

    public function latest(): View
    {
        $this->ensureEnvironmentAllowed();

        $report = $this->reports->latest();

        if ($report === null) {
            abort(Response::HTTP_NOT_FOUND, 'No Preflight HTML reports found.');
        }

        return $this->renderReport($report);
    }

    public function show(string $filename): View
    {
        $this->ensureEnvironmentAllowed();

        if ($filename !== basename($filename) || str_contains($filename, '..')) {
            abort(Response::HTTP_NOT_FOUND, 'Preflight HTML report not found.');
        }

        $report = $this->reports->find($filename);

        if ($report === null || ! $this->reports->isAllowedReportPath($report['path'])) {
            abort(Response::HTTP_NOT_FOUND, 'Preflight HTML report not found.');
        }

        return $this->renderReport($report);
    }

    private function renderReport(array $report): View
    {
        $contents = file_get_contents($report['path']);

        if (! is_string($contents)) {
            abort(Response::HTTP_NOT_FOUND, 'Preflight HTML report not found.');
        }

        return view('preflight::show', [
            'report' => $report,
            'reportHtml' => $contents,
            'path' => trim((string) config('preflight.ui.path', 'preflight'), '/'),
        ]);
    }

    private function ensureEnvironmentAllowed(): void
    {
        $allowed = (array) config('preflight.ui.allowed_environments', []);

        if ($allowed === [] || ! app()->environment($allowed)) {
            abort(Response::HTTP_FORBIDDEN, 'Preflight UI is not available in this environment.');
        }
    }
}
