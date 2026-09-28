export const DRAWER_ACTIONS = ['accept', 'dismiss', 'applied', 'experiment'] as const;

export type DrawerAction = (typeof DRAWER_ACTIONS)[number];

export interface DrawerDetail {
  why: string | null;
  evidence: string | null;
  sources: string[];
  action: string | null;
  kpi: string | null;
  confidence: string | null;
  risks: string | null;
  url: string | null;
  page_changed: false;
}

export function drawerDetail(item: {
  title?: string;
  rationale?: string;
  impact?: string;
  confidence?: string;
  provenance?: string;
}): DrawerDetail {
  return {
    why: blank(item.rationale),
    evidence: null,
    sources: blank(item.provenance) === null ? [] : [String(item.provenance)],
    action: blank(item.title),
    kpi: blank(item.impact),
    confidence: blank(item.confidence),
    risks: null,
    url: null,
    page_changed: false,
  };
}

export function drawerResult(confirmed: boolean): { stored: boolean; page_changed: false; published: false; note: string } {
  if (!confirmed) {
    return {
      stored: false,
      page_changed: false,
      published: false,
      note: 'The live page stays unchanged until you confirm Accept, Dismiss, Mark Applied, or Create Experiment.',
    };
  }
  return {
    stored: true,
    page_changed: false,
    published: false,
    note: 'The recommendation status was recorded. The live page was not changed.',
  };
}

function blank(value: string | undefined): string | null {
  const text = (value ?? '').trim();
  return text === '' ? null : text;
}
