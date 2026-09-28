import { useMemo, useState } from 'react';
import { QueryNovaApi } from '../../core/api/client';
import { t } from '../../i18n';
import { localPlan, locationPlan } from './model';

interface Props {
  restUrl: string;
  nonce: string;
  localWorkspace: unknown;
}

export function LocalScreen({ restUrl, nonce, localWorkspace }: Props) {
  const api = useMemo(() => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }), [restUrl, nonce]);
  const stored = isRecord(localWorkspace) ? localWorkspace : {};
  const [moduleOn, setModuleOn] = useState(stored.enabled === true);
  const [enabled, setEnabled] = useState(stored.enabled === true);
  const [confirm, setConfirm] = useState(false);
  const [name, setName] = useState('');
  const [address, setAddress] = useState('');
  const [locationConfirm, setLocationConfirm] = useState(false);
  const [locations, setLocations] = useState<LocationRow[]>(() => locationRows(stored.locations));
  const [message, setMessage] = useState('');
  const ready = restUrl !== '' && nonce !== '';

  async function saveModule() {
    const plan = localPlan(enabled, confirm);
    if (!confirm || !ready) {
      setMessage(t(plan.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/local', { enabled, confirmed: true });
      setModuleOn(body.enabled === true);
      setMessage(typeof body.note === 'string' ? body.note : t(plan.note));
    } catch {
      setMessage(t('Local SEO was not changed. Live URLs were not changed.'));
    }
  }

  async function saveLocation() {
    const plan = locationPlan(name, locationConfirm, moduleOn);
    if (!plan.stored || !ready) {
      setMessage(t(plan.note));
      return;
    }
    try {
      const body = await api.post<Record<string, unknown>>('/local/locations', {
        name,
        address,
        confirmed: true,
      });
      setLocations(locationRows(body.locations));
      setMessage(typeof body.note === 'string' ? body.note : t(plan.note));
    } catch {
      setMessage(t('The location was not stored. Live URLs were not changed.'));
    }
  }

  return (
    <section aria-labelledby="qn-local">
      <h2 id="qn-local">{t('Local SEO')}</h2>
      <p>{t('Local SEO stays off until the option is exactly true. Live URLs stay unchanged.')}</p>
      <label>
        <input type="checkbox" checked={enabled} onChange={(event) => setEnabled(event.target.checked)} />
        {t('Enable local SEO')}
      </label>
      <label>
        <input type="checkbox" checked={confirm} onChange={(event) => setConfirm(event.target.checked)} />
        {t('Confirm local SEO')}
      </label>
      <button type="button" onClick={() => void saveModule()}>{t('Store local SEO')}</button>
      {moduleOn ? (
        <>
          <h3>{t('Locations')}</h3>
          <p>{t('Locations are stored only after you confirm. A missing address stays empty. Live URLs were not changed.')}</p>
          {locations.length === 0 ? <p>{t('No stored locations. Live URLs were not changed.')}</p> : (
            <table>
              <caption>{t('Stored locations')}</caption>
              <thead>
                <tr>
                  <th scope="col">{t('Name')}</th>
                  <th scope="col">{t('Address')}</th>
                </tr>
              </thead>
              <tbody>
                {locations.map((row, index) => (
                  <tr key={`${row.name}-${index}`}>
                    <td>{row.name}</td>
                    <td>{row.address ?? t('Empty')}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
          <label>
            {t('Location name')}
            <input value={name} onChange={(event) => setName(event.target.value)} />
          </label>
          <label>
            {t('Address')}
            <input value={address} onChange={(event) => setAddress(event.target.value)} />
          </label>
          <label>
            <input type="checkbox" checked={locationConfirm} onChange={(event) => setLocationConfirm(event.target.checked)} />
            {t('Confirm location')}
          </label>
          <button type="button" onClick={() => void saveLocation()}>{t('Store location')}</button>
        </>
      ) : (
        <p>{t('The locations workspace stays hidden until local SEO is enabled.')}</p>
      )}
      {message === '' ? null : <p role="status">{message}</p>}
    </section>
  );
}

interface LocationRow {
  name: string;
  address: string | null;
}

function locationRows(input: unknown): LocationRow[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.flatMap((row) => {
    if (!isRecord(row) || typeof row.name !== 'string' || row.name === '') {
      return [];
    }
    return [{ name: row.name, address: typeof row.address === 'string' && row.address !== '' ? row.address : null }];
  });
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
