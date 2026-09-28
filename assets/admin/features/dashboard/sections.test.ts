import { describe, expect, it } from 'vitest';
import { dashboardKpis, metricLine, normalizeSections, primaryText, SECTION_TITLES, visibleActions, type TodayAction } from './sections';

describe('what matters now sections', () => {
  it('keeps every section empty when nothing was supplied', () => {
    const sections = normalizeSections(undefined);
    expect(sections.revenue).toEqual([]);
    expect(sections.search).toEqual([]);
    expect(sections.technical).toEqual([]);
    expect(sections.commerce).toEqual([]);
    expect(sections.ai).toEqual([]);
    expect(sections.recent).toEqual([]);
    expect(Object.values(SECTION_TITLES)).toEqual([
      'Revenue Opportunities',
      'Search Opportunities',
      'Technical Risks',
      'Commerce Risks',
      'AI Opportunities',
      'Recent Changes',
    ]);
  });

  it('labels an estimate as estimated and leaves a missing amount empty', () => {
    const sections = normalizeSections({
      revenue: [
        {
          id: 'rev-1',
          title: 'Close the revenue gap',
          summary: 'Supplied clicks and revenue.',
          metric: { value: 87.3649, kind: 'ESTIMATED', label: 'Estimated' },
          methodology: 'querynova.ctr_revenue_gap',
          provider: 'secret-provider',
          serp: [{ url: 'https://serp.example' }],
          keywords: ['lager'],
          backlinks: [{ domain: 'example.test' }],
        },
      ],
      search: [
        {
          id: 'search-1',
          title: 'Major rank loss',
          summary: 'The supplied rank dropped.',
          metric: { value: null, kind: 'MEASURED', label: 'Measured' },
        },
      ],
    });

    const revenue = sections.revenue[0];
    expect(revenue).toBeDefined();
    const line = primaryText(revenue!);
    expect(line).toBe('Close the revenue gap Supplied clicks and revenue. 87 · Estimated');
    expect(line).not.toContain('Measured');
    expect(line).not.toContain('querynova.ctr_revenue_gap');
    expect(line).not.toContain('secret-provider');
    expect(JSON.stringify(revenue)).not.toContain('serp');
    expect(JSON.stringify(revenue)).not.toContain('keywords');
    expect(JSON.stringify(revenue)).not.toContain('backlinks');
    expect(metricLine(sections.search[0]?.metric ?? null)).toBe('Unavailable');
    expect(primaryText(sections.search[0]!)).not.toContain('0');
    expect(primaryText(sections.search[0]!)).not.toContain('Measured');
  });

  it('does not pad a short list and stops after ten actions', () => {
    const actions: TodayAction[] = Array.from({ length: 12 }, (_, index) => ({
      id: index + 1,
      title: index === 0 ? '   ' : `Action ${index + 1}`,
      rationale: '',
      impact: 'estimated',
      confidence: 'LOW',
      provenance: 'ESTIMATED',
    }));

    const visible = visibleActions(actions);
    expect(visible).toHaveLength(10);
    expect(visible[0]?.title).toBe('Action 2');
    expect(visible.map((action) => action.provenance).every((kind) => kind !== 'MEASURED')).toBe(true);
  });

  it('labels disconnected KPIs as Connect and does not turn a missing value into zero', () => {
    const disconnected = dashboardKpis({ connected: false, metrics: { clicks: 0 } });
    const connected = dashboardKpis({ connected: true, metrics: { clicks: 4 } });

    expect(disconnected.map((card) => card.text)).toEqual(['Connect', 'Connect', 'Connect', 'Connect']);
    expect(disconnected.every((card) => card.value === null)).toBe(true);
    expect(connected.find((card) => card.id === 'clicks')?.text).toBe('4');
    expect(connected.find((card) => card.id === 'revenue')?.text).toBe('Not available');
    expect(connected.find((card) => card.id === 'revenue')?.value).toBeNull();
  });
});
