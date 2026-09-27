import { describe, expect, it } from 'vitest';
import { normalizeProductScreen, PRODUCT_TAB_TITLES } from './model';

describe('product screen', () => {
  it('keeps every tab empty when no product is stored', () => {
    const screen = normalizeProductScreen(undefined);
    expect(screen.title).toBeNull();
    expect(screen.tabs.overview).toEqual([]);
    expect(screen.tabs.revenue).toEqual([]);
    expect(screen.tabs.recommendations).toEqual([]);
    expect(PRODUCT_TAB_TITLES.revenue).toBe('Revenue');
    expect(PRODUCT_TAB_TITLES.ai).toBe('AI');
  });

  it('does not label estimated revenue as measured', () => {
    const screen = normalizeProductScreen({
      title: 'LAGER-1',
      tabs: {
        revenue: [
          { id: 'estimated', title: 'Estimated revenue', summary: '4.50 · Estimated', metric: null },
          { id: 'measured', title: 'Measured revenue', summary: '10 · Measured', metric: null },
        ],
        overview: [{ id: 'price', title: 'Price', summary: '19.90 EUR · Measured', metric: null }],
      },
    });

    expect(screen.tabs.revenue[0]?.summary).toBe('4.50 · Estimated');
    expect(screen.tabs.revenue[0]?.summary).not.toContain('Measured');
    expect(screen.tabs.overview[0]?.summary).toContain('19.90');
    expect(screen.tabs.content).toEqual([]);
  });
});
