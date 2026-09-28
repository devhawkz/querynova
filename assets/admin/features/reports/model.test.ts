import { describe, expect, it } from 'vitest';
import { REPORT_KINDS, importPreviewNote, reportFormat, schedulePlan, settingsImport, whiteLabelPlan } from './model';

describe('reports workspace', () => {
  it('offers csv and json and does not claim a pdf', () => {
    expect(REPORT_KINDS).toEqual(['organic', 'content', 'keyword', 'rank', 'index', 'woocommerce', 'ai_visibility']);
    expect(reportFormat('pdf').format).toBe('json');
    expect(reportFormat('pdf').pdf).toBe(false);
    expect(reportFormat('csv').note).toBe('PDF is not generated.');
  });

  it('stores a schedule without sending mail or customer records', () => {
    const held = schedulePlan(['editor@example.com'], 'organic', false);
    const stored = schedulePlan(['editor@example.com'], 'organic', true);

    expect(held.sent).toBe(false);
    expect(held.customers).toBe(false);
    expect(held.stored).toBe(false);
    expect(stored.stored).toBe(true);
    expect(stored.sent).toBe(false);
    expect(stored.note).toContain('does not include customer records');
  });

  it('keeps security notices visible and previews import without writing', () => {
    expect(whiteLabelPlan().hides_security).toBe(false);
    expect(whiteLabelPlan().security).toContain('does not hide');
    expect(importPreviewNote()).toContain('other plugin was not disabled');
    expect(settingsImport(false).changed_posts).toBe(false);
    expect(settingsImport(true).note).toContain('Posts were not changed');
  });
});
