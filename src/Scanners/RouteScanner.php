<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

final class RouteScanner implements ScannerInterface
{
    public function __construct(
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'routes';
    }

    public function scan(): array
    {
        $results = [];
        $ignored = array_map('strtolower', (array) config('preflight.ignored_routes', []));
        $publicAllowlist = array_map('strtolower', $this->options?->array($this->name(), 'public_route_allowlist', (array) config('preflight.public_route_allowlist', [])) ?? (array) config('preflight.public_route_allowlist', []));
        $sensitivePatterns = array_map('strtolower', $this->options?->array($this->name(), 'sensitive_route_patterns', (array) config('preflight.sensitive_route_patterns', [])) ?? (array) config('preflight.sensitive_route_patterns', []));
        $destructiveKeywords = array_map('strtolower', $this->options?->array($this->name(), 'destructive_uri_keywords', ['delete', 'destroy', 'remove', 'truncate', 'wipe', 'reset']) ?? ['delete', 'destroy', 'remove', 'truncate', 'wipe', 'reset']);
        $dangerousSegments = ['seed', 'migrate', 'reset', 'truncate', 'fresh', 'database'];

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $uri = $route->uri();
            $normalizedUri = strtolower($uri);

            if ($this->isIgnored($normalizedUri, $ignored) || $this->isIgnored($normalizedUri, $publicAllowlist)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $hasAuth = $this->hasAuthMiddleware($middleware);
            $methods = array_values(array_diff($route->methods(), ['HEAD']));
            $actionName = strtolower((string) ($route->getActionName() ?: $route->getAction('uses') ?: ''));

            if ($middleware === []) {
                $results[] = $this->routeResult('ROUTE_NO_MIDDLEWARE', Severity::High, 'Route has no middleware', "The route {$uri} has no middleware attached.", $uri, $methods, 'Attach appropriate web, api, auth, throttle, or authorization middleware.', 'medium');
            }

            if ($this->matchesAnyPattern($normalizedUri, $sensitivePatterns) && ! $hasAuth) {
                $results[] = $this->routeResult('ROUTE_SENSITIVE_WITHOUT_AUTH', Severity::Critical, 'Sensitive route lacks auth middleware', "The route {$uri} appears sensitive but does not use auth middleware.", $uri, $methods, 'Protect sensitive routes with auth and authorization middleware.', 'medium');
            }

            if ($this->containsAny($normalizedUri, $dangerousSegments)) {
                $results[] = $this->routeResult('ROUTE_DANGEROUS_URI', Severity::Critical, 'Dangerous route URI detected', "The route {$uri} contains database or destructive operation terms.", $uri, $methods, 'Remove deployment-only routes or guard them behind strict authorization.', 'high');
            }

            if (in_array('GET', $methods, true) && ($this->containsAny($normalizedUri, $destructiveKeywords) || $this->containsAny($actionName, $destructiveKeywords))) {
                $results[] = $this->routeResult('ROUTE_DESTRUCTIVE_GET', Severity::High, 'GET route appears destructive', "The route {$uri} uses GET for a destructive-looking action.", $uri, $methods, 'Use POST, PATCH, PUT, or DELETE with CSRF protection for state-changing actions.', 'high');
            }

            if ($route->getAction('uses') instanceof \Closure) {
                $results[] = $this->routeResult('ROUTE_CLOSURE_ACTION', Severity::Low, 'Route uses a Closure action', "The route {$uri} uses a Closure action.", $uri, $methods, 'Prefer controller actions for production routes so they remain testable and cache-friendly.', 'high');
            }
        }

        return $results;
    }

    /**
     * @param array<int, string> $ignored
     */
    private function isIgnored(string $uri, array $ignored): bool
    {
        foreach ($ignored as $pattern) {
            if ($pattern !== '' && Str::is(ltrim($pattern, '/'), ltrim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $middleware
     */
    private function hasAuthMiddleware(array $middleware): bool
    {
        $keywords = array_map('strtolower', $this->options?->array($this->name(), 'auth_middleware_keywords', ['auth', 'auth:sanctum', 'verified', 'can:', 'permission:', 'role:']) ?? ['auth', 'auth:sanctum', 'verified', 'can:', 'permission:', 'role:']);
        foreach ($middleware as $item) {
            $middlewareName = strtolower((string) $item);

            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($middlewareName, $keyword)) {
                    return true;
                }
            }
            if (str_contains($middlewareName, 'permission') || str_contains($middlewareName, 'role')) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (preg_match('/(^|[\/_.-])' . preg_quote($needle, '/') . '($|[\/_.-])/', $haystack) === 1 || str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $patterns
     */
    private function matchesAnyPattern(string $uri, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern !== '' && Str::is(ltrim($pattern, '/'), ltrim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $methods
     */
    private function routeResult(string $code, Severity $severity, string $title, string $message, string $uri, array $methods, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, null, null, $recommendation, $confidence, [
            'uri' => $uri,
            'methods' => $methods,
        ]);
    }
}
