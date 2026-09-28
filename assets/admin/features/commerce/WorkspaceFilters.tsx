import { t } from '../../i18n';
import type { WorkspaceEntity } from './workspace';

interface Props {
  kind: 'product' | 'category';
  lead: string;
  recent: WorkspaceEntity[];
  search: string;
  issue: string;
  opportunity: string;
  onSearch: (value: string) => void;
  onIssue: (value: string) => void;
  onOpportunity: (value: string) => void;
}

export function WorkspaceFilters({ kind, lead, recent, search, issue, opportunity, onSearch, onIssue, onOpportunity }: Props) {
  const searchLabel = kind === 'category' ? 'Search categories' : 'Search products';
  return (
    <>
      <p>{t(lead)}</p>
      <label>
        {t(searchLabel)}
        <input value={search} onChange={(event) => onSearch(event.target.value)} />
      </label>
      <label>
        {t('Issue')}
        <select value={issue} onChange={(event) => onIssue(event.target.value)}>
          <option value="">{t('Any issue')}</option>
          <option value="missing_identifier">{t('Missing identifier')}</option>
        </select>
      </label>
      <label>
        {t('Opportunity')}
        <select value={opportunity} onChange={(event) => onOpportunity(event.target.value)}>
          <option value="">{t('Any opportunity')}</option>
          <option value="unavailable">{t('Unavailable')}</option>
        </select>
      </label>
      <h2>{kind === 'category' ? t('Recent categories') : t('Recent products')}</h2>
      {recent.length === 0 ? null : (
        <ul>
          {recent.map((entity) => (
            <li key={`${kind}-${entity.id}-${entity.sku}`}>
              <h3>{entity.name}</h3>
              {entity.sku !== '' ? <p>{entity.sku}</p> : null}
              <p>{entity.issues.length === 0 ? t('No issue on this row.') : entity.issues.join(', ')}</p>
              <p>{entity.opportunity === 'unavailable' ? t('Unavailable') : entity.opportunity}</p>
            </li>
          ))}
        </ul>
      )}
    </>
  );
}
