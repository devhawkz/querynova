export interface WorkspaceEntity {
  id: number;
  name: string;
  sku: string;
  issues: string[];
  opportunity: string;
}

export interface WorkspaceModel {
  lead: string;
  entities: WorkspaceEntity[];
  recent: WorkspaceEntity[];
  total: number;
  rewritten: false;
}

export const IDENTIFIER_KEYS = ['gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'ean', 'upc', 'mpn', 'isbn'] as const;

export type IdentifierKey = (typeof IDENTIFIER_KEYS)[number];

export function workspaceLead(kind: 'product' | 'category', empty: boolean): string {
  if (kind === 'category') {
    return empty
      ? 'Search by category name. Category URLs stay unchanged.'
      : 'Recent categories. Category URLs stay unchanged.';
  }
  return empty
    ? 'Search by name or SKU. Product URLs stay unchanged.'
    : 'Recent products. Product URLs stay unchanged.';
}

export function filterWorkspace(
  rows: WorkspaceEntity[],
  search: string,
  issue: string,
  opportunity: string,
): WorkspaceEntity[] {
  const needle = search.trim().toLowerCase();
  const issueCode = issue.trim();
  const opportunityCode = opportunity.trim();
  return rows
    .filter((row) => {
      const haystack = `${row.name} ${row.sku} ${row.id}`.toLowerCase();
      if (needle !== '' && !haystack.includes(needle)) {
        return false;
      }
      if (issueCode !== '' && !row.issues.includes(issueCode)) {
        return false;
      }
      if (opportunityCode !== '' && row.opportunity !== opportunityCode) {
        return false;
      }
      return true;
    })
    .sort((left, right) => right.id - left.id);
}

export function presentWorkspace(
  rows: WorkspaceEntity[],
  search: string,
  issue: string,
  opportunity: string,
  kind: 'product' | 'category',
): WorkspaceModel {
  const entities = filterWorkspace(rows, search, issue, opportunity);
  return {
    lead: workspaceLead(kind, entities.length === 0),
    entities,
    recent: entities.slice(0, 8),
    total: entities.length,
    rewritten: false,
  };
}

export function readWorkspace(input: unknown, kind: 'product' | 'category'): WorkspaceModel {
  const empty = presentWorkspace([], '', '', '', kind);
  if (typeof input !== 'object' || input === null) {
    return empty;
  }
  const record = input as Record<string, unknown>;
  const entities = Array.isArray(record.entities) ? record.entities.map(readEntity).filter((row): row is WorkspaceEntity => row !== null) : [];
  return presentWorkspace(entities, '', '', '', kind);
}

export function emptyIdentifiers(): Record<IdentifierKey, string> {
  return {
    gtin: '',
    gtin8: '',
    gtin12: '',
    gtin13: '',
    gtin14: '',
    ean: '',
    upc: '',
    mpn: '',
    isbn: '',
  };
}

export function urlBasePlan(productBase: string, categoryBase: string, confirmed: boolean): {
  applied: false;
  flushed: false;
  stored: boolean;
  product_base: string;
  category_base: string;
  note: string;
} {
  return {
    applied: false,
    flushed: false,
    stored: confirmed,
    product_base: productBase.trim(),
    category_base: categoryBase.trim(),
    note: confirmed
      ? 'The request is stored. Live product and category URLs were not changed.'
      : 'Product and category URL bases stay unchanged until you confirm.',
  };
}

export function optionalCount(value: string): number | null {
  const trimmed = value.trim();
  if (trimmed === '' || !/^-?\d+$/.test(trimmed)) {
    return null;
  }
  return Number(trimmed);
}

export function crawlTrap(facetCount: number, combinationCount: number | null, indexableCount: number | null): {
  status: 'unavailable' | 'crawl_trap' | 'clear';
  indexable: number | null;
  applied: false;
  note: string;
} {
  if (combinationCount === null) {
    return {
      status: 'unavailable',
      indexable: indexableCount,
      applied: false,
      note: 'Combination count was not measured. QueryNova did not crawl facet URLs.',
    };
  }
  const trap = combinationCount >= 50 || (facetCount > 0 && combinationCount > facetCount * 8);
  return {
    status: trap ? 'crawl_trap' : 'clear',
    indexable: indexableCount,
    applied: false,
    note: trap
      ? 'These facet combinations can trap a crawler. Nothing was noindexed and no URL was rewritten.'
      : 'The supplied facet counts do not show a crawl trap. Nothing was changed.',
  };
}

function readEntity(input: unknown): WorkspaceEntity | null {
  if (typeof input !== 'object' || input === null) {
    return null;
  }
  const record = input as Record<string, unknown>;
  const id = typeof record.id === 'number' ? record.id : Number(record.id);
  if (!Number.isFinite(id)) {
    return null;
  }
  const issues = Array.isArray(record.issues) ? record.issues.filter((issue): issue is string => typeof issue === 'string') : [];
  return {
    id,
    name: typeof record.name === 'string' ? record.name : '',
    sku: typeof record.sku === 'string' ? record.sku : '',
    issues,
    opportunity: typeof record.opportunity === 'string' ? record.opportunity : 'unavailable',
  };
}
