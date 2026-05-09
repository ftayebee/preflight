<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\PreflightServiceProvider;
use FahimTayebee\Preflight\Support\ReportRepository;
use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ReportUiTest extends TestCase
{
    private string $reportsDirectory;

    private string $fallbackReport;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('preflight.ui.enabled', true);
        $app['config']->set('preflight.ui.path', 'preflight');
        $app['config']->set('preflight.ui.middleware', []);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportsDirectory = base_path('storage/app/preflight-ui-reports');
        $this->fallbackReport = base_path('storage/app/preflight-ui-fallback.html');

        is_dir($this->reportsDirectory) || mkdir($this->reportsDirectory, 0755, true);
        config()->set('preflight.ui.reports_directory', $this->reportsDirectory);
        config()->set('preflight.ui.fallback_report', $this->fallbackReport);
        config()->set('preflight.ui.allowed_environments', ['testing']);

        $this->clearReports();
    }

    protected function tearDown(): void
    {
        $this->clearReports();

        parent::tearDown();
    }

    public function test_ui_routes_are_registered_when_enabled(): void
    {
        $this->assertTrue(Route::has('preflight.index'));
        $this->assertTrue(Route::has('preflight.latest'));
        $this->assertTrue(Route::has('preflight.report'));
    }

    public function test_index_returns_report_list_in_allowed_environment(): void
    {
        $this->writeReport('alpha.html', '<html><body>Alpha</body></html>');

        $this->get('/preflight')
            ->assertOk()
            ->assertSee('Generated HTML Reports')
            ->assertSee('alpha.html');
    }

    public function test_latest_shows_latest_report(): void
    {
        $old = $this->writeReport('old.html', '<html><body>Old report</body></html>', time() - 60);
        $new = $this->writeReport('new.html', '<html><body>Newest report</body></html>', time());

        $this->assertFileExists($old);
        $this->assertFileExists($new);

        $this->get('/preflight/latest')
            ->assertOk()
            ->assertSee('new.html')
            ->assertSee('Newest report');
    }

    public function test_specific_report_is_shown(): void
    {
        $this->writeReport('specific.html', '<html><body>Specific report</body></html>');

        $this->get('/preflight/report/specific.html')
            ->assertOk()
            ->assertSee('specific.html')
            ->assertSee('Specific report');
    }

    public function test_production_environment_is_blocked_by_default(): void
    {
        $this->writeReport('prod.html', '<html><body>Prod</body></html>');
        config()->set('preflight.ui.allowed_environments', ['local', 'development', 'testing']);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->get('/preflight')
            ->assertForbidden()
            ->assertSee('Preflight UI is not available in this environment.');
    }

    public function test_path_traversal_is_blocked(): void
    {
        $this->writeReport('safe.html', '<html><body>Safe</body></html>');

        $this->get('/preflight/report/..%2Fsafe.html')
            ->assertStatus(404);
    }

    public function test_non_html_files_are_blocked(): void
    {
        file_put_contents($this->reportsDirectory . '/notes.txt', 'not html');

        $this->get('/preflight/report/notes.txt')
            ->assertStatus(404);
    }

    public function test_missing_report_returns_404(): void
    {
        $this->get('/preflight/report/missing.html')
            ->assertStatus(404);
    }

    public function test_repository_lists_reports_sorted_newest_first(): void
    {
        $this->writeReport('first.html', '<html>first</html>', time() - 100);
        $this->writeReport('second.html', '<html>second</html>', time());

        $reports = (new ReportRepository())->reports();

        $this->assertSame('second.html', $reports[0]['filename']);
        $this->assertSame('first.html', $reports[1]['filename']);
    }

    public function test_repository_includes_fallback_report(): void
    {
        file_put_contents($this->fallbackReport, '<html>fallback</html>');

        $reports = (new ReportRepository())->reports();

        $this->assertContains('preflight-ui-fallback.html', array_column($reports, 'filename'));
        $this->assertTrue($reports[array_search('preflight-ui-fallback.html', array_column($reports, 'filename'), true)]['is_fallback']);
    }

    public function test_service_provider_publishes_views_tag(): void
    {
        $paths = ServiceProvider::pathsToPublish(PreflightServiceProvider::class, 'preflight-views');

        $this->assertNotEmpty($paths);
    }

    public function test_doctor_output_includes_ui_status(): void
    {
        $exitCode = Artisan::call('preflight:doctor');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('UI enabled: yes', $output);
        $this->assertStringContainsString('Current environment allowed: yes', $output);
    }

    private function writeReport(string $filename, string $contents, ?int $modifiedAt = null): string
    {
        $path = $this->reportsDirectory . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($path, $contents);

        if ($modifiedAt !== null) {
            touch($path, $modifiedAt);
        }

        return $path;
    }

    private function clearReports(): void
    {
        foreach (glob($this->reportsDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        @unlink($this->fallbackReport);
    }
}
