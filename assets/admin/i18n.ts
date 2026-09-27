type WordPressI18n = {
  __: (text: string, domain: string) => string;
};

export function t(text: string): string {
  const scope = globalThis as { window?: { wp?: { i18n?: WordPressI18n } } };
  const i18n = scope.window?.wp?.i18n;
  if (i18n === undefined) {
    return text;
  }
  return i18n.__(text, 'querynova');
}
