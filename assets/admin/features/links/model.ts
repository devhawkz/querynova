export const ROBOTS_NOTE = 'This editor stores robots.txt in QueryNova. A physical file is not changed unless you allow it.';

export const SITEMAP_CHANNELS = [
  ['post', 'Posts'],
  ['page', 'Pages'],
  ['cpt', 'Custom post types'],
  ['taxonomy', 'Taxonomies'],
  ['product', 'Products'],
  ['brand', 'Brands'],
  ['image', 'Images'],
  ['author', 'Authors'],
  ['html', 'HTML sitemap'],
  ['news', 'News'],
  ['video', 'Video'],
  ['kml', 'KML'],
] as const;

export interface ChannelFlags {
  post: boolean;
  page: boolean;
  cpt: boolean;
  taxonomy: boolean;
  product: boolean;
  brand: boolean;
  image: boolean;
  author: boolean;
  html: boolean;
  news: boolean;
  video: boolean;
  kml: boolean;
}

export function defaultChannels(): ChannelFlags {
  return {
    post: true,
    page: true,
    cpt: true,
    taxonomy: true,
    product: true,
    brand: true,
    image: true,
    author: false,
    html: false,
    news: false,
    video: true,
    kml: false,
  };
}

export function channelFlags(input: unknown, newsConfigured: boolean, localEnabled: boolean): ChannelFlags {
  const flags = defaultChannels();
  const record = isRecord(input) ? input : {};
  for (const [key] of SITEMAP_CHANNELS) {
    const value = record[key];
    if (typeof value === 'boolean') {
      flags[key] = value;
    }
  }
  if (!newsConfigured) {
    flags.news = false;
  }
  if (!localEnabled) {
    flags.kml = false;
  }
  return flags;
}

export function htaccessVisibility(server: string, advanced: boolean): { visible: boolean; note: string } {
  if (server.toLowerCase().includes('nginx')) {
    return { visible: false, note: 'Hidden on Nginx.' };
  }
  if (!advanced) {
    return { visible: false, note: 'Open Advanced to edit .htaccess.' };
  }
  return { visible: true, note: 'A backup is stored before any write. Confirm is required.' };
}

export function indexNowPlan(urls: string[], noindex: string[]): { queued: string[]; skipped: string[]; requested: false; note: string } {
  const queued: string[] = [];
  const skipped: string[] = [];
  for (const url of urls) {
    if (!/^https?:\/\//.test(url)) {
      continue;
    }
    if (noindex.includes(url)) {
      skipped.push(url);
      continue;
    }
    queued.push(url);
  }
  return {
    queued,
    skipped,
    requested: false,
    note: 'IndexNow did not send a request. noindex URLs are skipped.',
  };
}

export function linkSettingsPlan(
  flags: { new_tab?: boolean; nofollow?: boolean; auto_insert?: boolean },
  confirmed: boolean,
): { new_tab: boolean; nofollow: boolean; auto_insert: boolean; applied: false; inserted: false; stored: boolean; note: string } {
  const next = {
    new_tab: flags.new_tab === true,
    nofollow: flags.nofollow === true,
    auto_insert: flags.auto_insert === true,
    applied: false as const,
    inserted: false as const,
  };
  if (!confirmed) {
    return {
      ...next,
      stored: false,
      note: 'Global link settings stay unchanged until you confirm. Nothing is inserted.',
    };
  }
  return {
    ...next,
    stored: true,
    note: 'Global link settings are stored. Nothing is inserted.',
  };
}

export function podcastPlan(enabled: boolean, confirmed: boolean): { stored: boolean; enabled: boolean; published: false; note: string } {
  if (!confirmed) {
    return {
      stored: false,
      enabled: false,
      published: false,
      note: 'Podcast stays unchanged until you confirm. Nothing was published.',
    };
  }
  return {
    stored: true,
    enabled,
    published: false,
    note: enabled ? 'Podcast is enabled. Nothing was published.' : 'Podcast stays off. Nothing was published.',
  };
}

export function linkPresentation(board: unknown): { mode: string; inserted: boolean; note: string } {
  const record = isRecord(board) ? board : {};
  return {
    mode: typeof record.mode === 'string' && record.mode !== '' ? record.mode : 'Suggest Only',
    inserted: record.inserted === true,
    note: typeof record.note === 'string' ? record.note : 'Suggestions stay Suggest Only. Nothing is inserted.',
  };
}

export function listText(value: unknown): string {
  if (value === null || value === undefined) {
    return 'Not available';
  }
  if (Array.isArray(value)) {
    return value.length === 0 ? 'None' : value.map((item) => String(item)).join(', ');
  }
  return String(value);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
