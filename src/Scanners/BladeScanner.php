<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\FileReader;
use FahimTayebee\Preflight\Support\PathResolver;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use Illuminate\Support\Str;

final class BladeScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'blade';
    }

    public function scan(): array
    {
        $results = [];

        foreach ($this->scanFiles($this->paths->base('resources/views')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $file = $this->paths->relative($path);

            if (preg_match_all('/\{!!\s*(.*?)\s*!!\}/s', $contents, $matches, PREG_OFFSET_CAPTURE) > 0) {
                foreach ($matches[0] as $match) {
                    $results[] = $this->result(
                        'BLADE_RAW_OUTPUT',
                        Severity::High,
                        'Raw Blade output detected',
                        'The template renders raw Blade output with {!! !!}.',
                        $file,
                        $contents,
                        $match[0],
                        'Use escaped Blade output {{ $variable }} unless raw HTML is intentionally sanitized.',
                        'high'
                    );
                }
            }

            foreach ($this->forms($contents) as $form) {
                if ($this->requiresCsrf($form['open']) && ! $this->hasCsrf($form['body'])) {
                    $results[] = $this->result(
                        'BLADE_FORM_MISSING_CSRF',
                        Severity::High,
                        'Blade form is missing CSRF protection',
                        'A POST, PUT, PATCH, or DELETE form does not include @csrf or csrf_field().',
                        $file,
                        $contents,
                        $form['open'],
                        'Add @csrf inside the form so Laravel can reject forged requests.',
                        'medium'
                    );
                }

                if ($this->looksDestructive($form['html']) && ! $this->hasDeleteSpoofing($form['body'])) {
                    $results[] = $this->result(
                        'BLADE_DELETE_FORM_MISSING_METHOD',
                        Severity::Medium,
                        'Delete form is missing method spoofing',
                        'A destructive-looking form does not spoof the DELETE method.',
                        $file,
                        $contents,
                        $form['open'],
                        "Add @method('DELETE') or method_field('DELETE') to destructive forms.",
                        'medium'
                    );
                }

                if ($this->looksAdminAction($form['html']) && ! $this->hasAuthorizationNearby($contents, $form['offset'])) {
                    $results[] = $this->result(
                        'BLADE_UNGUARDED_ADMIN_ACTION',
                        Severity::Low,
                        'Admin action may be missing a Blade authorization guard',
                        'A sensitive-looking form appears without a nearby Blade authorization directive.',
                        $file,
                        $contents,
                        $form['open'],
                        'Wrap sensitive actions with @can, @role, @permission, @auth, or another project authorization check.',
                        'low'
                    );
                }
            }

            foreach ($this->adminActionElements($contents) as $element) {
                if (! $this->hasAuthorizationNearby($contents, $element['offset'])) {
                    $results[] = $this->result(
                        'BLADE_UNGUARDED_ADMIN_ACTION',
                        Severity::Low,
                        'Admin action may be missing a Blade authorization guard',
                        'A sensitive-looking link or button appears without a nearby Blade authorization directive.',
                        $file,
                        $contents,
                        $element['html'],
                        'Wrap sensitive actions with @can, @role, @permission, @auth, or another project authorization check.',
                        'low'
                    );
                }
            }
        }

        return $results;
    }

    /**
     * @return array<int, string>
     */
    private function scanFiles(string $directory): array
    {
        return array_values(array_filter(
            $this->files->files($directory, 'php'),
            fn (string $path): bool => str_ends_with($path, '.blade.php') && ! $this->isIgnored($path)
        ));
    }

    private function isIgnored(string $path): bool
    {
        $relative = strtolower($this->paths->relative($path));

        foreach (array_merge((array) config('preflight.ignored_paths', []), $this->optionArray('ignored_view_paths', [])) as $pattern) {
            if (Str::is(strtolower((string) $pattern), $relative)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{html: string, open: string, body: string, offset: int}>
     */
    private function forms(string $contents): array
    {
        $forms = [];

        if (preg_match_all('/<form\b(?P<attrs>[^>]*)>(?P<body>.*?)<\/form>/is', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $forms;
        }

        foreach ($matches[0] as $index => $match) {
            $open = '<form' . $matches['attrs'][$index][0] . '>';
            $forms[] = [
                'html' => $match[0],
                'open' => $open,
                'body' => $matches['body'][$index][0],
                'offset' => $match[1],
            ];
        }

        return $forms;
    }

    private function requiresCsrf(string $openTag): bool
    {
        return preg_match('/method\s*=\s*([\'"])(post|put|patch|delete)\1/i', $openTag) === 1;
    }

    private function hasCsrf(string $body): bool
    {
        return stripos($body, '@csrf') !== false || stripos($body, 'csrf_field()') !== false;
    }

    private function hasDeleteSpoofing(string $body): bool
    {
        return preg_match('/@method\s*\(\s*[\'"]DELETE[\'"]\s*\)|method_field\s*\(\s*[\'"]DELETE[\'"]\s*\)/i', $body) === 1;
    }

    private function looksDestructive(string $html): bool
    {
        return $this->containsAny(strtolower($html), ['delete', 'destroy', 'remove']);
    }

    private function looksAdminAction(string $html): bool
    {
        return $this->containsAny(strtolower(strip_tags($html)), array_map('strtolower', $this->optionArray('admin_action_keywords', [
            'delete',
            'destroy',
            'edit',
            'role',
            'permission',
            'admin',
            'payment',
        ]))) || $this->containsAny(strtolower($html), array_map('strtolower', $this->optionArray('admin_action_keywords', [])));
    }

    /**
     * @return array<int, array{html: string, offset: int}>
     */
    private function adminActionElements(string $contents): array
    {
        $elements = [];

        if (preg_match_all('/<(a|button)\b[^>]*>.*?<\/\1>/is', $contents, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return $elements;
        }

        foreach ($matches[0] as $match) {
            if ($this->looksAdminAction($match[0])) {
                $elements[] = ['html' => $match[0], 'offset' => $match[1]];
            }
        }

        return $elements;
    }

    private function hasAuthorizationNearby(string $contents, int $offset): bool
    {
        $window = substr($contents, max(0, $offset - 500), 1000);

        foreach ($this->optionArray('authorization_directives', ['@can', '@cannot', '@role', '@permission', '@auth', '@unless']) as $directive) {
            if ($directive !== '' && stripos($window, $directive) !== false) {
                return true;
            }
        }

        return stripos($window, 'Gate::') !== false;
    }

    private function result(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, $confidence);
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        $resolver = $this->options ?? app(ScannerOptionResolver::class);

        return $resolver->array($this->name(), $key, $default);
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
