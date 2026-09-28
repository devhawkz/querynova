export const DRAFT_KINDS = [
  'outline',
  'titles',
  'metas',
  'faq',
  'rewrite',
  'product_copy',
  'category_copy',
  'alt',
  'social',
  'internal_links',
] as const;

export type DraftKind = (typeof DRAFT_KINDS)[number];

export const DRAFT_LABELS: Record<DraftKind, string> = {
  outline: 'Outline',
  titles: 'Titles',
  metas: 'Metas',
  faq: 'FAQ',
  rewrite: 'Rewrite',
  product_copy: 'Product copy',
  category_copy: 'Category copy',
  alt: 'ALT',
  social: 'Social',
  internal_links: 'Internal links',
};

export const AUTOMATION_RULES = ['internal_links', 'keyword_links'] as const;

export function contentLead(hasDocument: boolean): string {
  return hasDocument
    ? 'Observations from the supplied document. This is not a content score.'
    : 'Supply a document. QueryNova does not fetch the URL.';
}

export function modelConnection(modelId: string): {
  status: 'not_connected' | 'connected';
  model: string;
  called: false;
  note: string;
} {
  const model = modelId.trim();
  if (model === '') {
    return {
      status: 'not_connected',
      model: '',
      called: false,
      note: 'No content model is connected. Draft actions stay unavailable until a model is connected. QueryNova did not call a model.',
    };
  }
  return {
    status: 'connected',
    model,
    called: false,
    note: 'A model id is stored. QueryNova does not call the model from this screen.',
  };
}

export function generateDraft(modelId: string, kind: string, text: string): {
  status: 'not_connected' | 'not_called';
  stored: boolean;
  published: false;
  page_changed: false;
  called: false;
  note: string;
} {
  const connection = modelConnection(modelId);
  if (connection.status !== 'connected') {
    return {
      status: 'not_connected',
      stored: false,
      published: false,
      page_changed: false,
      called: false,
      note: 'No content model is connected. This draft was not created. QueryNova did not call a model.',
    };
  }
  const stored = text.trim() !== '' && (DRAFT_KINDS as readonly string[]).includes(kind);
  return {
    status: 'not_called',
    stored,
    published: false,
    page_changed: false,
    called: false,
    note: stored
      ? 'The supplied text was stored as a draft. QueryNova did not call the model. It was not published and the live page was not changed.'
      : 'No source text was supplied, so no draft was stored. QueryNova did not call the model.',
  };
}

export function storeDraft(kind: string, text: string): {
  stored: boolean;
  published: false;
  page_changed: false;
  called: false;
  note: string;
} {
  if (!(DRAFT_KINDS as readonly string[]).includes(kind)) {
    return {
      stored: false,
      published: false,
      page_changed: false,
      called: false,
      note: 'That draft type is not available. Nothing was stored and the live page was not changed.',
    };
  }
  if (text.trim() === '') {
    return {
      stored: false,
      published: false,
      page_changed: false,
      called: false,
      note: 'A draft needs text. Nothing was stored and the live page was not changed.',
    };
  }
  return {
    stored: true,
    published: false,
    page_changed: false,
    called: false,
    note: 'The draft is stored. It was not published and the live page was not changed. QueryNova did not call a model.',
  };
}

export function automationPlan(rule: string, enabled: boolean): {
  internal_links: 'Suggest Only' | 'Automated';
  keyword_links: 'Suggest Only' | 'Automated';
  inserted: false;
  page_changed: false;
  note: string;
} {
  const chosen = enabled && (rule === 'internal_links' || rule === 'keyword_links') ? rule : '';
  return {
    internal_links: chosen === 'internal_links' ? 'Automated' : 'Suggest Only',
    keyword_links: chosen === 'keyword_links' ? 'Automated' : 'Suggest Only',
    inserted: false,
    page_changed: false,
    note: chosen === ''
      ? 'Suggestions stay Suggest Only. Nothing is inserted.'
      : 'Automation is on for one rule. No link was inserted and the live page was not changed.',
  };
}

export function gapTopics(coverage: Array<{ topic: string; classification: string | null; on_our_page: boolean | null }>): {
  status: 'UNAVAILABLE' | 'MEASURED';
  topics: string[] | null;
  note: string;
} {
  if (coverage.length === 0) {
    return {
      status: 'UNAVAILABLE',
      topics: null,
      note: 'A gap needs supplied topics. QueryNova did not crawl.',
    };
  }
  const measured = coverage.some((row) => row.classification !== null);
  if (!measured) {
    return {
      status: 'UNAVAILABLE',
      topics: null,
      note: 'Gap needs supplied top results. QueryNova did not crawl.',
    };
  }
  return {
    status: 'MEASURED',
    topics: coverage.filter((row) => row.classification === 'important' && row.on_our_page === false).map((row) => row.topic),
    note: 'Topics marked important that are absent from the supplied document. This is not a score.',
  };
}
