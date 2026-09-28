import { describe, expect, it } from 'vitest';
import {
  IDENTIFIER_KEYS,
  crawlTrap,
  filterWorkspace,
  optionalCount,
  presentWorkspace,
  readWorkspace,
  urlBasePlan,
  type WorkspaceEntity,
} from './workspace';

const rows: WorkspaceEntity[] = [
  { id: 4, name: 'Lager', sku: 'LAG', issues: ['missing_identifier'], opportunity: 'unavailable' },
  { id: 9, name: 'Pils', sku: 'PIL', issues: [], opportunity: 'unavailable' },
];

describe('commerce workspace', () => {
  it('leads with search and recent entities and never the empty product sentence', () => {
    const empty = presentWorkspace([], '', '', '', 'product');
    const filled = presentWorkspace(rows, '', '', '', 'product');
    const category = presentWorkspace([], '', '', '', 'category');

    expect(empty.lead).toBe('Search by name or SKU. Product URLs stay unchanged.');
    expect(filled.lead).toBe('Recent products. Product URLs stay unchanged.');
    expect(category.lead).toBe('Search by category name. Category URLs stay unchanged.');
    expect(empty.lead).not.toContain('No stored product');
    expect(filled.lead).not.toContain('No stored product');
    expect(category.lead).not.toContain('No stored category');
    expect(filled.recent.map((row) => row.id)).toEqual([9, 4]);
    expect(filled.rewritten).toBe(false);
  });

  it('filters by search, issue, and opportunity', () => {
    expect(filterWorkspace(rows, 'lag', '', '').map((row) => row.id)).toEqual([4]);
    expect(filterWorkspace(rows, '', 'missing_identifier', '').map((row) => row.id)).toEqual([4]);
    expect(filterWorkspace(rows, '', '', 'measured')).toEqual([]);
    expect(filterWorkspace(rows, '', '', 'unavailable')).toHaveLength(2);
  });

  it('reads a stored workspace without inventing a rewrite', () => {
    const workspace = readWorkspace({ entities: rows, rewritten: true }, 'category');
    expect(workspace.lead).toBe('Recent categories. Category URLs stay unchanged.');
    expect(workspace.rewritten).toBe(false);
    expect(workspace.total).toBe(2);
  });

  it('keeps identifier keys and leaves URL bases off until confirm', () => {
    expect(IDENTIFIER_KEYS).toEqual(['gtin', 'gtin8', 'gtin12', 'gtin13', 'gtin14', 'ean', 'upc', 'mpn', 'isbn']);
    const hidden = urlBasePlan('shop', 'product-category', false);
    expect(hidden.applied).toBe(false);
    expect(hidden.flushed).toBe(false);
    expect(hidden.stored).toBe(false);
    expect(hidden.note).toBe('Product and category URL bases stay unchanged until you confirm.');
    const stored = urlBasePlan('shop', 'product-category', true);
    expect(stored.applied).toBe(false);
    expect(stored.flushed).toBe(false);
    expect(stored.note).toContain('Live product and category URLs were not changed.');
  });

  it('does not turn a missing facet count into zero', () => {
    expect(optionalCount('')).toBeNull();
    expect(optionalCount('  ')).toBeNull();
    const missing = crawlTrap(3, optionalCount(''), optionalCount(''));
    expect(missing.status).toBe('unavailable');
    expect(missing.indexable).toBeNull();
    expect(missing.applied).toBe(false);
    expect(missing.note).toContain('did not crawl');
    const trap = crawlTrap(2, 50, 1);
    expect(trap.status).toBe('crawl_trap');
    expect(trap.applied).toBe(false);
    expect(trap.indexable).toBe(1);
  });
});
