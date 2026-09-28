import { describe, expect, it } from 'vitest';
import { historyNote, historyRows, indexState, trackedKeywords, trendState } from './model';

describe('rank tracker', () => {
  it('keeps a missing rank empty and index status unavailable', () => {
    expect(trackedKeywords({
      keywords: [{ id: 1, keyword: 'lager', group: 'beer', location: 'Valjevo', language: 'sr', device: 'desktop', country: 'rs' }],
    })[0]).toMatchObject({ keyword: 'lager', location: 'Valjevo', language: 'sr', device: 'desktop' });
    expect(historyNote({ history: { note: 'No stored rank history. A missing rank is not zero. No SERP vendor is selected.' } }))
      .toContain('not zero');
    expect(historyRows({ history: { rows: [{ position: null, url: '', device: 'desktop' }] } })[0].position).toBe('Not available');
    expect(indexState({ index_status: { state: 'Not available' } })).toBe('Not available');
    expect(trendState({ trends: { state: 'Not available' } })).toBe('Not available');
  });
});
