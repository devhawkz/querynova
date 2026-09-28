import { describe, expect, it } from 'vitest';
import {
  ANALYTICS_SCREENS,
  availabilityState,
  connectionState,
  hasChart,
  kpiCards,
  keywordMovement,
  metricText,
  previousPeriod,
  timingText,
} from './model';

describe('analytics screens', () => {
  it('keeps disconnected metrics unlabeled as zero and skips charts', () => {
    expect(ANALYTICS_SCREENS).toEqual([
      'overview',
      'seo_performance',
      'keywords',
      'content',
      'rank_tracker',
      'index_status',
      'traffic',
      'commerce',
      'ai',
    ]);
    expect(connectionState({ state: 'Not connected' })).toBe('Not connected');
    expect(metricText(null, 'Not connected')).toBe('Not connected');
    expect(metricText(null, 'Not available')).toBe('Not available');
    expect(kpiCards({
      kpis: [
        { id: 'clicks', label: 'Clicks', value: null, state: 'Not connected' },
        { id: 'sessions', label: 'Sessions', value: null, state: 'Not connected' },
      ],
    }).map((card) => card.text)).toEqual(['Not connected', 'Not connected']);
    expect(keywordMovement({ comparison: { winning_keywords: null } }, 'winning_keywords')).toBeNull();
    expect(availabilityState({ state: 'Not available' })).toBe('Not available');
    expect(timingText(null)).toBe('Not available');
    expect(hasChart({ kpis: [], trends: { rows: null } })).toBe(false);
    expect(previousPeriod({ range: { previous_start: '2026-01-01', previous_end: '2026-01-07' } })).toBe('2026-01-01 to 2026-01-07');
  });
});
