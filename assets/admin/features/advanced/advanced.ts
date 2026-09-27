import { normalizeItemList, type DashboardItem } from '../dashboard/sections';

export const ADVANCED_ORDER = [
  'raw',
  'serps',
  'keywords',
  'backlinks',
  'methodologies',
  'providers',
  'confidence',
  'history',
] as const;

export type AdvancedId = (typeof ADVANCED_ORDER)[number];

export const ADVANCED_TITLES: Record<AdvancedId, string> = {
  raw: 'Raw Data',
  serps: 'SERPs',
  keywords: 'Keywords',
  backlinks: 'Backlinks',
  methodologies: 'Methodologies',
  providers: 'Providers',
  confidence: 'Confidence',
  history: 'Historical Data',
};

export type AdvancedSections = Record<AdvancedId, DashboardItem[]>;

const ADVANCED_LIMIT = 20;

export function emptyAdvanced(): AdvancedSections {
  return {
    raw: [],
    serps: [],
    keywords: [],
    backlinks: [],
    methodologies: [],
    providers: [],
    confidence: [],
    history: [],
  };
}

export function normalizeAdvanced(input: unknown): AdvancedSections {
  const sections = emptyAdvanced();
  if (typeof input !== 'object' || input === null) {
    return sections;
  }
  const record = input as Record<string, unknown>;
  for (const id of ADVANCED_ORDER) {
    sections[id] = normalizeItemList(record[id], ADVANCED_LIMIT);
  }
  return sections;
}
