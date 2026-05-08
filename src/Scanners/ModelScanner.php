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

final class ModelScanner implements ScannerInterface
{
    public function __construct(
        private readonly PathResolver $paths,
        private readonly FileReader $files,
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'models';
    }

    public function scan(): array
    {
        $results = [];

        foreach ($this->scanFiles($this->paths->app('Models')) as $path) {
            $contents = $this->files->get($path);

            if ($contents === null) {
                continue;
            }

            $scanContents = $this->stripPhpComments($contents);
            $file = $this->paths->relative($path);
            $isPivot = preg_match('/extends\s+(Pivot|.*\\\\Pivot)\b/i', $scanContents) === 1;
            $hasFillable = preg_match('/protected\s+\$fillable\s*=/i', $scanContents) === 1;
            $hasGuarded = preg_match('/protected\s+\$guarded\s*=/i', $scanContents) === 1;

            if (preg_match('/protected\s+\$guarded\s*=\s*\[\s*\]\s*;/i', $scanContents) === 1) {
                $results[] = $this->fileResult('MODEL_GUARDED_EMPTY', Severity::High, 'Model allows all attributes for mass assignment', 'protected $guarded = [] disables mass-assignment protection for every column.', $file, $contents, '$guarded', 'Prefer explicit $fillable attributes or a non-empty $guarded list.', 'high');
            }

            if ($this->fillableContainsSensitiveField($scanContents) && ! $this->hasHashingContext($scanContents)) {
                $results[] = $this->fileResult('MODEL_PASSWORD_FILLABLE_WITHOUT_HASHING', Severity::Critical, 'Sensitive field is fillable without visible protection', 'The model includes a sensitive field in $fillable and no obvious protection context was found.', $file, $contents, '$fillable', 'Ensure sensitive assignment is protected before persistence.', 'medium');
            }

            if ($this->containsAny($scanContents, $this->optionArray('plaintext_password_fields', ['visible_password', 'plain_password', 'plaintext_password']))) {
                $results[] = $this->fileResult('MODEL_PLAINTEXT_PASSWORD', Severity::Critical, 'Plain password field detected on model', 'Model references visible_password or plain_password.', $file, $contents, 'password', 'Remove plain-text password fields and store only hashed passwords.', 'high');
            }

            if (stripos($scanContents, 'HasFactory') === false) {
                $results[] = $this->fileResult('MODEL_MISSING_HAS_FACTORY', Severity::Info, 'Model does not use HasFactory', 'The model does not appear to use the HasFactory trait.', $file, $contents, 'class ', 'Add HasFactory if this model needs factory support for tests or seeding.', 'medium');
            }

            if (! $isPivot && ! $hasFillable && ! $hasGuarded) {
                $results[] = $this->fileResult('MODEL_MISSING_MASS_ASSIGNMENT_DECLARATION', Severity::Low, 'Model has no mass-assignment declaration', 'No $fillable or $guarded property was found.', $file, $contents, 'class ', 'Declare either $fillable or $guarded to make mass-assignment behavior explicit.', 'high');
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
            $this->files->files($directory),
            fn (string $path): bool => ! $this->isIgnored($path)
        ));
    }

    private function isIgnored(string $path): bool
    {
        $relative = strtolower($this->paths->relative($path));

        foreach ((array) config('preflight.ignored_paths', []) as $pattern) {
            if (Str::is(strtolower((string) $pattern), $relative)) {
                return true;
            }
        }

        return false;
    }

    private function fillableContainsSensitiveField(string $contents): bool
    {
        if (preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\]\s*;/is', $contents, $matches) !== 1) {
            return false;
        }

        foreach ($this->optionArray('sensitive_fillable_fields', ['password', 'is_admin', 'role', 'permissions']) as $field) {
            if (preg_match('/[\'"]' . preg_quote($field, '/') . '[\'"]/', $matches[1]) === 1) {
                return true;
            }
        }

        return false;
    }

    private function hasHashingContext(string $contents): bool
    {
        return preg_match('/Hash::make|bcrypt\s*\(|[\'"]password[\'"]\s*=>\s*[\'"]hashed[\'"]|setPasswordAttribute|Attribute::make/i', $contents) === 1;
    }

    private function fileResult(string $code, Severity $severity, string $title, string $message, string $file, string $contents, string $needle, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, $file, $this->files->lineFor($contents, $needle), $recommendation, $confidence);
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        return $this->options?->array($this->name(), $key, $default) ?? $default;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $contents, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($contents, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function stripPhpComments(string $content): string
    {
        $tokens = token_get_all($content);
        $output = '';

        foreach ($tokens as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $output .= is_array($token) ? $token[1] : $token;
        }

        return $output;
    }
}
