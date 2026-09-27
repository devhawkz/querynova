import { formatMetric, provenanceLabel, type MetricPayload, type ProvenanceKind } from '../../core/provenance';

export interface TodayAction {
  id: number;
  title: string;
  rationale: string;
  impact: string;
  confidence: string;
  provenance: ProvenanceKind;
}

export const SECTION_ORDER = ['revenue', 'search', 'technical', 'commerce', 'ai', 'recent'] as const;

export type SectionId = (typeof SECTION_ORDER)[number];

export const SECTION_TITLES: Record<SectionId, string> = {
  revenue: 'Revenue Opportunities',
  search: 'Search Opportunities',
  technical: 'Technical Risks',
  commerce: 'Commerce Risks',
  ai: 'AI Opportunities',
  recent: 'Recent Changes',
};

export interface DashboardItem {
  id: string;
  title: string;
  summary: string;
  metric: MetricPayload | null;
}

export type DashboardSections = Record<SectionId, DashboardItem[]>;

const SECTION_LIMIT = 5;
const ACTION_LIMIT = 10;

const kinds: ProvenanceKind[] = ['MEASURED', 'ATTRIBUTED', 'ESTIMATED', 'UNAVAILABLE'];

export function emptySections(): DashboardSections {
  return {
    revenue: [],
    search: [],
    technical: [],
    commerce: [],
    ai: [],
    recent: [],
  };
}

export function normalizeSections(input: unknown): DashboardSections {
  const sections = emptySections();
  if (typeof input !== 'object' || input === null) {
    return sections;
  }
  const record = input as Record<string, unknown>;
  for (const id of SECTION_ORDER) {
    const rows = record[id];
    if (!Array.isArray(rows)) {
      continue;
    }
    const items: DashboardItem[] = [];
    for (const row of rows) {
      const item = normalizeItem(row);
      if (item === null) {
        continue;
      }
      items.push(item);
      if (items.length === SECTION_LIMIT) {
        break;
      }
    }
    sections[id] = items;
  }
  return sections;
}

export function metricLine(metric: MetricPayload | null): string | null {
  if (metric === null) {
    return null;
  }
  if (metric.value === null && metric.kind === 'ESTIMATED') {
    return 'Estimated';
  }
  if (metric.value === null) {
    return 'Unavailable';
  }
  return formatMetric(metric);
}

export function primaryText(item: DashboardItem): string {
  const metric = metricLine(item.metric);
  const parts = [item.title, item.summary, metric ?? ''].filter((part) => part !== '');
  return parts.join(' ');
}

export function visibleActions(actions: TodayAction[]): TodayAction[] {
  const visible: TodayAction[] = [];
  for (const action of actions) {
    if (action.title.trim() === '') {
      continue;
    }
    visible.push(action);
    if (visible.length === ACTION_LIMIT) {
      break;
    }
  }
  return visible;
}

function normalizeItem(row: unknown): DashboardItem | null {
  if (typeof row !== 'object' || row === null) {
    return null;
  }
  const record = row as Record<string, unknown>;
  const title = typeof record.title === 'string' ? record.title.trim() : '';
  if (title === '') {
    return null;
  }
  const id = typeof record.id === 'string' || typeof record.id === 'number' ? String(record.id) : title;
  const summary = typeof record.summary === 'string' ? record.summary.trim() : '';
  return {
    id,
    title,
    summary,
    metric: normalizeMetric(record.metric),
  };
}

function normalizeMetric(value: unknown): MetricPayload | null {
  if (typeof value !== 'object' || value === null) {
    return null;
  }
  const record = value as Record<string, unknown>;
  const kind = kinds.find((candidate) => candidate === record.kind) ?? 'UNAVAILABLE';
  const amount = typeof record.value === 'number' && Number.isFinite(record.value) ? record.value : null;
  if (amount === null && kind === 'MEASURED') {
    return { value: null, kind: 'UNAVAILABLE', label: 'Unavailable' };
  }
  return {
    value: amount,
    kind,
    label: provenanceLabel(kind),
  };
}
