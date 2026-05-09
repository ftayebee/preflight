<?php

declare(strict_types=1);

namespace FahimTayebee\Preflight\Scanners;

use FahimTayebee\Preflight\Core\AuditResult;
use FahimTayebee\Preflight\Core\Severity;
use FahimTayebee\Preflight\Scanners\Contracts\ScannerInterface;
use FahimTayebee\Preflight\Support\ScannerOptionResolver;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;

final class AuthScanner implements ScannerInterface
{
    public function __construct(
        private readonly ?ScannerOptionResolver $options = null,
    ) {
    }

    public function name(): string
    {
        return 'auth';
    }

    public function scan(): array
    {
        $results = [];

        foreach (app('router')->getRoutes() as $route) {
            if (! $route instanceof Route) {
                continue;
            }

            $uri = $route->uri();
            $normalizedUri = strtolower($uri);
            $middleware = array_map(static fn (mixed $item): string => strtolower((string) $item), $route->gatherMiddleware());
            $middlewareText = implode('|', $middleware);

            if ($this->isAllowedPublicRoute($normalizedUri)) {
                continue;
            }

            if ($this->containsAny($normalizedUri, $this->optionArray('auth_route_keywords', ['login', 'register', 'password', 'forgot-password', 'reset-password'])) && ! $this->containsAny($middlewareText, $this->optionArray('rate_limit_keywords', ['throttle', 'rate']))) {
                $results[] = $this->routeResult(
                    'AUTH_ROUTE_MISSING_THROTTLE',
                    Severity::Medium,
                    'Auth route is missing throttle middleware',
                    "The {$uri} route looks authentication-related but does not include throttle or rate limiting middleware.",
                    $uri,
                    $route,
                    'Add throttle middleware such as throttle:login, throttle:6,1, or a named Laravel rate limiter.',
                    'medium'
                );
            }

            if ($this->isAdminRoute($normalizedUri) && $this->hasPlainAuth($middleware) && ! $this->containsAny($middlewareText, $this->optionArray('admin_authorization_keywords', ['can:', 'permission:', 'role:', 'abilities:', 'ability:']))) {
                $results[] = $this->routeResult(
                    'AUTH_ADMIN_ROUTE_WEAK_MIDDLEWARE',
                    Severity::High,
                    'Admin route uses weak middleware',
                    "The {$uri} route appears sensitive and only uses auth without role, permission, ability, or policy middleware.",
                    $uri,
                    $route,
                    'Add can:, role:, permission:, ability:, or abilities: middleware for admin and account-management routes.',
                    'medium'
                );
            }

            if ($this->isApiRoute($normalizedUri, $middleware) && $this->hasPlainAuth($middleware) && ! $this->containsAny($middlewareText, $this->optionArray('api_auth_keywords', ['auth:sanctum', 'auth:api', 'auth:passport', 'token', 'ability:', 'abilities:']))) {
                $results[] = $this->routeResult(
                    'AUTH_API_ROUTE_WEAK_GUARD',
                    Severity::Medium,
                    'API route uses a weak token guard',
                    "The {$uri} API route uses generic auth middleware without an explicit API token guard.",
                    $uri,
                    $route,
                    'Use auth:sanctum, auth:api, auth:passport, token middleware, or ability middleware for API routes.',
                    'medium'
                );
            }
        }

        return $results;
    }

    private function isAllowedPublicRoute(string $uri): bool
    {
        foreach ($this->optionArray('public_route_allowlist', (array) config('preflight.public_route_allowlist', [])) as $pattern) {
            if ($pattern !== '' && Str::is(ltrim(strtolower($pattern), '/'), ltrim($uri, '/'))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $middleware
     */
    private function hasPlainAuth(array $middleware): bool
    {
        foreach ($middleware as $item) {
            if ($item === 'auth' || str_starts_with($item, 'auth:')) {
                return true;
            }
        }

        return false;
    }

    private function isAdminRoute(string $uri): bool
    {
        return $this->containsAny($uri, ['admin', 'dashboard', 'settings', 'users', 'roles', 'permissions', 'payment', 'payments']);
    }

    /**
     * @param array<int, string> $middleware
     */
    private function isApiRoute(string $uri, array $middleware): bool
    {
        return str_starts_with(ltrim($uri, '/'), 'api/') || in_array('api', $middleware, true);
    }

    /**
     * @param array<int, string> $needles
     */
    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && str_contains($haystack, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function optionArray(string $key, array $default): array
    {
        $resolver = $this->options ?? app(ScannerOptionResolver::class);

        return array_map(static fn (mixed $item): string => strtolower((string) $item), $resolver->array($this->name(), $key, $default));
    }

    private function routeResult(string $code, Severity $severity, string $title, string $message, string $uri, Route $route, string $recommendation, string $confidence): AuditResult
    {
        return new AuditResult($code, $this->name(), $severity, $title, $message, null, null, $recommendation, $confidence, [
            'uri' => $uri,
            'methods' => array_values(array_diff($route->methods(), ['HEAD'])),
            'middleware' => array_values(array_map(static fn (mixed $item): string => (string) $item, $route->gatherMiddleware())),
        ]);
    }
}
