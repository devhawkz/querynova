import { visibleActions, normalizeSections, type TodayAction } from '../dashboard/sections';
import { readWorkspace } from '../commerce/workspace';
import { trackedKeywords } from '../rank/model';
import { SETTINGS_SECTIONS, type SettingsSectionId } from '../settings/model';
import { NAV_ITEMS, type ViewId } from '../../app/navigation';

export type SearchKind = 'page' | 'post' | 'product' | 'keyword' | 'opportunity' | 'settings';

export interface SearchTarget {
  view: ViewId;
  settingsSection?: SettingsSectionId;
  opportunityId?: number;
}

export interface SearchHit {
  id: string;
  kind: SearchKind;
  title: string;
  detail: string;
  target: SearchTarget;
}

export interface QuickSearchSource {
  documents?: unknown;
  product?: unknown;
  rankTracker?: unknown;
  actions?: TodayAction[];
  sections?: unknown;
}

const KIND_ORDER: SearchKind[] = ['page', 'post', 'product', 'keyword', 'opportunity', 'settings'];
const KIND_LIMIT = 8;

const KIND_LABELS: Record<SearchKind, string> = {
  page: 'Pages',
  post: 'Posts',
  product: 'Products',
  keyword: 'Keywords',
  opportunity: 'Opportunities',
  settings: 'Settings',
};

export function kindLabel(kind: SearchKind): string {
  return KIND_LABELS[kind];
}

export function isQuickSearchShortcut(event: { key: string; metaKey: boolean; ctrlKey: boolean; altKey: boolean }): boolean {
  if (event.altKey || event.key.toLowerCase() !== 'k') {
    return false;
  }
  return event.metaKey || event.ctrlKey;
}

export function searchLoaded(source: QuickSearchSource, query: string): SearchHit[] {
  const needle = query.trim().toLowerCase();
  if (needle === '') {
    return [];
  }
  const loaded = index(source);
  const hits: SearchHit[] = [];
  for (const kind of KIND_ORDER) {
    const matched = loaded.filter((hit) => hit.kind === kind && haystack(hit).includes(needle));
    hits.push(...matched.slice(0, KIND_LIMIT));
  }
  return hits;
}

function index(source: QuickSearchSource): SearchHit[] {
  return [
    ...screenPages(),
    ...documents(source.documents, 'pages', 'page'),
    ...documents(source.documents, 'posts', 'post'),
    ...products(source.product),
    ...keywords(source.rankTracker),
    ...opportunities(source.actions, source.sections),
    ...settings(),
  ];
}

function screenPages(): SearchHit[] {
  return NAV_ITEMS.map((item) => ({
    id: `page:screen:${item.id}`,
    kind: 'page' as const,
    title: item.label,
    detail: item.hint,
    target: { view: item.id },
  }));
}

function documents(input: unknown, key: 'pages' | 'posts', kind: 'page' | 'post'): SearchHit[] {
  const record = isRecord(input) ? input : {};
  if (!Array.isArray(record[key])) {
    return [];
  }
  const detail = kind === 'page'
    ? 'Stored page. Opening it switches to SEO and does not load or save the document.'
    : 'Stored post. Opening it switches to SEO and does not load or save the document.';
  const hits: SearchHit[] = [];
  for (const row of record[key]) {
    if (!isRecord(row)) {
      continue;
    }
    const id = typeof row.id === 'number' ? row.id : 0;
    const title = typeof row.title === 'string' ? row.title.trim() : '';
    if (id < 1 || title === '') {
      continue;
    }
    hits.push({
      id: `${kind}:${id}`,
      kind,
      title,
      detail,
      target: { view: 'seo' },
    });
  }
  return hits;
}

function products(input: unknown): SearchHit[] {
  const screen = isRecord(input) ? input : {};
  const workspace = readWorkspace(isRecord(screen.workspace) ? screen.workspace : screen, 'product');
  return workspace.entities
    .filter((entity) => entity.name.trim() !== '')
    .map((entity) => ({
      id: `product:${entity.id}`,
      kind: 'product' as const,
      title: entity.name,
      detail: entity.sku === '' ? 'Stored product' : entity.sku,
      target: { view: 'commerce' as const },
    }));
}

function keywords(input: unknown): SearchHit[] {
  return trackedKeywords(input).map((row) => ({
    id: `keyword:${row.id}:${row.keyword}`,
    kind: 'keyword' as const,
    title: row.keyword,
    detail: row.group === '' ? 'Stored keyword' : row.group,
    target: { view: 'rank' as const },
  }));
}

function opportunities(actions: TodayAction[] | undefined, sections: unknown): SearchHit[] {
  const fromActions = visibleActions(actions ?? []).map((action) => ({
    id: `opportunity:action:${action.id}`,
    kind: 'opportunity' as const,
    title: action.title,
    detail: action.rationale === '' ? 'Stored opportunity' : action.rationale,
    target: { view: 'dashboard' as const, opportunityId: action.id },
  }));
  const grouped = normalizeSections(sections);
  const fromSections: SearchHit[] = [];
  for (const [section, items] of Object.entries(grouped)) {
    for (const item of items) {
      fromSections.push({
        id: `opportunity:${section}:${item.id}`,
        kind: 'opportunity',
        title: item.title,
        detail: item.summary === '' ? 'Stored opportunity' : item.summary,
        target: { view: 'dashboard' },
      });
    }
  }
  return [...fromActions, ...fromSections];
}

function settings(): SearchHit[] {
  return SETTINGS_SECTIONS.map((section) => ({
    id: `settings:${section.id}`,
    kind: 'settings' as const,
    title: section.label,
    detail: 'Settings',
    target: { view: 'settings' as const, settingsSection: section.id },
  }));
}

function haystack(hit: SearchHit): string {
  return `${hit.title} ${hit.detail}`.toLowerCase();
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
