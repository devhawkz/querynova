import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { REPORT_KINDS, importPreviewNote, reportFormat, schedulePlan, settingsImport, whiteLabelPlan } from './model';

interface Props {
  restUrl: string;
  nonce: string;
}

export function ReportsScreen({ restUrl, nonce }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const ready = restUrl !== '' && nonce !== '';
  const [kind, setKind] = useState<(typeof REPORT_KINDS)[number]>('organic');
  const [format, setFormat] = useState('json');
  const [recipients, setRecipients] = useState('');
  const [frequency, setFrequency] = useState('weekly');
  const [scheduleConfirm, setScheduleConfirm] = useState(false);
  const [brand, setBrand] = useState('');
  const [footer, setFooter] = useState('');
  const [sender, setSender] = useState('');
  const [logo, setLogo] = useState('');
  const [labelEnabled, setLabelEnabled] = useState(false);
  const [roleName, setRoleName] = useState('');
  const [roleArea, setRoleArea] = useState('seo');
  const [roleConfirm, setRoleConfirm] = useState(false);
  const [plugin, setPlugin] = useState('yoast');
  const [section, setSection] = useState('schedule');
  const [importConfirm, setImportConfirm] = useState(false);
  const [message, setMessage] = useState('');
  const security = whiteLabelPlan();

  async function renderReport() {
    const local = reportFormat(format);
    if (!ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/reports', { kind, format: local.format, metrics: {} });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The report was not rendered. PDF is not generated.'));
    }
  }

  async function saveSchedule() {
    const emails = recipients.split(',').map((email) => email.trim()).filter((email) => email !== '');
    const local = schedulePlan(emails, kind, scheduleConfirm);
    if (!scheduleConfirm || !ready) {
      setMessage(t(local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/reports/schedule', { recipients: emails, kind, frequency, confirmed: true });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('The schedule was not stored. No email was sent.'));
    }
  }

  async function saveLabel() {
    if (!ready) {
      setMessage(t(security.security));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/reports/white-label', { logo, brand, footer, sender, enabled: labelEnabled });
      setMessage(typeof body.security === 'string' ? body.security : t(security.security));
    } catch {
      setMessage(t(security.security));
    }
  }

  async function saveRole() {
    if (!roleConfirm || !ready) {
      setMessage(t('A custom role is not stored until you confirm a name and at least one area. WordPress roles were not changed.'));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/reports/roles', { name: roleName, areas: [roleArea], confirmed: true });
      setMessage(typeof body.note === 'string' ? body.note : t('WordPress roles were not changed.'));
    } catch {
      setMessage(t('The custom role was not stored. WordPress roles were not changed.'));
    }
  }

  async function previewImport() {
    if (!ready) {
      setMessage(t(importPreviewNote()));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/seo/import/preview', { plugin, objects: [] });
      setMessage(typeof body.note === 'string' ? body.note : t(importPreviewNote()));
    } catch {
      setMessage(t(importPreviewNote()));
    }
  }

  async function moveSettings(action: 'export' | 'import') {
    const local = settingsImport(importConfirm);
    if (action === 'import' && !importConfirm) {
      setMessage(t(local.note));
      return;
    }
    if (!ready) {
      setMessage(t(action === 'export' ? 'One section was exported. Secrets are not included.' : local.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>(action === 'export' ? '/reports/settings/export' : '/reports/settings/import', {
        section,
        payload: {},
        confirmed: action === 'import' && importConfirm,
      });
      setMessage(typeof body.note === 'string' ? body.note : t(local.note));
    } catch {
      setMessage(t('Settings were not changed. Posts were not changed.'));
    }
  }

  return (
    <section aria-labelledby="qn-reports">
      <h1 id="qn-reports">{t('Reports')}</h1>
      <p>{t('CSV and JSON only. PDF is not generated.')}</p>
      <label>
        {t('Report')}
        <select value={kind} onChange={(event) => setKind(event.target.value as (typeof REPORT_KINDS)[number])}>
          {REPORT_KINDS.map((id) => (
            <option key={id} value={id}>{id}</option>
          ))}
        </select>
      </label>
      <label>
        {t('Format')}
        <select value={format} onChange={(event) => setFormat(event.target.value)}>
          <option value="json">{t('JSON')}</option>
          <option value="csv">{t('CSV')}</option>
        </select>
      </label>
      <button type="button" onClick={() => void renderReport()}>{t('Render report')}</button>

      <h2>{t('Schedule')}</h2>
      <p>{t('No email is sent from this screen. Customer records are not included.')}</p>
      <label>
        {t('Recipients')}
        <input value={recipients} onChange={(event) => setRecipients(event.target.value)} />
      </label>
      <label>
        {t('Frequency')}
        <input value={frequency} onChange={(event) => setFrequency(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={scheduleConfirm} onChange={(event) => setScheduleConfirm(event.target.checked)} />
        {t('Confirm schedule')}
      </label>
      <button type="button" onClick={() => void saveSchedule()}>{t('Store schedule')}</button>

      <h2>{t('White label')}</h2>
      <p>{t(security.security)}</p>
      <label>
        {t('Logo')}
        <input value={logo} onChange={(event) => setLogo(event.target.value)} />
      </label>
      <label>
        {t('Brand name')}
        <input value={brand} onChange={(event) => setBrand(event.target.value)} />
      </label>
      <label>
        {t('Footer')}
        <input value={footer} onChange={(event) => setFooter(event.target.value)} />
      </label>
      <label>
        {t('Sender')}
        <input value={sender} onChange={(event) => setSender(event.target.value)} />
      </label>
      <label>
        <input type="checkbox" checked={labelEnabled} onChange={(event) => setLabelEnabled(event.target.checked)} />
        {t('Enable white label')}
      </label>
      <button type="button" onClick={() => void saveLabel()}>{t('Store white label')}</button>

      <h2>{t('Roles')}</h2>
      <p>{t('Administrator, SEO Manager, Content Editor, Commerce Manager, and Developer stay on the fixed map.')}</p>
      <label>
        {t('Custom role')}
        <input value={roleName} onChange={(event) => setRoleName(event.target.value)} />
      </label>
      <label>
        {t('Area')}
        <select value={roleArea} onChange={(event) => setRoleArea(event.target.value)}>
          <option value="seo">{t('SEO')}</option>
          <option value="analysis">{t('Analysis')}</option>
          <option value="commerce">{t('Commerce')}</option>
          <option value="analytics">{t('Analytics')}</option>
        </select>
      </label>
      <label>
        <input type="checkbox" checked={roleConfirm} onChange={(event) => setRoleConfirm(event.target.checked)} />
        {t('Confirm custom role')}
      </label>
      <button type="button" onClick={() => void saveRole()}>{t('Store custom role')}</button>

      <h2>{t('Import preview')}</h2>
      <label>
        {t('Plugin')}
        <select value={plugin} onChange={(event) => setPlugin(event.target.value)}>
          <option value="yoast">{t('Yoast')}</option>
          <option value="rankmath">{t('Rank Math')}</option>
          <option value="aioseo">{t('AIOSEO')}</option>
        </select>
      </label>
      <button type="button" onClick={() => void previewImport()}>{t('Preview import')}</button>

      <h2>{t('Settings transfer')}</h2>
      <label>
        {t('Section')}
        <select value={section} onChange={(event) => setSection(event.target.value)}>
          <option value="schedule">{t('Schedule')}</option>
          <option value="white_label">{t('White label')}</option>
          <option value="roles">{t('Roles')}</option>
        </select>
      </label>
      <label>
        <input type="checkbox" checked={importConfirm} onChange={(event) => setImportConfirm(event.target.checked)} />
        {t('Confirm settings import')}
      </label>
      <button type="button" onClick={() => void moveSettings('export')}>{t('Export section')}</button>
      <button type="button" onClick={() => void moveSettings('import')}>{t('Import section')}</button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </section>
  );
}
