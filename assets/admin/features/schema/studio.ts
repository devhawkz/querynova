import { SCHEMA_TYPES } from './rules';

export const SPEAKABLE_NOTE = 'Speakable is included only for an article or post with a headline and a CSS selector. It is not added to every page.';

export function videoDocument(title: string, contentUrl: string): { fetched: false; saved: false; ready: boolean; note: string } {
  const ready = title.trim() !== '' && /^https?:\/\//i.test(contentUrl.trim());
  return {
    fetched: false,
    saved: false,
    ready,
    note: ready
      ? 'Video markup uses the supplied fields. Remote pages are not fetched and the sitemap was not published.'
      : 'Video markup needs a supplied title and a content or embed URL. Remote pages are not fetched.',
  };
}

export const SCHEMA_TEMPLATES = [
  { id: 'article', label: 'Article', type: 'Article', properties: ['headline', 'description'] },
  { id: 'product', label: 'Product', type: 'Product', properties: ['name', 'description'] },
  { id: 'breadcrumb', label: 'Breadcrumb', type: 'BreadcrumbList', properties: ['itemListElement'] },
  { id: 'faq', label: 'FAQ', type: 'FAQPage', properties: ['mainEntity'] },
] as const;

export interface ImportPreview {
  valid: boolean;
  saved: false;
  type: string;
  properties: string[];
  note: string;
}

export function templateProperties(id: string): readonly string[] {
  return SCHEMA_TEMPLATES.find((template) => template.id === id)?.properties ?? [];
}

export function previewImport(json: string): ImportPreview {
  let decoded: unknown;
  try {
    decoded = JSON.parse(json);
  } catch {
    return invalid('JSON-LD could not be read. Nothing was saved.');
  }
  if (!isRecord(decoded)) {
    return invalid('JSON-LD did not contain an object. Nothing was saved.');
  }
  const graph = decoded['@graph'];
  const node = Array.isArray(graph) ? graph[0] : decoded;
  if (!isRecord(node)) {
    return invalid('JSON-LD did not contain an object. Nothing was saved.');
  }
  const rawType = node['@type'];
  const type = Array.isArray(rawType) ? String(rawType[0] ?? '') : typeof rawType === 'string' ? rawType : '';
  if (!SCHEMA_TYPES.some((item) => item === type)) {
    return invalid('That schema type is not available. Nothing was saved.');
  }
  const properties: string[] = [];
  for (const [property, value] of Object.entries(node)) {
    if (property.startsWith('@') || typeof value !== 'string' || value.trim() === '') {
      continue;
    }
    if (properties.length >= 8) {
      break;
    }
    properties.push(property);
  }
  return {
    valid: true,
    saved: false,
    type,
    properties,
    note: 'Only supplied properties are shown. Empty fields are not added. Nothing was saved.',
  };
}

function invalid(note: string): ImportPreview {
  return { valid: false, saved: false, type: '', properties: [], note };
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}
