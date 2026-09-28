import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const sources = import.meta.glob('./**/*.tsx', {
  query: '?raw',
  eager: true,
  import: 'default',
});

describe('admin accessibility', () => {
  it('names controls, keeps a main landmark, and does not remove focus outlines', () => {
    const entries = Object.entries(sources);
    expect(entries.length).toBeGreaterThan(0);
    const app = sources['./app/App.tsx'];
    expect(app).toContain('<nav aria-label="QueryNova">');
    expect(app).toContain('<main>');

    for (const [file, source] of entries) {
      if (typeof source !== 'string') {
        throw new Error(`${file} was not loaded as text`);
      }
      expect(source).not.toMatch(/outline\s*:\s*(none|0)\b/);
      assertNamedFields(source, file);
      assertNamedButtons(source, file);
      expect(source).not.toMatch(/tabindex\s*=\s*["']-1["']/i);
      expect(source).not.toMatch(/tabIndex=\{-1\}/);
    }
  });

  it('keeps a visible keyboard focus outline in the admin stylesheet', () => {
    const css = readFileSync(resolve('assets/admin/styles/admin.css'), 'utf8');
    expect(css).toContain(':focus-visible');
    expect(css).toMatch(/outline:\s*2px solid/);
    expect(css).not.toMatch(/outline\s*:\s*(none|0)\b/);
  });
});

function assertNamedFields(source: string, file: string): void {
  const pattern = /<(input|select|textarea)\b[^>]*>/g;
  for (const match of source.matchAll(pattern)) {
    const tag = match[0];
    if (tag.includes('aria-label=')) {
      continue;
    }
    const before = source.slice(0, match.index ?? 0);
    const labelAt = before.lastIndexOf('<label');
    const labelClose = before.lastIndexOf('</label>');
    if (labelAt === -1 || labelClose > labelAt) {
      throw new Error(`${file} has a ${tag} without a label`);
    }
  }
}

function assertNamedButtons(source: string, file: string): void {
  const pattern = /<button\b([^>]*)>([\s\S]*?)<\/button>/g;
  for (const match of source.matchAll(pattern)) {
    const attrs = match[1] ?? '';
    const text = (match[2] ?? '').replace(/<[^>]+>/g, '').trim();
    if (attrs.includes('aria-label=') || text !== '') {
      continue;
    }
    throw new Error(`${file} has a button without an accessible name`);
  }
}
