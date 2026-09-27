import { t } from '../../i18n';

export const SETUP_STEPS = [
  'Site type',
  'Business type',
  'WooCommerce',
  'Organization',
  'Search Console',
  'GA4',
  'SEO defaults',
  'Schema',
  'Sitemap',
  'Provider setup',
  'Crawler',
] as const;

export interface SetupModel {
  siteType: string | null;
  businessType: string | null;
  wooCommerce: boolean | null;
  organizationName: string | null;
  organizationUrl: string | null;
  organizationLogo: string | null;
  searchConsole: string | null;
  searchConsoleState: string;
  ga4: string | null;
  ga4State: string;
  titleSeparator: string | null;
  schemaEnabled: boolean | null;
  sitemapEnabled: boolean | null;
  crawlerEnabled: boolean | null;
  crawlerOrigin: string | null;
  providers: string;
  completed: boolean;
  connected: boolean;
  crawlStarted: boolean;
}

export function normalizeSetup(input: unknown): SetupModel {
  const record = typeof input === 'object' && input !== null ? (input as Record<string, unknown>) : {};
  const organization = typeof record.organization === 'object' && record.organization !== null ? (record.organization as Record<string, unknown>) : {};
  const search = typeof record.search_console === 'object' && record.search_console !== null ? (record.search_console as Record<string, unknown>) : {};
  const ga4 = typeof record.ga4 === 'object' && record.ga4 !== null ? (record.ga4 as Record<string, unknown>) : {};
  return {
    siteType: optionalText(record.site_type),
    businessType: optionalText(record.business_type),
    wooCommerce: typeof record.woocommerce === 'boolean' ? record.woocommerce : null,
    organizationName: optionalText(organization.name),
    organizationUrl: optionalText(organization.url),
    organizationLogo: optionalText(organization.logo),
    searchConsole: optionalText(search.property),
    searchConsoleState: 'not_configured',
    ga4: optionalText(ga4.property),
    ga4State: 'not_configured',
    titleSeparator: optionalText(record.title_separator),
    schemaEnabled: typeof record.schema_enabled === 'boolean' ? record.schema_enabled : null,
    sitemapEnabled: typeof record.sitemap_enabled === 'boolean' ? record.sitemap_enabled : null,
    crawlerEnabled: typeof record.crawler_enabled === 'boolean' ? record.crawler_enabled : null,
    crawlerOrigin: optionalText(record.crawler_origin),
    providers: 'not_configured',
    completed: record.completed === true,
    connected: false,
    crawlStarted: false,
  };
}

export function setupPayload(input: Record<string, unknown>): Record<string, unknown> {
  const payload: Record<string, unknown> = {};
  if (typeof input.site_type === 'string') {
    payload.site_type = input.site_type;
  }
  if (typeof input.business_type === 'string') {
    payload.business_type = input.business_type;
  }
  const search = propertyOnly(input.search_console);
  if (search !== undefined) {
    payload.search_console = search;
  }
  const ga4 = propertyOnly(input.ga4);
  if (ga4 !== undefined) {
    payload.ga4 = ga4;
  }
  if (typeof input.organization === 'object' && input.organization !== null) {
    const organization = input.organization as Record<string, unknown>;
    payload.organization = {
      name: typeof organization.name === 'string' ? organization.name : '',
      url: typeof organization.url === 'string' ? organization.url : '',
      logo: typeof organization.logo === 'string' ? organization.logo : '',
    };
  }
  if (typeof input.title_separator === 'string') {
    payload.title_separator = input.title_separator;
  }
  for (const flag of ['schema_enabled', 'sitemap_enabled', 'crawler_enabled'] as const) {
    if (typeof input[flag] === 'boolean' || input[flag] === null) {
      payload[flag] = input[flag];
    }
  }
  if (typeof input.crawler_origin === 'string') {
    payload.crawler_origin = input.crawler_origin;
  }
  return payload;
}

export function draftPayload(model: SetupModel): Record<string, unknown> {
  return setupPayload({
    site_type: model.siteType ?? '',
    business_type: model.businessType ?? '',
    organization: {
      name: model.organizationName ?? '',
      url: model.organizationUrl ?? '',
      logo: model.organizationLogo ?? '',
    },
    search_console: model.searchConsole ?? '',
    ga4: model.ga4 ?? '',
    title_separator: model.titleSeparator ?? '',
    schema_enabled: model.schemaEnabled,
    sitemap_enabled: model.sitemapEnabled,
    crawler_enabled: model.crawlerEnabled,
    crawler_origin: model.crawlerOrigin ?? '',
  });
}

function propertyOnly(value: unknown): { property: string } | undefined {
  if (typeof value === 'string') {
    return { property: value };
  }
  if (typeof value === 'object' && value !== null && typeof (value as Record<string, unknown>).property === 'string') {
    return { property: (value as Record<string, unknown>).property as string };
  }
  return undefined;
}

export function setupLine(value: string | boolean | null, empty = 'Nothing recorded.'): string {
  if (value === null || value === '') {
    return t(empty);
  }
  if (typeof value === 'boolean') {
    return value ? t('Yes') : t('No');
  }
  return value;
}

function optionalText(value: unknown): string | null {
  if (typeof value !== 'string') {
    return null;
  }
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}
