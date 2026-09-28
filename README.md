# QueryNova

Search Intelligence to Revenue. SEO, commerce, and AI search intelligence for WordPress.

QueryNova is a modular WordPress plugin. It connects technical SEO, WooCommerce, search analytics, and AI visibility around one question: what should be worked on next, and why it matters to organic growth or organic revenue.

## Requirements

- WordPress 6.4 or newer
- PHP 8.1 or newer
- WooCommerce 8.2 or newer when commerce features are used
- HTTPS for Google OAuth callbacks

## Install

1. Run `composer install --no-dev --optimize-autoloader` and `npm ci && npm run build` before packaging, or install a release ZIP from `npm run package`.
2. Copy the plugin into `wp-content/plugins/querynova`.
3. Activate QueryNova. Activation runs migrations, registers capabilities, and schedules background jobs. It does not delete data on deactivation.

## Development

```bash
composer install
npm install
composer test
composer lint
composer analyse
npm run lint
npm run typecheck
npm run test
npm run build
```

Environment comes from `wp_get_environment_type()` (`local`, `development`, `staging`, `production`). See `docs/environments.md`.

## Documentation

- `docs/user-guide.md`
- `docs/architecture.md`
- `docs/modules.md`
- `docs/implementation-status.md`
- `docs/performance.md`
- `docs/release-notes.md`
- `docs/known-limitations.md`
- `docs/adding-a-feature.md`
- `docs/adding-a-provider.md`

## License

GPL-2.0-or-later.
