import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { isQuickSearchShortcut, searchLoaded, type QuickSearchSource } from './quick-search';

const source: QuickSearchSource = {
  documents: {
    pages: [
      { id: 4, title: 'About the brewery' },
      { id: 0, title: 'Dropped page' },
      { id: 5, title: ' ' },
    ],
    posts: [{ id: 9, title: 'How lager is brewed' }],
    fetched: false,
    written: false,
  },
  product: {
    workspace: {
      entities: [
        { id: 3, name: 'Valjevo lager', sku: 'LAG-1', issues: [], opportunity: 'unavailable' },
        { id: 4, name: ' ', sku: 'EMPTY', issues: [], opportunity: 'unavailable' },
      ],
    },
  },
  rankTracker: {
    keywords: [{ id: 7, keyword: 'valjevo lager', group: 'beer', location: '', language: '', device: '', country: '' }],
  },
  actions: [
    {
      id: 11,
      title: 'Fix the homepage title',
      rationale: 'The stored title is empty.',
      impact: '',
      confidence: '',
      provenance: 'MEASURED',
    },
  ],
  sections: {
    search: [{ id: 'gap', title: 'Missing meta description', summary: 'Stored on the about page.' }],
  },
};

describe('quick search', () => {
  it('opens from command K and control K only', () => {
    expect(isQuickSearchShortcut({ key: 'k', metaKey: true, ctrlKey: false, altKey: false })).toBe(true);
    expect(isQuickSearchShortcut({ key: 'K', metaKey: false, ctrlKey: true, altKey: false })).toBe(true);
    expect(isQuickSearchShortcut({ key: 'k', metaKey: false, ctrlKey: false, altKey: false })).toBe(false);
    expect(isQuickSearchShortcut({ key: 'k', metaKey: true, ctrlKey: false, altKey: true })).toBe(false);
    expect(isQuickSearchShortcut({ key: 'f', metaKey: true, ctrlKey: false, altKey: false })).toBe(false);
  });

  it('returns nothing until there is a query', () => {
    expect(searchLoaded(source, '   ')).toEqual([]);
  });

  it('finds loaded pages, posts, products, keywords, opportunities, and settings', () => {
    const page = searchLoaded(source, 'about the brewery')[0];
    const screen = searchLoaded(source, 'rank tracking')[0];
    const post = searchLoaded(source, 'how lager')[0];
    const product = searchLoaded(source, 'lag-1')[0];
    const keyword = searchLoaded(source, 'valjevo lager').find((hit) => hit.kind === 'keyword');
    const action = searchLoaded(source, 'homepage title')[0];
    const section = searchLoaded(source, 'missing meta')[0];
    const settings = searchLoaded(source, 'breadcrumbs')[0];

    expect(page).toMatchObject({ kind: 'page', title: 'About the brewery', target: { view: 'seo' } });
    expect(screen).toMatchObject({ kind: 'page', title: 'Rank Tracking', target: { view: 'rank' } });
    expect(post).toMatchObject({ kind: 'post', target: { view: 'seo' } });
    expect(product).toMatchObject({ kind: 'product', title: 'Valjevo lager', target: { view: 'commerce' } });
    expect(keyword).toMatchObject({ kind: 'keyword', target: { view: 'rank' } });
    expect(action).toMatchObject({ kind: 'opportunity', target: { view: 'dashboard', opportunityId: 11 } });
    expect(section).toMatchObject({ kind: 'opportunity', target: { view: 'dashboard' } });
    expect(section?.target.opportunityId).toBeUndefined();
    expect(settings).toMatchObject({ kind: 'settings', target: { view: 'settings', settingsSection: 'breadcrumbs' } });
  });

  it('does not invent documents that were not loaded', () => {
    const titles = searchLoaded(source, 'dropped').map((hit) => hit.title);
    expect(titles).not.toContain('Dropped page');
    expect(searchLoaded({ documents: { posts: null, pages: null } }, 'lager')).toEqual([]);
    expect(searchLoaded(source, 'secret provider')).toEqual([]);
  });

  it('does not call the network or write from the search module', () => {
    const file = readFileSync(resolve('assets/admin/features/search/quick-search.ts'), 'utf8');
    expect(file).not.toContain('QueryNovaApi');
    expect(file).not.toContain('fetch(');
    expect(file).not.toContain('update_post_meta');
    expect(file).not.toContain('update_option');
  });
});
