import { useEffect, useMemo, useState } from 'react';
import { t } from '../../i18n';
import { kindLabel, searchLoaded, type QuickSearchSource, type SearchHit, type SearchKind } from './quick-search';

interface Props {
  source: QuickSearchSource;
  onClose: () => void;
  onSelect: (hit: SearchHit) => void;
}

export function QuickSearchModal({ source, onClose, onSelect }: Props) {
  const [query, setQuery] = useState('');
  const hits = useMemo(() => searchLoaded(source, query), [source, query]);
  const groups = useMemo(() => groupHits(hits), [hits]);

  useEffect(() => {
    function onKey(event: KeyboardEvent) {
      if (event.key === 'Escape') {
        onClose();
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onClose]);

  return (
    <div
      className="qn-search-backdrop"
      onMouseDown={(event) => {
        if (event.target === event.currentTarget) {
          onClose();
        }
      }}
    >
      <div
        className="qn-search"
        id="qn-quick-search"
        role="dialog"
        aria-modal="true"
        aria-labelledby="qn-quick-search-title"
      >
        <h2 id="qn-quick-search-title">{t('Quick search')}</h2>
        <label>
          {t('Search loaded pages, posts, products, keywords, opportunities, and settings')}
          <input
            value={query}
            autoFocus
            onChange={(event) => setQuery(event.target.value)}
          />
        </label>
        {query.trim() === '' ? (
          <p>{t('Type a name. This search does not call a provider, crawl, or change stored data.')}</p>
        ) : null}
        {query.trim() !== '' && hits.length === 0 ? <p>{t('Nothing loaded matches that search.')}</p> : null}
        {groups.map((group) => (
          <section key={group.kind} aria-labelledby={`qn-search-${group.kind}`}>
            <h3 id={`qn-search-${group.kind}`}>{t(kindLabel(group.kind))}</h3>
            <ul>
              {group.hits.map((hit) => (
                <li key={hit.id}>
                  <button type="button" onClick={() => onSelect(hit)}>
                    {hit.title}
                  </button>
                  <p>{hit.detail}</p>
                </li>
              ))}
            </ul>
          </section>
        ))}
        <button type="button" onClick={onClose}>
          {t('Close')}
        </button>
      </div>
    </div>
  );
}

function groupHits(hits: SearchHit[]): Array<{ kind: SearchKind; hits: SearchHit[] }> {
  const kinds: SearchKind[] = ['page', 'post', 'product', 'keyword', 'opportunity', 'settings'];
  return kinds
    .map((kind) => ({ kind, hits: hits.filter((hit) => hit.kind === kind) }))
    .filter((group) => group.hits.length > 0);
}
