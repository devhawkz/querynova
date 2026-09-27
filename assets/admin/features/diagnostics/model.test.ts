import { describe, expect, it } from 'vitest';
import { displayValue, normalizeDiagnostics, reportText } from './model';

describe('diagnostics screen', () => {
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
});
