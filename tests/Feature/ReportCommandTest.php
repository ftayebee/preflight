<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Tests\Feature;

use FahimTayebee\Preflight\Support\ReportOpener;
use FahimTayebee\Preflight\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

final class ReportCommandTest extends TestCase
{
    private string $defaultReport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultReport = base_path('storage/app/preflight-report-command.html');
        config()->set('preflight.reports.default_html', $this->defaultReport);
        config()->set('preflight.html.default_output', $this->defaultReport);
        config()->set('preflight.reports.directory', base_path('storage/app/preflight-reports'));
        @unlink($this->defaultReport);
        $this->fakeOpener();
    }

    protected function tearDown(): void
    {
        @unlink($this->defaultReport);
        $directory = base_path('storage/app/preflight-reports');
        foreach (glob($directory . '/*.html') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_report_command_shows_helpful_message_when_no_report_exists(): void
    {
        $exitCode = Artisan::call('preflight:report');
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No HTML report found.', $output);
        $this->assertStringContainsString('Run: php artisan preflight:audit --format=html', $output);
        $this->assertStringContainsString('Or run: php artisan preflight:report --generate', $output);
    }

    public function test_report_command_file_opens_existing_html_without_failing(): void
    {
        $path = base_path('storage/app/existing-report.html');
        file_put_contents($path, '<html></html>');

        $exitCode = Artisan::call('preflight:report', ['--file' => $path]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('HTML report:', Artisan::output());

        @unlink($path);
    }

    public function test_report_command_missing_file_returns_failure(): void
    {
        $exitCode = Artisan::call('preflight:report', ['--file' => 'storage/app/missing-report.html']);

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('HTML report file not found:', Artisan::output());
    }

    public function test_report_command_generate_creates_html_report(): void
    {
        config()->set('preflight.enabled_scanners', []);

        $exitCode = Artisan::call('preflight:report', ['--generate' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertFileExists($this->defaultReport);
        $this->assertStringContainsString('HTML report saved to:', Artisan::output());
    }

    public function test_report_command_latest_selects_newest_html_report(): void
    {
        $directory = base_path('storage/app/preflight-reports');
        is_dir($directory) || mkdir($directory, 0755, true);
        $old = $directory . '/old.html';
        $new = $directory . '/new.html';
        file_put_contents($old, '<html>old</html>');
        file_put_contents($new, '<html>new</html>');
        touch($old, time() - 60);
        touch($new, time());

        $exitCode = Artisan::call('preflight:report', ['--latest' => true]);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('new.html', Artisan::output());
    }

    public function test_report_opener_handles_failure_safely(): void
    {
        $opener = new class extends ReportOpener {
            public function open(string $path): bool
            {
                return false;
            }

            public function lastError(): ?string
            {
                return 'failed';
            }
        };

        $this->assertFalse($opener->open('missing.html'));
        $this->assertSame('failed', $opener->lastError());
    }

    public function test_report_command_is_registered(): void
    {
        $commands = array_keys(Artisan::all());

        $this->assertContains('preflight:report', $commands);
    }

    private function fakeOpener(): void
    {
        app()->instance(ReportOpener::class, new class extends ReportOpener {
            public function open(string $path): bool
            {
                return true;
            }
        });
    }
}
