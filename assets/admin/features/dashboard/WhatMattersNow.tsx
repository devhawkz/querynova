import { provenanceLabel } from '../../core/provenance';
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
}

export function WhatMattersNow({ actions, sections, wooCommerceActive }: Props) {
  const visible = visibleActions(actions);
  const grouped = normalizeSections(sections);
  return (
    <section aria-labelledby="qn-what-matters">
      <h1 id="qn-what-matters">What Matters Now</h1>
      <p>{wooCommerceActive ? 'Organic revenue opportunities' : 'Organic growth opportunities'}</p>
      {visible.length === 0 ? (
        <p>No measured opportunities yet. Connect Search Console or run an on-site audit to rank the next actions.</p>
      ) : (
        <ol>
          {visible.map((action) => (
            <li key={action.id}>
              <h2>{action.title}</h2>
              {action.rationale !== '' ? <p>{action.rationale}</p> : null}
              <p>
                {action.impact !== '' ? `Impact ${action.impact}. ` : ''}
                {action.confidence !== '' ? `Confidence ${action.confidence}. ` : ''}
                {provenanceLabel(action.provenance)}
              </p>
            </li>
          ))}
        </ol>
      )}
      {SECTION_ORDER.map((id) => {
        const items = grouped[id];
        return (
          <section key={id} aria-labelledby={`qn-section-${id}`}>
            <h2 id={`qn-section-${id}`}>{SECTION_TITLES[id]}</h2>
            {items.length === 0 ? (
              <p>Nothing recorded.</p>
            ) : (
              <ul>
                {items.map((item) => {
                  const metric = metricLine(item.metric);
                  return (
                    <li key={item.id}>
                      <h3>{item.title}</h3>
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
