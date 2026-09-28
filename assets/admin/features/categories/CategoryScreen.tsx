import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { WorkspaceFilters } from '../commerce/WorkspaceFilters';
import { crawlTrap, optionalCount, presentWorkspace, readWorkspace } from '../commerce/workspace';
import { CATEGORY_TABS, CATEGORY_TAB_TITLES, normalizeCategoryScreen, type CategoryTab } from './model';

interface Props {
  category: unknown;
  restUrl?: string;
  nonce?: string;
}

export function CategoryScreen({ category, restUrl = '', nonce = '' }: Props) {
  const screen = normalizeCategoryScreen(category);
  const stored = readWorkspace(recordField(category, 'workspace'), 'category');
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const ready = restUrl !== '' && nonce !== '';
  const [tab, setTab] = useState<CategoryTab>('overview');
  const [search, setSearch] = useState('');
  const [issue, setIssue] = useState('');
  const [opportunity, setOpportunity] = useState('');
  const [facetCount, setFacetCount] = useState('');
  const [combinationCount, setCombinationCount] = useState('');
  const [indexableCount, setIndexableCount] = useState('');
  const [facetNote, setFacetNote] = useState('');
  const [indexableLabel, setIndexableLabel] = useState('');
  const workspace = presentWorkspace(stored.entities, search, issue, opportunity, 'category');
  const items = screen.tabs[tab];

  async function reviewFacets() {
    const facets = optionalCount(facetCount) ?? 0;
    const combinations = optionalCount(combinationCount);
    const indexable = optionalCount(indexableCount);
    const local = crawlTrap(facets, combinations, indexable);
    if (!ready) {
      setFacetNote(t(local.note));
      setIndexableLabel(local.indexable === null ? t('Indexable count was not measured.') : String(local.indexable));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/commerce/facets', {
        facet_count: facets,
        combination_count: combinations,
        indexable_count: indexable,
      });
      const note = typeof body.note === 'string' ? body.note : local.note;
      const measured = typeof body.indexable === 'number' ? String(body.indexable) : t('Indexable count was not measured.');
      setFacetNote(note);
      setIndexableLabel(measured);
    } catch {
      setFacetNote(t(local.note));
      setIndexableLabel(local.indexable === null ? t('Indexable count was not measured.') : String(local.indexable));
    }
  }

  return (
    <section aria-labelledby="qn-category">
      <h1 id="qn-category">{screen.title ?? t('Category')}</h1>
      <WorkspaceFilters
        kind="category"
        lead={workspace.lead}
        recent={workspace.recent}
        search={search}
        issue={issue}
        opportunity={opportunity}
        onSearch={setSearch}
        onIssue={setIssue}
        onOpportunity={setOpportunity}
      />
      <h2>{t('Facet combinations')}</h2>
      <p>{t('This review does not crawl facet URLs and does not change indexability.')}</p>
      <label>
        {t('Facet count')}
        <input value={facetCount} onChange={(event) => setFacetCount(event.target.value)} />
      </label>
      <label>
        {t('Combination count')}
        <input value={combinationCount} onChange={(event) => setCombinationCount(event.target.value)} />
      </label>
      <label>
        {t('Indexable count')}
        <input value={indexableCount} onChange={(event) => setIndexableCount(event.target.value)} />
      </label>
      <button type="button" onClick={() => void reviewFacets()}>{t('Review facet combinations')}</button>
      {facetNote !== '' ? <p role="status">{facetNote}</p> : null}
      {indexableLabel !== '' ? <p>{indexableLabel}</p> : null}
      <nav aria-label={t('Category')}>
        {CATEGORY_TABS.map((id) => (
          <button key={id} type="button" aria-current={tab === id ? 'page' : undefined} onClick={() => setTab(id)}>
            {t(CATEGORY_TAB_TITLES[id])}
          </button>
        ))}
      </nav>
      <section aria-labelledby={`qn-category-${tab}`}>
        <h2 id={`qn-category-${tab}`}>{t(CATEGORY_TAB_TITLES[tab])}</h2>
        {items.length === 0 ? (
          <p>{t('Nothing recorded.')}</p>
        ) : (
          <ul>
            {items.map((item) => (
              <li key={item.id}>
                <h3>{item.title}</h3>
                {item.summary !== '' ? <p>{item.summary}</p> : null}
              </li>
            ))}
          </ul>
        )}
      </section>
    </section>
  );
}

function recordField(input: unknown, key: string): unknown {
  if (typeof input !== 'object' || input === null) {
    return null;
  }
  return (input as Record<string, unknown>)[key];
}
