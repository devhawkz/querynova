import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { HEADLESS_NOTE, TEMPLATE_CONTEXTS, auditFindings, auditNote, checklistNote, checklistRows, statusLabel, type ReviewRow } from './model';

interface Props {
  restUrl: string;
  nonce: string;
  metaDefaults: unknown;
  seoAudit: unknown;
}

export function SeoScreen({ restUrl, nonce, metaDefaults, seoAudit }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const defaults = isRecord(metaDefaults) ? metaDefaults : {};
  const templates = isRecord(defaults.templates) ? defaults.templates : {};
  const [separator, setSeparator] = useState(typeof defaults.separator === 'string' ? defaults.separator : '-');
  const [templateText, setTemplateText] = useState<Record<string, { title: string; description: string }>>(() => initialTemplates(templates));
  const [documentText, setDocumentText] = useState({
    title: '',
    description: '',
    permalink: '',
    canonical: '',
    robots_index: '',
    robots_follow: '',
    robots_max_snippet: '',
    robots_max_image_preview: '',
    robots_max_video_preview: '',
    focus_keyword: '',
    html: '',
    og_title: '',
    og_description: '',
    og_image: '',
    twitter_card: '',
  });
  const [checks, setChecks] = useState<ReviewRow[]>([]);
  const [checkNote, setCheckNote] = useState('This checklist does not predict rankings.');
  const [permalinkPreview, setPermalinkPreview] = useState('');
  const [socialTitle, setSocialTitle] = useState('');
  const [message, setMessage] = useState('');
  const findings = auditFindings(seoAudit);

  async function checkDocument() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('The checklist cannot run in this session.'));
      return;
    }
    try {
      const report = await api.post<unknown>('/seo/checklist', documentText);
      setChecks(checklistRows(report));
      setCheckNote(checklistNote(report));
      const record = isRecord(report) ? report : {};
      setPermalinkPreview(typeof record.permalink_preview === 'string' ? record.permalink_preview : '');
      const social = isRecord(record.social_preview) ? record.social_preview : {};
      setSocialTitle(typeof social.title === 'string' ? social.title : '');
      setMessage(t('Checklist finished. The document was not saved and the site was not crawled.'));
    } catch {
      setMessage(t('The checklist could not be run.'));
    }
  }

  async function saveTemplates() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('Templates cannot be saved in this session.'));
      return;
    }
    try {
      await api.put('/seo/templates', { separator, templates: templateText });
      setMessage(t('Global templates are stored. Custom titles on existing documents were not rewritten.'));
    } catch {
      setMessage(t('Templates could not be saved.'));
    }
  }

  async function runAudit() {
    if (restUrl === '' || nonce === '') {
      setMessage(t('The audit cannot be queued in this session.'));
      return;
    }
    try {
      await api.post('/seo/audit', { source: 'stored' });
      setMessage(t('SEO audit queued. This request does not crawl the site.'));
    } catch {
      setMessage(t('The SEO audit could not be queued.'));
    }
  }

  return (
    <>
      <section aria-labelledby="qn-headless">
        <h2 id="qn-headless">{t('Headless SEO')}</h2>
        <p>{t(HEADLESS_NOTE)}</p>
      </section>
      <section aria-labelledby="qn-on-page">
        <h2 id="qn-on-page">{t('On-page checklist')}</h2>
        <p>{t(checkNote)}</p>
        <p>{t('The check uses the text in this form. It does not save the document and it does not crawl the site.')}</p>
        <label>
          {t('SEO title')}
          <input value={documentText.title} onChange={(event) => setDocumentText({ ...documentText, title: event.target.value })} />
        </label>
        <label>
          {t('Description')}
          <textarea value={documentText.description} onChange={(event) => setDocumentText({ ...documentText, description: event.target.value })} />
        </label>
        <label>
          {t('Permalink')}
          <input value={documentText.permalink} onChange={(event) => setDocumentText({ ...documentText, permalink: event.target.value })} />
        </label>
        <label>
          {t('Canonical URL')}
          <input value={documentText.canonical} onChange={(event) => setDocumentText({ ...documentText, canonical: event.target.value })} />
        </label>
        <label>
          {t('Index')}
          <select value={documentText.robots_index} onChange={(event) => setDocumentText({ ...documentText, robots_index: event.target.value })}>
            <option value="">{t('Default')}</option>
            <option value="index">{t('Index')}</option>
            <option value="noindex">{t('Noindex')}</option>
          </select>
        </label>
        <label>
          {t('Follow')}
          <select value={documentText.robots_follow} onChange={(event) => setDocumentText({ ...documentText, robots_follow: event.target.value })}>
            <option value="">{t('Default')}</option>
            <option value="follow">{t('Follow')}</option>
            <option value="nofollow">{t('Nofollow')}</option>
          </select>
        </label>
        <label>
          {t('Max snippet')}
          <input value={documentText.robots_max_snippet} onChange={(event) => setDocumentText({ ...documentText, robots_max_snippet: event.target.value })} />
        </label>
        <label>
          {t('Max image preview')}
          <select value={documentText.robots_max_image_preview} onChange={(event) => setDocumentText({ ...documentText, robots_max_image_preview: event.target.value })}>
            <option value="">{t('Default')}</option>
            <option value="none">{t('None')}</option>
            <option value="standard">{t('Standard')}</option>
            <option value="large">{t('Large')}</option>
          </select>
        </label>
        <label>
          {t('Max video preview')}
          <input value={documentText.robots_max_video_preview} onChange={(event) => setDocumentText({ ...documentText, robots_max_video_preview: event.target.value })} />
        </label>
        <label>
          {t('Focus keywords')}
          <input value={documentText.focus_keyword} onChange={(event) => setDocumentText({ ...documentText, focus_keyword: event.target.value })} />
        </label>
        <label>
          {t('Document HTML')}
          <textarea value={documentText.html} onChange={(event) => setDocumentText({ ...documentText, html: event.target.value })} />
        </label>
        <label>
          {t('Open Graph title')}
          <input value={documentText.og_title} onChange={(event) => setDocumentText({ ...documentText, og_title: event.target.value })} />
        </label>
        <label>
          {t('Open Graph description')}
          <textarea value={documentText.og_description} onChange={(event) => setDocumentText({ ...documentText, og_description: event.target.value })} />
        </label>
        <label>
          {t('Open Graph image')}
          <input value={documentText.og_image} onChange={(event) => setDocumentText({ ...documentText, og_image: event.target.value })} />
        </label>
        <label>
          {t('X/Twitter card')}
          <input value={documentText.twitter_card} onChange={(event) => setDocumentText({ ...documentText, twitter_card: event.target.value })} />
        </label>
        <button type="button" onClick={() => void checkDocument()}>{t('Check this document')}</button>
        {permalinkPreview === '' ? null : <p>{t('Permalink preview')} {permalinkPreview}</p>}
        {socialTitle === '' ? null : <p>{t('Social preview')} {socialTitle}</p>}
        <ReviewList rows={checks} empty={t('No checklist result yet. Check this document to see Passed, Warning, Failed, and Info.')} />
      </section>
      <section aria-labelledby="qn-templates">
        <h2 id="qn-templates">{t('Titles and meta templates')}</h2>
        <p>{t('Variables include title, sep, sitename, excerpt, category, tag, author, date, page, pt_single, pt_plural, term, term_description, search_query, location, and name.')}</p>
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
                onChange={(event) => setTemplateText({ ...templateText, [context]: { title: event.target.value, description: templateText[context]?.description ?? '' } })}
              />
            </label>
            <label>
              {t('Description template')}
              <input
                value={templateText[context]?.description ?? ''}
                onChange={(event) => setTemplateText({ ...templateText, [context]: { title: templateText[context]?.title ?? '', description: event.target.value } })}
              />
            </label>
          </fieldset>
        ))}
        <button type="button" onClick={() => void saveTemplates()}>{t('Save templates')}</button>
      </section>
      <section aria-labelledby="qn-analyzer">
        <h2 id="qn-analyzer">{t('SEO Analyzer')}</h2>
        <p>{t(auditNote(seoAudit))}</p>
        <p>{t('Run SEO Audit queues a background job. It reads stored crawl issues and does not crawl during this request.')}</p>
        <button type="button" onClick={() => void runAudit()}>{t('Run SEO Audit')}</button>
        <button type="button" onClick={() => void runAudit()}>{t('Rerun')}</button>
        <ReviewList rows={findings} empty={t('No stored audit findings. Run the crawler, then rerun the audit. Nothing here is a ranking impact.')} />
      </section>
      {message === '' ? null : <p role="status">{message}</p>}
    </>
  );
}

function ReviewList({ rows, empty }: { rows: ReviewRow[]; empty: string }) {
  if (rows.length === 0) {
    return <p>{empty}</p>;
  }
  return (
    <ul>
      {rows.map((row, index) => (
        <li key={`${row.status}-${index}`}>
          <span className="qn-badge" data-state={badgeState(row.status)}>{t(statusLabel(row.status))}</span>
          <p>{row.explanation}</p>
          <p>{t('Evidence')} {row.evidence}</p>
          <p>{t('How to fix')} {row.howToFix}</p>
          {row.url === '' ? null : <p>{row.url}</p>}
        </li>
      ))}
    </ul>
  );
}

function badgeState(status: string): string {
  if (status === 'passed') return 'success';
  if (status === 'warning') return 'warning';
  if (status === 'failed') return 'critical';
  return 'info';
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
