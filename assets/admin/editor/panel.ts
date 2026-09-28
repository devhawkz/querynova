import { dangerous, EDITOR_TABS, editorRequestBody, elementorAdapter, fieldsFrom, searchPreview, type EditorFields } from './model';

export interface EditorMount {
  objectId?: number;
  postType?: string;
  surface?: string;
  restUrl?: string;
  nonce?: string;
  fallbackTitle?: string;
  permalink?: string;
  adapters?: unknown;
  document?: {
    stored?: unknown;
    preview?: { title?: string; description?: string; url?: string };
  };
}

type Mode = 'form' | 'rest';

export function renderEditor(root: HTMLElement, boot: EditorMount, mode: Mode): void {
  const fields = fieldsFrom(boot.document?.stored);
  const fallbackTitle = boot.fallbackTitle ?? '';
  const url = boot.permalink ?? '';
  const shell = document.createElement('div');
  shell.className = 'qn-editor';
  const elementor = elementorAdapter(boot.adapters);
  if (elementor !== null && !elementor.mounted) {
    shell.append(paragraph('Elementor adapter is present and not built. This document still opens in Gutenberg or the classic editor.'));
  }
  const tabs = document.createElement('div');
  tabs.className = 'qn-editor-tabs';
  tabs.setAttribute('role', 'tablist');
  tabs.setAttribute('aria-label', t('QueryNova editor'));
  const preview = searchPreview(fields, fallbackTitle, url);
  const previewTitle = paragraph(preview.title);
  previewTitle.className = 'qn-preview-title';
  const previewUrl = paragraph(preview.url === '' ? 'Permalink is not available.' : preview.url);
  previewUrl.className = 'qn-preview-url';
  const previewDescription = paragraph(preview.description === '' ? 'No description stored.' : preview.description);
  previewDescription.className = 'qn-preview-description';
  const robotsWarning = warning('noindex can remove this URL from search results.');
  const canonicalWarning = warning('A canonical URL replaces the default URL for this document.');
  const status = document.createElement('p');
  status.setAttribute('role', 'alert');
  const inputs = new Map<keyof EditorFields, HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>();

  function read(): EditorFields {
    const next = fieldsFrom(boot.document?.stored);
    for (const [key, input] of inputs) {
      next[key] = input.value;
    }
    return next;
  }

  function refresh(): void {
    const next = read();
    const shown = searchPreview(next, fallbackTitle, url);
    previewTitle.textContent = shown.title === '' ? t('No title stored.') : shown.title;
    previewDescription.textContent = shown.description === '' ? t('No description stored.') : shown.description;
    const flags = dangerous(next);
    robotsWarning.hidden = !flags.robots;
    canonicalWarning.hidden = !flags.canonical;
  }

  const general = document.createElement('div');
  general.append(
    previewBlock(previewTitle, previewUrl, previewDescription),
    labeled('SEO title', control('title', 'input', fields.title, inputs)),
    labeled('Description', control('description', 'textarea', fields.description, inputs)),
    labeled('Focus keywords', control('focus_keyword', 'input', fields.focus_keyword, inputs)),
    paragraph('Separate extra keywords with commas. This does not change the document body.'),
    paragraph('The preview uses the stored title when there is one. It does not predict rankings.'),
  );
  const advanced = document.createElement('div');
  advanced.append(
    labeled('Index', choice('robots_index', fields.robots_index, ['', 'index', 'noindex'], ['Default', 'Index', 'Noindex'], inputs)),
    robotsWarning,
    labeled('Follow', choice('robots_follow', fields.robots_follow, ['', 'follow', 'nofollow'], ['Default', 'Follow', 'Nofollow'], inputs)),
    labeled('Max snippet', control('robots_max_snippet', 'input', fields.robots_max_snippet, inputs)),
    labeled('Max image preview', choice('robots_max_image_preview', fields.robots_max_image_preview, ['', 'none', 'standard', 'large'], ['Default', 'None', 'Standard', 'Large'], inputs)),
    labeled('Max video preview', control('robots_max_video_preview', 'input', fields.robots_max_video_preview, inputs)),
    paragraph('max-snippet, max-image-preview, and max-video-preview change how much of this document a crawler may show. Empty leaves the default.'),
    labeled('Canonical URL', control('canonical', 'input', fields.canonical, inputs)),
    canonicalWarning,
  );
  const schema = document.createElement('div');
  schema.append(paragraph('Schema rules stay on the Schema screen. This panel does not add schema to the document.'));
  const social = document.createElement('div');
  social.append(
    labeled('Open Graph title', control('og_title', 'input', fields.og_title, inputs)),
    labeled('Open Graph description', control('og_description', 'textarea', fields.og_description, inputs)),
    labeled('Open Graph image', control('og_image', 'input', fields.og_image, inputs)),
    labeled('X/Twitter card', choice('twitter_card', fields.twitter_card, ['', 'summary', 'summary_large_image'], ['Default', 'Summary', 'Summary with large image'], inputs)),
  );
  const ai = document.createElement('div');
  ai.append(paragraph('Content drafts are not generated here and nothing is published.'));
  const links = document.createElement('div');
  links.append(paragraph('Link suggestions stay Suggest Only. This panel does not insert links.'));
  const sections = [general, advanced, schema, social, ai, links];

  EDITOR_TABS.forEach((label, index) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = t(label);
    button.setAttribute('role', 'tab');
    button.setAttribute('aria-selected', index === 0 ? 'true' : 'false');
    const panel = sections[index] ?? document.createElement('div');
    panel.hidden = index !== 0;
    button.addEventListener('click', () => {
      sections.forEach((section, sectionIndex) => {
        section.hidden = sectionIndex !== index;
      });
      Array.from(tabs.querySelectorAll('button')).forEach((item, itemIndex) => {
        item.setAttribute('aria-selected', itemIndex === index ? 'true' : 'false');
      });
    });
    tabs.append(button);
  });

  if (mode === 'form') {
    const marker = document.createElement('input');
    marker.type = 'hidden';
    marker.name = 'querynova_editor_fields';
    marker.value = '1';
    shell.append(marker);
  }
  shell.append(tabs, ...sections, status, paragraph(t('Saving writes this document only.')));
  root.replaceChildren(shell);
  refresh();
  for (const input of inputs.values()) {
    input.addEventListener('input', refresh);
    input.addEventListener('change', refresh);
  }

  if (mode === 'rest') {
    (root as HTMLElement & { querynovaRead?: () => EditorFields }).querynovaRead = read;
  }
}

export async function saveEditor(boot: EditorMount, fields: EditorFields): Promise<string> {
  const objectId = boot.objectId ?? 0;
  if (objectId < 1 || (boot.restUrl ?? '') === '' || (boot.nonce ?? '') === '') {
    return 'This document was not saved. The editor session is missing a post or a nonce.';
  }
  const response = await fetch(join(boot.restUrl ?? '', '/seo/editor'), {
    method: 'PUT',
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-WP-Nonce': boot.nonce ?? '',
    },
    body: JSON.stringify(editorRequestBody(objectId, fields)),
  });
  if (!response.ok) {
    return `This document was not saved. HTTP ${response.status}.`;
  }
  return '';
}

function previewBlock(title: HTMLElement, url: HTMLElement, description: HTMLElement): HTMLElement {
  const block = document.createElement('section');
  block.className = 'qn-preview';
  block.setAttribute('aria-label', 'Search preview');
  const heading = document.createElement('h3');
  heading.textContent = t('Search preview');
  block.append(heading, title, url, description);
  return block;
}

function control(
  key: keyof EditorFields,
  kind: 'input' | 'textarea',
  value: string,
  inputs: Map<keyof EditorFields, HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>,
): HTMLInputElement | HTMLTextAreaElement {
  const input = document.createElement(kind);
  input.value = value;
  if (input instanceof HTMLInputElement) {
    input.type = 'text';
    input.name = `querynova_${key}`;
  } else {
    input.name = `querynova_${key}`;
  }
  inputs.set(key, input);
  return input;
}

function choice(
  key: keyof EditorFields,
  value: string,
  values: string[],
  labels: string[],
  inputs: Map<keyof EditorFields, HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>,
): HTMLSelectElement {
  const select = document.createElement('select');
  select.name = `querynova_${key}`;
  values.forEach((optionValue, index) => {
    const option = document.createElement('option');
    option.value = optionValue;
    option.textContent = t(labels[index] ?? optionValue);
    select.append(option);
  });
  if (value !== '' && !values.includes(value)) {
    const option = document.createElement('option');
    option.value = value;
    option.textContent = value;
    select.append(option);
  }
  select.value = value;
  inputs.set(key, select);
  return select;
}

function labeled(text: string, input: HTMLElement): HTMLLabelElement {
  const label = document.createElement('label');
  label.append(document.createTextNode(t(text)), input);
  return label;
}

function paragraph(text: string): HTMLParagraphElement {
  const node = document.createElement('p');
  node.textContent = t(text);
  return node;
}

function warning(text: string): HTMLParagraphElement {
  const node = paragraph(text);
  const badge = document.createElement('span');
  badge.className = 'qn-badge';
  badge.textContent = t('Warning');
  node.prepend(badge, document.createTextNode(' '));
  return node;
}

function t(text: string): string {
  const i18n = (window as Window & { wp?: { i18n?: { __?: (value: string, domain: string) => string } } }).wp?.i18n;
  if (!i18n?.__) {
    return text;
  }
  return i18n.__(text, 'querynova');
}

function join(base: string, path: string): string {
  return `${base.replace(/\/$/, '')}${path}`;
}
