export const EDITOR_TABS = ['General', 'Advanced', 'Schema', 'Social', 'AI/Content', 'Links'] as const;

export interface EditorFields {
  title: string;
  description: string;
  canonical: string;
  robots_index: string;
  robots_follow: string;
  robots_max_snippet: string;
  robots_max_image_preview: string;
  robots_max_video_preview: string;
  og_title: string;
  og_description: string;
  og_image: string;
  twitter_card: string;
  focus_keyword: string;
}

export interface EditorAdapterRow {
  id: string;
  label: string;
  active: boolean;
  mounted: boolean;
}

export interface SearchPreview {
  title: string;
  description: string;
  url: string;
}

const FIELD_KEYS: (keyof EditorFields)[] = [
  'title',
  'description',
  'canonical',
  'robots_index',
  'robots_follow',
  'robots_max_snippet',
  'robots_max_image_preview',
  'robots_max_video_preview',
  'og_title',
  'og_description',
  'og_image',
  'twitter_card',
  'focus_keyword',
];

export function emptyFields(): EditorFields {
  return {
    title: '',
    description: '',
    canonical: '',
    robots_index: '',
    robots_follow: '',
    robots_max_snippet: '',
    robots_max_image_preview: '',
    robots_max_video_preview: '',
    og_title: '',
    og_description: '',
    og_image: '',
    twitter_card: '',
    focus_keyword: '',
  };
}

export function fieldsFrom(input: unknown): EditorFields {
  const record = isRecord(input) ? input : {};
  const fields = emptyFields();
  for (const key of FIELD_KEYS) {
    const value = record[key];
    if (typeof value === 'string') {
      fields[key] = value;
    }
  }
  return fields;
}

export function searchPreview(fields: EditorFields, fallbackTitle: string, url: string): SearchPreview {
  return {
    title: fields.title.trim() !== '' ? fields.title : fallbackTitle,
    description: fields.description,
    url,
  };
}

export function dangerous(fields: EditorFields): { robots: boolean; canonical: boolean } {
  return {
    robots: fields.robots_index === 'noindex',
    canonical: fields.canonical.trim() !== '',
  };
}

export function editorRequestBody(objectId: number, fields: EditorFields): { object_id: number } & EditorFields {
  return { object_id: objectId, ...fields };
}

export function shouldSendEditorSave(previousSaving: boolean, saving: boolean, autosave: boolean, loaded: boolean): boolean {
  return loaded && saving && !previousSaving && !autosave;
}

export function elementorAdapter(adapters: unknown): EditorAdapterRow | null {
  if (!Array.isArray(adapters)) {
    return null;
  }
  const row = adapters.find((item) => isRecord(item) && item.id === 'elementor');
  if (!isRecord(row)) {
    return null;
  }
  return {
    id: 'elementor',
    label: typeof row.label === 'string' ? row.label : 'Elementor',
    active: row.active === true,
    mounted: row.mounted === true,
  };
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
