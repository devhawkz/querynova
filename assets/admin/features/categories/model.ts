import { normalizeItemList, type DashboardItem } from '../dashboard/sections';

export const CATEGORY_TABS = [
  'overview',
  'keywords',
  'revenue',
  'products',
  'content',
  'serp',
  'filters',
  'links',
  'competitors',
  'ai',
  'recommendations',
] as const;

export type CategoryTab = (typeof CATEGORY_TABS)[number];

export const CATEGORY_TAB_TITLES: Record<CategoryTab, string> = {
  overview: 'Overview',
  keywords: 'Keywords',
  revenue: 'Revenue',
  products: 'Products',
  content: 'Content',
  serp: 'SERP',
  filters: 'Filters',
  links: 'Links',
  competitors: 'Competitors',
  ai: 'AI',
  recommendations: 'Recommendations',
};

export interface CategoryScreenModel {
  title: string | null;
  tabs: Record<CategoryTab, DashboardItem[]>;
}

export function emptyCategoryScreen(): CategoryScreenModel {
  return {
    title: null,
    tabs: {
      overview: [],
      keywords: [],
      revenue: [],
      products: [],
      content: [],
      serp: [],
      filters: [],
      links: [],
      competitors: [],
      ai: [],
      recommendations: [],
    },
  };
}

export function normalizeCategoryScreen(input: unknown): CategoryScreenModel {
  const screen = emptyCategoryScreen();
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
  for (const tab of CATEGORY_TABS) {
    screen.tabs[tab] = normalizeItemList(rows[tab], 8);
  }
  return screen;
}
