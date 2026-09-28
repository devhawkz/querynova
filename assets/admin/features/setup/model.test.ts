import { describe, expect, it } from 'vitest';
import { draftPayload, normalizeSetup, SETUP_STEPS, setupLine, setupPayload, WIZARD_PAGES } from './model';

describe('setup wizard', () => {
  it('leaves unanswered steps empty and does not show a provider as connected', () => {
    const setup = normalizeSetup(undefined);
    expect(setup.siteType).toBeNull();
    expect(setup.wooCommerce).toBeNull();
    expect(setup.searchConsoleState).toBe('not_configured');
    expect(setup.connected).toBe(false);
    expect(setup.crawlStarted).toBe(false);
    expect(setupLine(null)).toBe('Nothing recorded.');
    expect(SETUP_STEPS).toContain('Search Console');
    expect(SETUP_STEPS).toContain('Crawler');
    expect(WIZARD_PAGES).toEqual([
      'Site',
      'Business',
      'Search engines',
      'Analytics',
      'SEO defaults',
      'Sitemaps',
      'WooCommerce',
      'Review',
    ]);
  });

  it('drops secrets from the save payload', () => {
    const payload = setupPayload({
      site_type: 'store',
      search_console: 'sc-domain:example.test',
      api_key: 'secret-value',
      token: 'secret-token',
    });

    expect(payload).toEqual({
      site_type: 'store',
      search_console: { property: 'sc-domain:example.test' },
    });
    expect(JSON.stringify(payload)).not.toContain('secret-value');
    expect(JSON.stringify(payload)).not.toContain('secret-token');
  });

  it('sends the recorded answers and leaves providers disconnected', () => {
    const payload = draftPayload(
      normalizeSetup({
        site_type: 'publisher',
        search_console: { property: 'sc-domain:example.test', state: 'connected', api_key: 'secret-value' },
        schema_enabled: true,
        crawler_origin: 'https://example.test/',
      }),
    );

    expect(payload.search_console).toEqual({ property: 'sc-domain:example.test' });
    expect(payload.schema_enabled).toBe(true);
    expect(payload.crawler_origin).toBe('https://example.test/');
    expect(JSON.stringify(payload)).not.toContain('secret-value');
    expect(JSON.stringify(payload)).not.toContain('connected');
  });
});
