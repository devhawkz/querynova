import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { provenanceLabel } from '../../core/provenance';
import { t } from '../../i18n';
import { drawerDetail, drawerResult, type DrawerAction, type DrawerDetail } from './drawer';
import {
  metricLine,
  normalizeSections,
  SECTION_ORDER,
  SECTION_TITLES,
  visibleActions,
  type TodayAction,
} from './sections';

interface Props {
  actions: TodayAction[];
  sections: unknown;
  wooCommerceActive: boolean;
  mode?: 'simple' | 'advanced';
  restUrl?: string;
  nonce?: string;
}

export function WhatMattersNow({ actions, sections, wooCommerceActive, mode = 'simple', restUrl = '', nonce = '' }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [selected, setSelected] = useState<TodayAction | null>(null);
  const [note, setNote] = useState('');
  const visible = visibleActions(actions);
  const detail = selected === null ? null : drawerDetail(selected);
  const grouped = normalizeSections(sections);
  const sectionsToShow = mode === 'advanced' ? SECTION_ORDER : SECTION_ORDER.filter((id) => id !== 'ai' && id !== 'recent');
  return (
    <section aria-labelledby="qn-what-matters">
      <h2 id="qn-what-matters">{t('What Matters Now')}</h2>
      <p>{wooCommerceActive ? t('Organic revenue opportunities') : t('Organic growth opportunities')}</p>
      {visible.length === 0 ? (
        <p>{t('No measured opportunities yet. Connect Search Console or run an on-site audit to rank the next actions.')}</p>
      ) : (
        <ol>
          {visible.map((action) => (
            <li key={action.id}>
              <h2>{action.title}</h2>
              {action.rationale !== '' ? <p>{action.rationale}</p> : null}
              <p>
                {action.impact !== '' ? `${t('Impact')} ${action.impact}. ` : ''}
                {action.confidence !== '' ? `${t('Confidence')} ${action.confidence}. ` : ''}
                {provenanceLabel(action.provenance)}
              </p>
              <button type="button" onClick={() => { setSelected(action); setNote(''); }}>{t('Open detail')}</button>
            </li>
          ))}
        </ol>
      )}
      {sectionsToShow.map((id) => {
        const items = grouped[id];
        return (
          <section key={id} aria-labelledby={`qn-section-${id}`}>
            <h3 id={`qn-section-${id}`}>{t(SECTION_TITLES[id])}</h3>
            {items.length === 0 ? (
              <p>{t('No stored rows in this section. Connect a provider or run an audit before treating a gap as zero.')}</p>
            ) : (
              <ul>
                {items.map((item) => {
                  const metric = metricLine(item.metric);
                  return (
                    <li key={item.id}>
                      <h4>{item.title}</h4>
                      {item.summary !== '' ? <p>{item.summary}</p> : null}
                      {metric !== null ? <p>{metric}</p> : null}
                    </li>
                  );
                })}
              </ul>
            )}
          </section>
        );
      })}
      {detail !== null && selected !== null ? (
        <OpportunityDetail
          detail={detail}
          onClose={() => setSelected(null)}
          onAct={(action) => void recordAction(api, selected.id, action, restUrl !== '' && nonce !== '', setNote)}
          note={note}
        />
      ) : null}
    </section>
  );
}

function OpportunityDetail({ detail, onClose, onAct, note }: {
  detail: DrawerDetail;
  onClose: () => void;
  onAct: (action: DrawerAction) => void;
  note: string;
}) {
  return (
    <section aria-labelledby="qn-opportunity-drawer">
      <h3 id="qn-opportunity-drawer">{t('Opportunity detail')}</h3>
      <dl>
        <dt>{t('Why this matters')}</dt>
        <dd>{field(detail.why)}</dd>
        <dt>{t('Evidence')}</dt>
        <dd>{field(detail.evidence)}</dd>
        <dt>{t('Data sources')}</dt>
        <dd>{detail.sources.length === 0 ? t('No data source is stored.') : detail.sources.join(', ')}</dd>
        <dt>{t('Suggested action')}</dt>
        <dd>{field(detail.action)}</dd>
        <dt>{t('Expected KPI')}</dt>
        <dd>{field(detail.kpi)}</dd>
        <dt>{t('Confidence')}</dt>
        <dd>{field(detail.confidence)}</dd>
        <dt>{t('Risks')}</dt>
        <dd>{field(detail.risks)}</dd>
        <dt>{t('Affected URL')}</dt>
        <dd>{field(detail.url)}</dd>
      </dl>
      <button type="button" onClick={() => onAct('accept')}>{t('Accept')}</button>
      <button type="button" onClick={() => onAct('dismiss')}>{t('Dismiss')}</button>
      <button type="button" onClick={() => onAct('applied')}>{t('Mark Applied')}</button>
      <button type="button" onClick={() => onAct('experiment')}>{t('Create Experiment')}</button>
      <button type="button" onClick={onClose}>{t('Close detail')}</button>
      {note !== '' ? <p role="status">{note}</p> : null}
    </section>
  );
}

function field(value: string | null): string {
  return value === null ? t('Nothing stored for this field.') : value;
}

async function recordAction(api: QueryNovaApi, id: number, action: DrawerAction, ready: boolean, setNote: (note: string) => void): Promise<void> {
  const local = drawerResult(true);
  if (!ready) {
    setNote(t(local.note));
    return;
  }
  try {
    const body = await api.post<Record<string, unknown>>('/outcomes/drawer', { id, action, confirmed: true, item: {} });
    const result = body.result;
    const record = typeof result === 'object' && result !== null ? result as Record<string, unknown> : {};
    setNote(typeof record.note === 'string' ? record.note : t(local.note));
  } catch {
    setNote(t(local.note));
  }
}

export type { TodayAction };
