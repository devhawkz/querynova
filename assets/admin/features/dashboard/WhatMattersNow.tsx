import { provenanceLabel } from '../../core/provenance';
import { t } from '../../i18n';
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
}

export function WhatMattersNow({ actions, sections, wooCommerceActive, mode = 'simple' }: Props) {
  const visible = visibleActions(actions);
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
    </section>
  );
}

export type { TodayAction };
