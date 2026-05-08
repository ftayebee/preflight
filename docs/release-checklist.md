# Release Checklist

Before tagging `v0.1.0`:

- `composer validate --strict`
- PHP lint all package files
- `vendor/bin/phpunit`
- `php artisan preflight:self-test`
- `php artisan preflight:doctor`
- Test in fresh Laravel 11 app
- Test in fresh Laravel 12 app
- Test in one existing Laravel app
- Record notes in `docs/real-world-test-results.md`
- Review README installation
- Review `docs/limitations.md`
- Update `CHANGELOG.md`
- Confirm `LICENSE` is present and MIT
- Confirm Composer distribution excludes test/dev files
- Tag release
- Push tag
- Publish to GitHub
- Submit to Packagist
