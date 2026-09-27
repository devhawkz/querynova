import { describe, expect, it } from 'vitest';
import { CATEGORY_TAB_TITLES, normalizeCategoryScreen } from './model';

describe('category screen', () => {
  it('keeps every tab empty when no category is stored', () => {
    const screen = normalizeCategoryScreen(undefined);
    expect(screen.title).toBeNull();
    expect(screen.tabs.products).toEqual([]);
    expect(screen.tabs.filters).toEqual([]);
    expect(screen.tabs.serp).toEqual([]);
    expect(CATEGORY_TAB_TITLES.serp).toBe('SERP');
    expect(CATEGORY_TAB_TITLES.products).toBe('Products');
  });

  it('does not turn a missing product count into zero or label an estimate as measured', () => {
    const screen = normalizeCategoryScreen({
      title: 'Category 15',
      tabs: {
        revenue: [{ id: 'estimated', title: 'Estimated revenue', summary: '4.50 · Estimated', metric: null }],
        products: [],
      },
    });

    expect(screen.tabs.revenue[0]?.summary).not.toContain('Measured');
    expect(screen.tabs.products).toEqual([]);
    expect(JSON.stringify(screen)).not.toContain('"value":0');
  });
});
