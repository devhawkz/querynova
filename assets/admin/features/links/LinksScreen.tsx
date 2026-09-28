import { useMemo, useState } from 'react';
import { DataTable } from '../../components/DataTable';
import { textCell } from '../../components/data-table';
import { Toast } from '../../components/Toast';
import { missingSessionToast } from '../../components/action-toast';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { indexNowPlan, linkPresentation, linkSettingsPlan, listText, podcastPlan, ROBOTS_NOTE } from './model';

interface Props {
  restUrl: string;
  nonce: string;
  siteTools: unknown;
}

export function LinksScreen({ restUrl, nonce, siteTools }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const stored = isRecord(siteTools) ? siteTools : {};
  const [board, setBoard] = useState(stored.links);
  const [pages, setPages] = useState('');
  const [broken, setBroken] = useState('');
  const [redirectSearch, setRedirectSearch] = useState('');
  const [redirectStatus, setRedirectStatus] = useState('');
  const [redirects, setRedirects] = useState<ListPage>({ rows: [], total: 0, page: 1, pages: 0 });
  const [missingSearch, setMissingSearch] = useState('');
  const [missing, setMissing] = useState<ListPage>({ rows: [], total: 0, page: 1, pages: 0 });
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [confirmed, setConfirmed] = useState(false);
  const [robots, setRobots] = useState(textField(stored.robots, 'content'));
  const [allowFile, setAllowFile] = useState(false);
  const [robotsConfirm, setRobotsConfirm] = useState(false);
  const [separator, setSeparator] = useState(textField(stored.breadcrumbs, 'separator') || '/');
  const [home, setHome] = useState(textField(stored.breadcrumbs, 'home') || 'Home');
  const [rssEnabled, setRssEnabled] = useState(boolField(stored.rss, 'enabled'));
  const [before, setBefore] = useState(textField(stored.rss, 'before'));
  const [after, setAfter] = useState(textField(stored.rss, 'after'));
  const [indexUrls, setIndexUrls] = useState('');
  const [noindexUrls, setNoindexUrls] = useState('');
  const [newTab, setNewTab] = useState(boolField(stored.link_settings, 'new_tab'));
  const [nofollow, setNofollow] = useState(boolField(stored.link_settings, 'nofollow'));
  const [autoInsert, setAutoInsert] = useState(boolField(stored.link_settings, 'auto_insert'));
  const [linkConfirm, setLinkConfirm] = useState(false);
  const [podcastEnabled, setPodcastEnabled] = useState(boolField(stored.podcast, 'enabled'));
  const [podcastConfirm, setPodcastConfirm] = useState(false);
  const [message, setMessage] = useState('');
  const [toast, setToast] = useState('');
  const presentation = linkPresentation(board);
  const boardRecord = isRecord(board) ? board : {};
  const ready = restUrl !== '' && nonce !== '';

  async function reviewLinks() {
    const pageRows = pages.split('\n').map((line) => line.trim()).filter((line) => line !== '');
    const brokenRows = broken.split('\n').map((line) => line.trim()).filter((line) => line !== '');
    if (!ready) {
      setMessage(t('Link suggestions stay Suggest Only. This session cannot save them.'));
      return;
    }
    try {
      const next = await api.post<unknown>('/content/links', {
        pages: pageRows.map((url, index) => ({ url, links: [], broken: index === 0 ? brokenRows : [] })),
        suggestions: [],
      });
      setBoard(next);
      setMessage(t('Suggestions stay Suggest Only. Nothing is inserted.'));
    } catch {
      setMessage(t('The link graph could not be reviewed.'));
    }
  }

  async function loadRedirects(page: number) {
    if (!ready) {
      return;
    }
    const body = await api.get<unknown>(`/redirects/list?search=${encodeURIComponent(redirectSearch)}&status=${encodeURIComponent(redirectStatus)}&page=${page}&per_page=20`);
    setRedirects(readList(body));
  }

  async function loadMissing(page: number) {
    if (!ready) {
      return;
    }
    const body = await api.get<unknown>(`/not-found/list?search=${encodeURIComponent(missingSearch)}&page=${page}&per_page=20`);
    setMissing(readList(body));
  }

  async function savePermalink() {
    if (!ready) {
      setToast(t(missingSessionToast('save')));
      return;
    }
    setToast('');
    try {
      const body = await api.post<{ note?: string; created?: boolean }>('/redirects/permalink', { from, to, confirmed });
      setMessage(t(typeof body.note === 'string' ? body.note : 'A redirect is not created until you confirm it.'));
    } catch {
      setMessage(t('The permalink redirect was not stored.'));
    }
  }

  async function saveRobots() {
    if (!ready) {
      setToast(t(missingSessionToast('save')));
      return;
    }
    setToast('');
    try {
      const body = await api.put<{ note?: string }>('/seo/robots', { content: robots, allow_file: allowFile, confirm: robotsConfirm });
      setMessage(t(typeof body.note === 'string' ? body.note : ROBOTS_NOTE));
    } catch {
      setMessage(t('robots.txt could not be stored.'));
    }
  }

  async function saveBreadcrumbs() {
    if (!ready) {
      setToast(t(missingSessionToast('save')));
      return;
    }
    setToast('');
    try {
      await api.put('/seo/breadcrumbs', { separator, home });
      setMessage(t('Breadcrumb settings are stored. They are not inserted into every page.'));
    } catch {
      setMessage(t('Breadcrumb settings could not be stored.'));
    }
  }

  async function saveRss() {
    if (!ready) {
      setToast(t(missingSessionToast('save')));
      return;
    }
    setToast('');
    try {
      await api.put('/seo/rss', { enabled: rssEnabled, before, after });
      setMessage(t('RSS text is stored. It is added only when RSS text is enabled.'));
    } catch {
      setMessage(t('RSS text could not be stored.'));
    }
  }

  function planIndexNow() {
    const plan = indexNowPlan(lines(indexUrls), lines(noindexUrls));
    if (!ready) {
      setMessage(t(plan.note));
      return;
    }
    void api.post('/seo/indexnow', { urls: lines(indexUrls), noindex: lines(noindexUrls) }).then((body) => {
      const record = isRecord(body) ? body : {};
      setMessage(t(typeof record.note === 'string' ? record.note : plan.note));
    }).catch(() => {
      setMessage(t(plan.note));
    });
  }

  async function saveLinkSettings() {
    const local = linkSettingsPlan({ new_tab: newTab, nofollow, auto_insert: autoInsert }, linkConfirm);
    if (!linkConfirm || !ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/content/link-settings', {
        new_tab: newTab,
        nofollow,
        auto_insert: autoInsert,
        confirmed: true,
      });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('Link settings were not stored. Nothing is inserted.'));
    }
  }

  async function savePodcast() {
    const local = podcastPlan(podcastEnabled, podcastConfirm);
    if (!podcastConfirm || !ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/podcast', { enabled: podcastEnabled, confirmed: true });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('Podcast was not changed. Nothing was published.'));
    }
  }

  return (
    <section aria-labelledby="qn-links">
      <h2 id="qn-links">{t('Links')}</h2>
      <p>
        <span className="qn-badge" data-state="info">{t(presentation.mode)}</span>
      </p>
      <p>{t(presentation.note)}</p>
      <dl>
        <dt>{t('Broken links')}</dt>
        <dd>{listText(boardRecord.broken)}</dd>
        <dt>{t('Orphans')}</dt>
        <dd>{listText(boardRecord.orphans)}</dd>
        <dt>{t('Inserted')}</dt>
        <dd>{presentation.inserted ? t('Yes') : t('No')}</dd>
      </dl>
      <label>
        {t('Pages, one URL per line')}
        <textarea value={pages} onChange={(event) => setPages(event.target.value)} />
      </label>
      <label>
        {t('Broken links, one URL per line')}
        <textarea value={broken} onChange={(event) => setBroken(event.target.value)} />
      </label>
      <button type="button" onClick={() => void reviewLinks()}>{t('Review link graph')}</button>

      <h3>{t('Redirects')}</h3>
      <label>
        {t('Search redirects')}
        <input value={redirectSearch} onChange={(event) => setRedirectSearch(event.target.value)} />
      </label>
      <label>
        {t('Status')}
        <input value={redirectStatus} onChange={(event) => setRedirectStatus(event.target.value)} />
      </label>
      <button type="button" onClick={() => void loadRedirects(1)}>{t('Search')}</button>
      <DataTable
        caption={t('Redirects')}
        columns={storedColumns()}
        rows={redirects.rows}
        page={redirects.page}
        perPage={20}
        total={redirects.total}
        empty={t('No redirects match this search.')}
        previousLabel={t('Previous page')}
        nextLabel={t('Next page')}
        onPage={(page) => void loadRedirects(page)}
      />

      <h3>{t('404 monitor')}</h3>
      <label>
        {t('Search 404s')}
        <input value={missingSearch} onChange={(event) => setMissingSearch(event.target.value)} />
      </label>
      <button type="button" onClick={() => void loadMissing(1)}>{t('Filter 404s')}</button>
      <DataTable
        caption={t('404 monitor')}
        columns={storedColumns()}
        rows={missing.rows}
        page={missing.page}
        perPage={20}
        total={missing.total}
        empty={t('No 404s match this search. A 404 does not create a redirect.')}
        previousLabel={t('Previous 404 page')}
        nextLabel={t('Next 404 page')}
        onPage={(page) => void loadMissing(page)}
      />

      <h3>{t('Permalink change')}</h3>
      <p>{t('A redirect is not created until you confirm it.')}</p>
      <label>
        {t('From')}
        <input value={from} onChange={(event) => setFrom(event.target.value)} />
      </label>
      <label>
        {t('To')}
        <input value={to} onChange={(event) => setTo(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={confirmed} onChange={(event) => setConfirmed(event.target.checked)} />
        {t('Confirm redirect')}
      </label>
      <button type="button" onClick={() => void savePermalink()}>{t('Store permalink redirect')}</button>

      <h3>{t('robots.txt')}</h3>
      <p>{t(ROBOTS_NOTE)}</p>
      <label>
        {t('robots.txt contents')}
        <textarea value={robots} onChange={(event) => setRobots(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={allowFile} onChange={(event) => setAllowFile(event.target.checked)} />
        {t('Allow a physical file write')}
      </label>
      <label>
        <input type="checkbox" checked={robotsConfirm} onChange={(event) => setRobotsConfirm(event.target.checked)} />
        {t('Confirm robots.txt write')}
      </label>
      <button type="button" onClick={() => void saveRobots()}>{t('Store robots.txt')}</button>

      <h3>{t('Breadcrumbs')}</h3>
      <p>{t('Use querynova_breadcrumbs(), the querynova_breadcrumbs shortcode, or the querynova/breadcrumbs block. BreadcrumbList is available from the preview. Pages are not changed automatically.')}</p>
      <label>
        {t('Separator')}
        <input value={separator} onChange={(event) => setSeparator(event.target.value)} />
      </label>
      <label>
        {t('Home label')}
        <input value={home} onChange={(event) => setHome(event.target.value)} />
      </label>
      <button type="button" onClick={() => void saveBreadcrumbs()}>{t('Store breadcrumb settings')}</button>

      <h3>{t('RSS')}</h3>
      <label>
        <input type="checkbox" checked={rssEnabled} onChange={(event) => setRssEnabled(event.target.checked)} />
        {t('Add text around RSS content')}
      </label>
      <label>
        {t('Before')}
        <textarea value={before} onChange={(event) => setBefore(event.target.value)} />
      </label>
      <label>
        {t('After')}
        <textarea value={after} onChange={(event) => setAfter(event.target.value)} />
      </label>
      <button type="button" onClick={() => void saveRss()}>{t('Store RSS text')}</button>

      <h3>{t('IndexNow')}</h3>
      <p>{t('IndexNow did not send a request. noindex URLs are skipped.')}</p>
      <label>
        {t('URLs, one per line')}
        <textarea value={indexUrls} onChange={(event) => setIndexUrls(event.target.value)} />
      </label>
      <label>
        {t('noindex URLs, one per line')}
        <textarea value={noindexUrls} onChange={(event) => setNoindexUrls(event.target.value)} />
      </label>
      <button type="button" onClick={planIndexNow}>{t('Plan IndexNow')}</button>

      <h3>{t('Link settings')}</h3>
      <p>{t('New tab, nofollow, and automatic insertion stay off until you confirm. Nothing is inserted.')}</p>
      <label>
        <input type="checkbox" checked={newTab} onChange={(event) => setNewTab(event.target.checked)} />
        {t('Open in a new tab')}
      </label>
      <label>
        <input type="checkbox" checked={nofollow} onChange={(event) => setNofollow(event.target.checked)} />
        {t('Add nofollow')}
      </label>
      <label>
        <input type="checkbox" checked={autoInsert} onChange={(event) => setAutoInsert(event.target.checked)} />
        {t('Insert links automatically')}
      </label>
      <label>
        <input type="checkbox" checked={linkConfirm} onChange={(event) => setLinkConfirm(event.target.checked)} />
        {t('Confirm link settings')}
      </label>
      <button type="button" onClick={() => void saveLinkSettings()}>{t('Store link settings')}</button>

      <h3>{t('Podcast')}</h3>
      <p>{t('Podcast stays off until this option is exactly true. Nothing is published.')}</p>
      <label>
        <input type="checkbox" checked={podcastEnabled} onChange={(event) => setPodcastEnabled(event.target.checked)} />
        {t('Enable podcast')}
      </label>
      <label>
        <input type="checkbox" checked={podcastConfirm} onChange={(event) => setPodcastConfirm(event.target.checked)} />
        {t('Confirm podcast')}
      </label>
      <button type="button" onClick={() => void savePodcast()}>{t('Store podcast')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
      <Toast message={toast} />
    </section>
  );
}

function storedColumns() {
  return [
    { label: t('Source'), value: (row: Record<string, unknown>) => textCell(row, ['source', 'url']) },
    { label: t('Target'), value: (row: Record<string, unknown>) => textCell(row, ['target']) },
    { label: t('Status'), value: (row: Record<string, unknown>) => textCell(row, ['status']) },
  ];
}

interface ListPage {
  rows: Record<string, unknown>[];
  total: number;
  page: number;
  pages: number;
}

function readList(body: unknown): ListPage {
  const record = isRecord(body) ? body : {};
  const rows = Array.isArray(record.rows) ? record.rows.filter(isRecord) : [];
  return {
    rows,
    total: typeof record.total === 'number' ? record.total : 0,
    page: typeof record.page === 'number' ? record.page : 1,
    pages: typeof record.pages === 'number' ? record.pages : 0,
  };
}

function lines(value: string): string[] {
  return value.split('\n').map((line) => line.trim()).filter((line) => line !== '');
}

function textField(value: unknown, key: string): string {
  const record = isRecord(value) ? value : {};
  return typeof record[key] === 'string' ? record[key] : '';
}

function boolField(value: unknown, key: string): boolean {
  const record = isRecord(value) ? value : {};
  return record[key] === true;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
