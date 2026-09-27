export interface TodayAction {
  id: number;
  title: string;
  rationale: string;
  impact: string;
  confidence: string;
  provenance: 'MEASURED' | 'ATTRIBUTED' | 'ESTIMATED' | 'UNAVAILABLE';
}

interface Props {
  actions: TodayAction[];
  wooCommerceActive: boolean;
}

export function WhatMattersNow({ actions, wooCommerceActive }: Props) {
  const visible = actions.slice(0, 10);
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
              <p>{action.rationale}</p>
              <p>
                Impact {action.impact}. Confidence {action.confidence}. {action.provenance}
              </p>
            </li>
          ))}
        </ol>
      )}
    </section>
  );
}
