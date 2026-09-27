import { describe, expect, it } from 'vitest';
import { SECTION_TITLES } from '../dashboard/sections';
import { ADVANCED_TITLES, normalizeAdvanced } from './advanced';

describe('advanced detail view', () => {
  it('stays empty until rows are supplied and stays off the primary sections', () => {
    const sections = normalizeAdvanced(undefined);
    expect(sections.raw).toEqual([]);
    expect(sections.serps).toEqual([]);
    expect(sections.keywords).toEqual([]);
    expect(sections.backlinks).toEqual([]);
    expect(sections.methodologies).toEqual([]);
    expect(sections.providers).toEqual([]);
    expect(sections.confidence).toEqual([]);
    expect(sections.history).toEqual([]);
    expect(Object.values(SECTION_TITLES)).not.toContain('SERPs');
    expect(Object.values(SECTION_TITLES)).not.toContain('Raw Data');
    expect(ADVANCED_TITLES.history).toBe('Historical Data');
  });

  it('keeps an estimated difficulty out of the measured label and does not turn a missing rank into zero', () => {
    const sections = normalizeAdvanced({
      keywords: [
        {
          id: 'keyword-1',
          title: 'lager',
          summary: 'Organic difficulty 40 · Estimated',
          metric: { value: null, kind: 'UNAVAILABLE', label: 'Unavailable' },
        },
      ],
      history: [
        {
          id: 'rank-1',
          title: 'https://example.test/lager',
          summary: 'rs desktop',
          metric: { value: null, kind: 'UNAVAILABLE', label: 'Unavailable' },
        },
      ],
    });

    expect(sections.keywords[0]?.summary).toContain('Estimated');
    expect(sections.keywords[0]?.summary).not.toContain('Measured');
    expect(JSON.stringify(sections.history[0])).not.toContain('"value":0');
    expect(sections.raw).toEqual([]);
  });
});
