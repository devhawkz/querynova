import { describe, expect, it } from 'vitest';
import { DIAGNOSTIC_CARDS, displayValue, normalizeDiagnostics, providerCard, reportText } from './model';

describe('diagnostics screen', () => {
  it('groups diagnostics into environment, health, jobs, and provider cards', () => {
    expect(DIAGNOSTIC_CARDS.map((card) => card.id)).toEqual(['environment', 'health', 'jobs', 'providers']);
    const report = normalizeDiagnostics({ environment: 'production', querynova_build: 'staging' });
    expect(report.environment).toBe('production');
    expect(report.querynovaBuild).toBe('staging');
  });

  it('shows an unconfigured provider as Not connected and does not call it', () => {
    const card = providerCard('search_console', 'not_configured');
    expect(card.state).toBe('Not connected');
    expect(card.called).toBe(false);
  });

  it('keeps missing versions and errors empty', () => {
    const report = normalizeDiagnostics(undefined);
    expect(report.wooCommerceVersion).toBeNull();
    expect(report.dbVersion).toBeNull();
    expect(report.cacheHits).toBeNull();
    expect(report.recentErrors).toEqual([]);
    expect(report.modules).toEqual([]);
    expect(displayValue(null)).toBe('Unavailable');
    expect(displayValue(null)).not.toBe('0');
  });

  it('scrubs a copied report and does not call a missing database version zero', () => {
    const report = normalizeDiagnostics({
      environment: 'local',
      querynova_version: '0.1.0',
      woocommerce_version: null,
      db_version: 0,
      providers: [{ name: 'serp', state: 'not_configured' }],
      cache: { adapter: 'object-cache', hits: 0 },
      recent_errors: [{ message: 'Failed for user@example.com Bearer abcdef123456', reference: 'QN-1' }],
    });
    const text = reportText(report);

    expect(report.dbVersion).toBeNull();
    expect(report.cacheHits).toBeNull();
    expect(text).toContain('Unavailable');
    expect(text).toContain('Not configured');
    expect(text).not.toContain('user@example.com');
    expect(text).not.toContain('abcdef123456');
    expect(text).not.toContain('"hits":0');
    expect(text).not.toContain('"db_version":0');
  });

  it('keeps a production WordPress environment when the installed build is staging', () => {
    const notice =
      'Staging build is running on a production WordPress environment. This build includes additional diagnostic capabilities and is intended primarily for testing. For normal live-site operation, use the production QueryNova build.';
    const report = normalizeDiagnostics({
      environment: 'production',
      querynova_build: 'staging',
      release_channel: 'beta',
      release_status: 'warning',
      release_notice: notice,
    });
    const text = reportText(report);

    expect(report.environment).toBe('production');
    expect(report.querynovaBuild).toBe('staging');
    expect(report.releaseChannel).toBe('beta');
    expect(report.releaseStatus).toBe('warning');
    expect(text).toContain('"wordpress_environment": "production"');
    expect(text).toContain('"querynova_build": "staging"');
    expect(text).toContain('"release_channel": "beta"');
    expect(text).toContain('"release_status": "warning"');
    expect(text).toContain(notice);
    expect(text).not.toContain('"environment": "staging"');
  });
});
