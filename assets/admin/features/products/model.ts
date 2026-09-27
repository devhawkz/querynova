import { normalizeItemList, type DashboardItem } from '../dashboard/sections';

export const PRODUCT_TABS = [
  'overview',
  'search',
  'keywords',
  'revenue',
  'conversion',
  'content',
  'schema',
  'links',
  'competitors',
  'ai',
  'recommendations',
] as const;

export type ProductTab = (typeof PRODUCT_TABS)[number];

export const PRODUCT_TAB_TITLES: Record<ProductTab, string> = {
  overview: 'Overview',
  search: 'Search',
  keywords: 'Keywords',
  revenue: 'Revenue',
  conversion: 'Conversion',
  content: 'Content',
  schema: 'Schema',
  links: 'Links',
  competitors: 'Competitors',
  ai: 'AI',
  recommendations: 'Recommendations',
};

export interface ProductScreenModel {
  title: string | null;
  tabs: Record<ProductTab, DashboardItem[]>;
}

export function emptyProductScreen(): ProductScreenModel {
  return {
    title: null,
    tabs: {
      overview: [],
      search: [],
      keywords: [],
      revenue: [],
      conversion: [],
      content: [],
      schema: [],
      links: [],
      competitors: [],
      ai: [],
      recommendations: [],
    },
  };
}

export function normalizeProductScreen(input: unknown): ProductScreenModel {
  const screen = emptyProductScreen();
  if (typeof input !== 'object' || input === null) {
    return screen;
  }
  const record = input as Record<string, unknown>;
  screen.title = typeof record.title === 'string' && record.title.trim() !== '' ? record.title.trim() : null;
  const tabs = record.tabs;
  if (typeof tabs !== 'object' || tabs === null) {
    return screen;
  }
  const rows = tabs as Record<string, unknown>;
  for (const tab of PRODUCT_TABS) {
    screen.tabs[tab] = normalizeItemList(rows[tab], 8);
  }
  return screen;
}
