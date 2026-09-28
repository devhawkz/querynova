import './editor.css';
import { shouldSendEditorSave, type EditorFields } from './model';
import { renderEditor, saveEditor, type EditorMount } from './panel';

interface EditorStore {
  isSavingPost?: () => boolean;
  isAutosavingPost?: () => boolean;
}

interface WordPressEditor {
  plugins?: { registerPlugin?: (name: string, settings: { render: () => unknown }) => void };
  editPost?: { PluginSidebar?: unknown };
  editor?: { PluginSidebar?: unknown };
  element?: { createElement: (type: unknown, props?: Record<string, unknown> | null, ...children: unknown[]) => unknown };
  data?: {
    select?: (store: string) => EditorStore | undefined;
    subscribe?: (listener: () => void) => void;
  };
}

declare global {
  interface Window {
    querynovaEditor?: EditorMount;
    wp?: WordPressEditor;
  }
}

const boot = window.querynovaEditor;
if (boot) {
  if (boot.surface === 'gutenberg') {
    registerSidebar(boot);
  }
  const start = (): void => {
    if (boot.surface === 'gutenberg') {
      return;
    }
    const root = document.getElementById(boot.surface === 'woocommerce' ? 'querynova-product-editor' : 'querynova-editor');
    if (root) {
      renderEditor(root, boot, 'form');
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
}

function registerSidebar(source: EditorMount): void {
  const wp = window.wp;
  const sidebar = wp?.editPost?.PluginSidebar ?? wp?.editor?.PluginSidebar;
  if (!wp?.plugins?.registerPlugin || !wp.element || sidebar === undefined) {
    return;
  }
  let fields: EditorFields | null = null;
  let saving = false;
  wp.plugins.registerPlugin('querynova-editor', {
    render: () => {
      const host = wp.element?.createElement('div', {
        className: 'qn-editor-mount',
        ref: (node: HTMLElement | null) => {
          if (node === null || node.dataset.ready === '1') {
            return;
          }
          node.dataset.ready = '1';
          renderEditor(node, source, 'rest');
          const reader = (node as HTMLElement & { querynovaRead?: () => EditorFields }).querynovaRead;
          if (reader) {
            fields = reader();
            const sync = (): void => {
              fields = reader();
            };
            node.addEventListener('input', sync);
            node.addEventListener('change', sync);
          }
        },
      });
      return wp.element?.createElement(sidebar, { name: 'querynova-document', title: 'QueryNova', icon: 'search' }, host);
    },
  });
  wp.data?.subscribe?.(() => {
    const editor = wp.data?.select?.('core/editor');
    const now = editor?.isSavingPost?.() === true;
    const autosave = editor?.isAutosavingPost?.() === true;
    if (fields !== null && shouldSendEditorSave(saving, now, autosave, true)) {
      const pending = fields;
      void saveEditor(source, pending).then((message) => {
        const alert = document.querySelector('.qn-editor [role="alert"]');
        if (alert) {
          alert.textContent = message;
        }
      });
    }
    saving = now;
  });
}
