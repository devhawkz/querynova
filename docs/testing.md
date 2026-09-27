# Testing

```bash
composer install
composer test
composer lint
composer analyse
npm install
npm run lint
npm run typecheck
npm run test
npm run build
```

PHPUnit covers unit, integration, REST, security, provider, performance, WordPress, and WooCommerce suites as those tests are added. Tests do not call live third-party APIs. WordPress functions used by the foundation are stubbed in `tests/Support/wp-stubs.php`. Domain rules should not need WordPress.

Current coverage includes the container, module boot order, circular dependencies, feature flags, metric provenance, log redaction, SSRF blocking, schema table coverage, job idempotency, validation failures, and debug-mode expiry.

Frontend tests use Vitest once the admin package is installed. CI runs the same commands. See `.github/workflows/ci.yml`.
