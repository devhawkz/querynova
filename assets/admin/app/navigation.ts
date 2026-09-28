export const NAV_ITEMS = [
  { id: 'dashboard', label: 'Dashboard', hint: 'What matters now, without a ranking score.' },
  { id: 'seo', label: 'SEO', hint: 'Titles, meta, and on-page checks for the content you open.' },
  { id: 'content', label: 'Content', hint: 'Coverage and drafts stay suggestions until you apply them.' },
  { id: 'keywords', label: 'Keywords', hint: 'Stored keyword rows. Missing volume stays unavailable.' },
  { id: 'analytics', label: 'Analytics', hint: 'Search Console and GA4 stay not connected until a provider is configured.' },
  { id: 'rank', label: 'Rank Tracking', hint: 'Stored rank history. A missing rank is not zero.' },
  { id: 'links', label: 'Links', hint: 'Redirects, 404s, and link suggestions. Suggestions are not inserted.' },
  { id: 'commerce', label: 'WooCommerce', hint: 'The latest stored product and category. Commerce stays quiet when WooCommerce is off.' },
  { id: 'schema', label: 'Schema', hint: 'Connected schema rules. Empty values are omitted.' },
  { id: 'local', label: 'Local SEO', hint: 'Local pages are off until the module is enabled.' },
  { id: 'ai', label: 'AI Visibility', hint: 'Observations only. Missing citations stay empty.' },
  { id: 'reports', label: 'Reports', hint: 'CSV and JSON from stored metrics. PDF is not generated.' },
  { id: 'settings', label: 'Settings & Tools', hint: 'Setup, diagnostics, and the installed build.' },
] as const;

export type ViewId = (typeof NAV_ITEMS)[number]['id'];

export type AdminMode = 'simple' | 'advanced';

export function isViewId(value: string): value is ViewId {
  return NAV_ITEMS.some((item) => item.id === value);
}

export function navItem(id: ViewId): (typeof NAV_ITEMS)[number] {
  const item = NAV_ITEMS.find((entry) => entry.id === id);
  if (!item) {
    return NAV_ITEMS[0];
  }
  return item;
}

export function environmentBadge(environment: string): string {
  const name = environment.trim().toLowerCase();
  if (name === '') {
    return '';
  }
  return `WordPress ${name.charAt(0).toUpperCase()}${name.slice(1)}`;
}
