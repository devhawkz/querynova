import { metricLine } from '../dashboard/sections';
import { ADVANCED_ORDER, ADVANCED_TITLES, normalizeAdvanced } from './advanced';

interface Props {
  advanced: unknown;
}

export function AdvancedDetail({ advanced }: Props) {
  const sections = normalizeAdvanced(advanced);
  return (
    <section aria-labelledby="qn-advanced">
      <h1 id="qn-advanced">Advanced</h1>
      <p>Detail from stored rows. These lists are not the primary view.</p>
      {ADVANCED_ORDER.map((id) => {
        const items = sections[id];
        return (
          <section key={id} aria-labelledby={`qn-advanced-${id}`}>
            <h2 id={`qn-advanced-${id}`}>{ADVANCED_TITLES[id]}</h2>
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
