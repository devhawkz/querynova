import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { channelFlags, htaccessVisibility, SITEMAP_CHANNELS, type ChannelFlags } from '../links/model';

interface Props {
  restUrl: string;
  nonce: string;
  siteTools: unknown;
  mode: 'simple' | 'advanced';
}

export function SitemapFields({ restUrl, nonce, siteTools }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const record = isRecord(siteTools) && isRecord(siteTools.sitemaps) ? siteTools.sitemaps : {};
  const newsConfigured = record.news === 'configured';
  const localEnabled = record.kml === 'available';
  const [flags, setFlags] = useState<ChannelFlags>(() => channelFlags(record.channels, newsConfigured, localEnabled));
  const [urls, setUrls] = useState('');
  const [html, setHtml] = useState('');
  const [message, setMessage] = useState(typeof record.note === 'string' ? record.note : '');

  async function save() {
    const next = channelFlags(flags, newsConfigured, localEnabled);
    setFlags(next);
    if (restUrl === '' || nonce === '') {
      setMessage(t('Sitemap settings are not saved in this session.'));
      return;
    }
    try {
      const body = await api.put<{ note?: string }>('/sitemap/channels', { channels: next });
      setMessage(t(typeof body.note === 'string' ? body.note : 'Channel settings are stored. Existing post content was not rewritten.'));
    } catch {
      setMessage(t('Sitemap settings could not be stored.'));
    }
  }

  async function generateHtml() {
    if (restUrl === '' || nonce === '') {
      return;
    }
    const body = await api.post<{ html?: string; note?: string }>('/sitemap/html', {
      urls: urls.split('\n').map((line) => line.trim()).filter((line) => line !== ''),
    });
    setHtml(typeof body.html === 'string' ? body.html : '');
    setMessage(t(typeof body.note === 'string' ? body.note : 'Permalinks were not flushed.'));
  }

  return (
    <>
      <p>{t('News stays off until a publication name is stored. KML stays off until local SEO is enabled. A missing save leaves the public XML sitemap unchanged.')}</p>
      {SITEMAP_CHANNELS.map(([key, label]) => {
        const locked = (key === 'news' && !newsConfigured) || (key === 'kml' && !localEnabled);
        return (
          <label key={key}>
            <input
              type="checkbox"
              checked={flags[key]}
              disabled={locked}
              onChange={(event) => setFlags((current) => ({ ...current, [key]: event.target.checked }))}
            />
            {t(label)}
          </label>
        );
      })}
      <button type="button" onClick={() => void save()}>{t('Store sitemap channels')}</button>
      <label>
        {t('HTML sitemap URLs, one per line')}
        <textarea value={urls} onChange={(event) => setUrls(event.target.value)} />
      </label>
      <button type="button" onClick={() => void generateHtml()}>{t('Generate HTML sitemap')}</button>
      {html !== '' ? <pre>{html}</pre> : null}
      {message !== '' ? <p role="status">{message}</p> : null}
    </>
  );
}

export function ImageAltFields({ restUrl, nonce }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const [current, setCurrent] = useState('');
  const [suggestion, setSuggestion] = useState('');
  const [manual, setManual] = useState(true);
  const [overwrite, setOverwrite] = useState(false);
  const [message, setMessage] = useState('');

  async function suggest() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('Manual ALT text was left unchanged.'));
      return;
    }
    try {
      const body = await api.post<{ note?: string; written?: boolean }>('/seo/image-alt', { current, suggestion, manual, overwrite });
      setMessage(t(typeof body.note === 'string' ? body.note : 'Attachment ALT text was not written.'));
    } catch {
      setMessage(t('The ALT suggestion could not be prepared.'));
    }
  }

  return (
    <>
      <p>{t('Manual ALT text is left unchanged unless overwrite is requested. Attachment ALT text is not written from this screen.')}</p>
      <label>
        {t('Current ALT')}
        <input value={current} onChange={(event) => setCurrent(event.target.value)} />
      </label>
      <label>
        {t('Suggested ALT')}
        <input value={suggestion} onChange={(event) => setSuggestion(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={manual} onChange={(event) => setManual(event.target.checked)} />
        {t('This ALT was entered manually')}
      </label>
      <label>
        <input type="checkbox" checked={overwrite} onChange={(event) => setOverwrite(event.target.checked)} />
        {t('Overwrite manual ALT')}
      </label>
      <button type="button" onClick={() => void suggest()}>{t('Suggest ALT')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </>
  );
}

export function WebmasterFields({ restUrl, nonce, siteTools }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const codes = isRecord(siteTools) && isRecord(siteTools.webmaster) ? siteTools.webmaster : {};
  const [google, setGoogle] = useState(text(codes.google));
  const [bing, setBing] = useState(text(codes.bing));
  const [pinterest, setPinterest] = useState(text(codes.pinterest));
  const [yandex, setYandex] = useState(text(codes.yandex));
  const [message, setMessage] = useState('');

  async function save() {
    if (restUrl === '' || nonce === '') {
      return;
    }
    try {
      await api.put('/seo/webmaster', { google, bing, pinterest, yandex });
      setMessage(t('Verification codes are stored. Empty codes print nothing.'));
    } catch {
      setMessage(t('Verification codes could not be stored.'));
    }
  }

  return (
    <>
      <label>
        {t('Google')}
        <input value={google} onChange={(event) => setGoogle(event.target.value)} />
      </label>
      <label>
        {t('Bing')}
        <input value={bing} onChange={(event) => setBing(event.target.value)} />
      </label>
      <label>
        {t('Pinterest')}
        <input value={pinterest} onChange={(event) => setPinterest(event.target.value)} />
      </label>
      <label>
        {t('Yandex')}
        <input value={yandex} onChange={(event) => setYandex(event.target.value)} />
      </label>
      <button type="button" onClick={() => void save()}>{t('Store verification codes')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </>
  );
}

export function BreadcrumbFields({ restUrl, nonce, siteTools }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const stored = isRecord(siteTools) && isRecord(siteTools.breadcrumbs) ? siteTools.breadcrumbs : {};
  const [separator, setSeparator] = useState(text(stored.separator) || '/');
  const [home, setHome] = useState(text(stored.home) || 'Home');
  const [message, setMessage] = useState('');

  async function save() {
    if (restUrl === '' || nonce === '') {
      return;
    }
    try {
      await api.put('/seo/breadcrumbs', { separator, home });
      setMessage(t('Breadcrumb settings are stored. They are not inserted into every page.'));
    } catch {
      setMessage(t('Breadcrumb settings could not be stored.'));
    }
  }

  return (
    <>
      <p>{t('The PHP function is querynova_breadcrumbs(). The shortcode is querynova_breadcrumbs. The block is querynova/breadcrumbs. BreadcrumbList schema is produced for the crumbs you pass in.')}</p>
      <label>
        {t('Separator')}
        <input value={separator} onChange={(event) => setSeparator(event.target.value)} />
      </label>
      <label>
        {t('Home label')}
        <input value={home} onChange={(event) => setHome(event.target.value)} />
      </label>
      <button type="button" onClick={() => void save()}>{t('Store breadcrumb settings')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </>
  );
}

export function HtaccessFields({ restUrl, nonce, siteTools, mode }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const server = isRecord(siteTools) && typeof siteTools.server === 'string' ? siteTools.server : '';
  const visibility = htaccessVisibility(server, mode === 'advanced');
  const [content, setContent] = useState('');
  const [confirm, setConfirm] = useState(false);
  const [message, setMessage] = useState(visibility.note);

  async function save() {
    if (!visibility.visible || restUrl === '' || nonce === '') {
      setMessage(t(visibility.note));
      return;
    }
    try {
      const body = await api.put<{ note?: string }>('/seo/htaccess', {
        content,
        advanced: mode === 'advanced',
        confirm,
        write_file: false,
      });
      setMessage(t(typeof body.note === 'string' ? body.note : visibility.note));
    } catch {
      setMessage(t('The .htaccess backup could not be stored.'));
    }
  }

  if (!visibility.visible) {
    return <p>{t(visibility.note)}</p>;
  }

  return (
    <>
      <p>
        <span className="qn-badge" data-state="warning">{t('Warning')}</span>
      </p>
      <p>{t(visibility.note)}</p>
      <label>
        {t('.htaccess contents')}
        <textarea value={content} onChange={(event) => setContent(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={confirm} onChange={(event) => setConfirm(event.target.checked)} />
        {t('Confirm .htaccess backup')}
      </label>
      <button type="button" onClick={() => void save()}>{t('Store .htaccess backup')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </>
  );
}

function text(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
