export const AI_SECTIONS = [
  'overview',
  'prompts',
  'mentions',
  'citations',
  'competitors',
  'brands',
  'products',
  'sentiment',
  'traffic',
  'revenue',
  'crawlers',
] as const;

export type AiSection = (typeof AI_SECTIONS)[number];

export const AI_SECTION_TITLES: Record<AiSection, string> = {
  overview: 'Overview',
  prompts: 'Prompts',
  mentions: 'Mentions',
  citations: 'Citations',
  competitors: 'Competitors',
  brands: 'Brands',
  products: 'Products',
  sentiment: 'Sentiment',
  traffic: 'AI traffic',
  revenue: 'AI revenue',
  crawlers: 'Crawler access',
};

export const MCP_READS = [
  'site_status',
  'issues',
  'keywords',
  'rankings',
  'not_found',
  'redirects',
  'sitemap_status',
  'product_seo',
  'opportunities',
  'ai_visibility',
] as const;

export function providerStatus(providerId: string): {
  status: 'not_connected' | 'connected';
  called: false;
  note: string;
} {
  if (providerId.trim() === '') {
    return {
      status: 'not_connected',
      called: false,
      note: 'No AI provider is connected. Citations and mention share stay empty. QueryNova did not call a model.',
    };
  }
  return {
    status: 'connected',
    called: false,
    note: 'A provider id is configured. This request did not call the provider.',
  };
}

export function emptyObservation(): { status: 'UNAVAILABLE'; value: null; note: string } {
  return {
    status: 'UNAVAILABLE',
    value: null,
    note: 'Citations were not measured.',
  };
}

export function trackPrompt(fields: {
  prompt: string;
  locale: string;
  country: string;
  language: string;
  provider: string;
  frequency: string;
  tags: string[];
}): { stored: boolean; called: false; connected: false; note: string } {
  if (fields.prompt.trim() === '') {
    return {
      stored: false,
      called: false,
      connected: false,
      note: 'A prompt is required. Nothing was stored and QueryNova did not call a model.',
    };
  }
  return {
    stored: true,
    called: false,
    connected: false,
    note: 'The prompt is stored. QueryNova did not call a model. Storing a provider name does not connect one.',
  };
}

export function crawlerPlan(agent: string, decision: string, confirmed: boolean): {
  stored: boolean;
  robots_changed: false;
  note: string;
} {
  const choice = decision === 'block' ? 'block' : decision;
  if (!confirmed || agent.trim() === '' || (choice !== 'allow' && choice !== 'block')) {
    return {
      stored: false,
      robots_changed: false,
      note: 'Crawler access stays unchanged until you confirm.',
    };
  }
  return {
    stored: true,
    robots_changed: false,
    note: 'The preference is stored. robots.txt was not changed.',
  };
}

export function llmsPlan(enabled: boolean, body: string): {
  enabled: boolean;
  experimental: true;
  ranking_requirement: false;
  served: boolean;
  note: string;
} {
  const active = enabled && body.trim() !== '';
  return {
    enabled: active,
    experimental: true,
    ranking_requirement: false,
    served: active,
    note: 'llms.txt is experimental. It is not a ranking requirement. It is served only when enabled.',
  };
}

export function mcpWrite(permitted: boolean): { accepted: boolean; changed: false; published: false; note: string } {
  if (!permitted) {
    return {
      accepted: false,
      changed: false,
      published: false,
      note: 'Writes need an explicit permission. Nothing was changed.',
    };
  }
  return {
    accepted: true,
    changed: false,
    published: false,
    note: 'Permission was recorded. Live content was not changed.',
  };
}
