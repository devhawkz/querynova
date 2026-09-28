import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { urlBasePlan } from '../commerce/workspace';
import { TEMPLATE_CONTEXTS } from '../seo/model';
import { roleSavePlan, titleSavePlan } from './settings-edits';

interface SessionProps {
  restUrl: string;
  nonce: string;
}

export function TitleSettings({ restUrl, nonce, metaDefaults }: SessionProps & { metaDefaults: unknown }) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const defaults = isRecord(metaDefaults) ? metaDefaults : {};
  const templates = isRecord(defaults.templates) ? defaults.templates : {};
  const [separator, setSeparator] = useState(typeof defaults.separator === 'string' ? defaults.separator : '-');
  const [templateText, setTemplateText] = useState(() => initialTemplates(templates));
  const [confirmed, setConfirmed] = useState(false);
  const [message, setMessage] = useState('');

  async function save() {
    const plan = titleSavePlan(confirmed);
    if (!confirmed || restUrl === '' || nonce === '') {
      setMessage(t(plan.note));
      return;
    }
    try {
      await api.put('/seo/templates', { separator, templates: templateText });
      setMessage(t(plan.note));
    } catch {
      setMessage(t('Title templates could not be stored. Custom titles on existing documents were not rewritten.'));
    }
  }

  return (
    <>
      <p>{t('Saving stores these defaults. It does not rewrite custom titles on existing documents.')}</p>
      <label>
        {t('Title separator')}
        <input value={separator} onChange={(event) => setSeparator(event.target.value)} />
      </label>
      {TEMPLATE_CONTEXTS.map((context) => (
        <fieldset key={context}>
          <legend>{context}</legend>
          <label>
            {t('Title template')}
            <input
              value={templateText[context]?.title ?? ''}
              onChange={(event) => setTemplateText({
                ...templateText,
                [context]: { title: event.target.value, description: templateText[context]?.description ?? '' },
              })}
            />
          </label>
          <label>
            {t('Description template')}
            <input
              value={templateText[context]?.description ?? ''}
              onChange={(event) => setTemplateText({
                ...templateText,
                [context]: { title: templateText[context]?.title ?? '', description: event.target.value },
              })}
            />
          </label>
        </fieldset>
      ))}
      <label>
        <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />
        {t('Confirm title templates')}
      </label>
      <button type="button" onClick={() => void save()}>{t('Store title templates')}</button>
      {message === '' ? null : <p role="status">{message}</p>}
    </>
  );
}

export function RoleSettings({ restUrl, nonce }: SessionProps) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [name, setName] = useState('');
  const [area, setArea] = useState('seo');
  const [confirmed, setConfirmed] = useState(false);
  const [message, setMessage] = useState('');

  async function save() {
    const plan = roleSavePlan(name, confirmed);
    if (!plan.stored || restUrl === '' || nonce === '') {
      setMessage(t(plan.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/reports/roles', { name, areas: [area], confirmed: true });
      setMessage(typeof body.note === 'string' ? body.note : t(plan.note));
    } catch {
      setMessage(t('The custom role was not stored. WordPress roles were not changed.'));
    }
  }

  return (
    <>
      <p>{t('Built-in roles stay on the fixed map. A custom role is stored only after you confirm, and it is not applied to WordPress.')}</p>
      <label>
        {t('Custom role')}
        <input value={name} onChange={(event) => setName(event.target.value)} />
      </label>
      <label>
        {t('Area')}
        <select value={area} onChange={(event) => setArea(event.target.value)}>
          <option value="seo">{t('SEO')}</option>
          <option value="analysis">{t('Analysis')}</option>
          <option value="commerce">{t('Commerce')}</option>
          <option value="analytics">{t('Analytics')}</option>
        </select>
      </label>
      <label>
        <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />
        {t('Confirm custom role')}
      </label>
      <button type="button" onClick={() => void save()}>{t('Store custom role')}</button>
      {message === '' ? null : <p role="status">{message}</p>}
    </>
  );
}

export function UrlBaseSettings({ restUrl, nonce }: SessionProps) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [productBase, setProductBase] = useState('');
  const [categoryBase, setCategoryBase] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [message, setMessage] = useState('');

  async function save() {
    const plan = urlBasePlan(productBase, categoryBase, confirmed);
    if (!confirmed || restUrl === '' || nonce === '') {
      setMessage(t(plan.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/commerce/url-base', {
        product_base: productBase,
        category_base: categoryBase,
        confirmed: true,
      });
      setMessage(typeof body.note === 'string' ? body.note : t(plan.note));
    } catch {
      setMessage(t('The request was not stored. Live product and category URLs were not changed.'));
    }
  }

  return (
    <>
      <p>{t('Product and category URL bases stay unchanged until you confirm. Live URLs are not rewritten.')}</p>
      <label>
        {t('Product base')}
        <input value={productBase} onChange={(event) => setProductBase(event.target.value)} />
      </label>
      <label>
        {t('Category base')}
        <input value={categoryBase} onChange={(event) => setCategoryBase(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />
        {t('Confirm URL bases')}
      </label>
      <button type="button" onClick={() => void save()}>{t('Store URL base request')}</button>
      {message === '' ? null : <p role="status">{message}</p>}
    </>
  );
}

function initialTemplates(templates: Record<string, unknown>): Record<string, { title: string; description: string }> {
  const next: Record<string, { title: string; description: string }> = {};
  for (const context of TEMPLATE_CONTEXTS) {
    const row = templates[context];
    const record = isRecord(row) ? row : {};
    next[context] = {
      title: typeof record.title === 'string' ? record.title : '',
      description: typeof record.description === 'string' ? record.description : '',
    };
  }
  return next;
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
