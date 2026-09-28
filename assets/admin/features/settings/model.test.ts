import { describe, expect, it } from 'vitest';
import { featureStatus, featuresFor, normalizeSettings, SETTINGS_SECTIONS } from './model';

describe('settings catalog', () => {
  it('lists the settings sections in information-architecture order', () => {
    expect(SETTINGS_SECTIONS.map((section) => section.label)).toEqual([
      'General',
      'SEO',
      'Titles & Meta',
      'Links',
      'Breadcrumbs',
      'Images',
      'Sitemaps',
      'Schema',
      'Webmaster Tools',
      'WooCommerce',
      'Local SEO',
      'Analytics',
      'Providers',
      'AI',
      'Roles',
      'Advanced',
      'Tools',
    ]);
  });

  it('keeps an off feature off and the build separate from the environment', () => {
    const model = normalizeSettings({
      wordpress_environment: 'production',
      querynova_build: 'staging',
      modules: [
        {
          name: 'sitemap',
          version: '0.1.2',
          optional: true,
          dependencies: ['core'],
          failed: false,
          registered: true,
          features: [
            {
              id: 'querynova.sitemap.news',
              name: 'News sitemap',
              module: 'sitemap',
              state: 'OFF',
              enabled: false,
              experimental: false,
            },
          ],
        },
      ],
    });

    expect(model.wordpressEnvironment).toBe('production');
    expect(model.querynovaBuild).toBe('staging');
    expect(featureStatus(featuresFor(model, 'sitemap')[0]!).label).toBe('Off');
    expect(featureStatus({ id: 'llms', name: 'llms.txt', module: 'ai', state: 'EXPERIMENTAL', enabled: true, experimental: true }).label).toBe('Experimental');
    expect(normalizeSettings(undefined).modules).toEqual([]);
  });
});
