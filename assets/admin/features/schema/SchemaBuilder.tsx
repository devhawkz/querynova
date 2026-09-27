import { useEffect, useMemo, useState } from 'react';
import { t } from '../../i18n';
import { QueryNovaApi } from '../../core/api/client';
import {
  emptyRule,
  fromPayload,
  SCHEMA_OPERATORS,
  SCHEMA_SOURCES,
  SCHEMA_TYPES,
  toPayload,
  type SchemaRule,
} from './rules';

interface Props {
  restUrl: string;
  nonce: string;
}

export function SchemaBuilder({ restUrl, nonce }: Props) {
  const api = useMemo(
    () => new QueryNovaApi({ restUrl, nonce, version: '', environment: '' }),
    [restUrl, nonce],
  );
  const [rules, setRules] = useState<SchemaRule[]>([]);
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (restUrl === '' || nonce === '') {
      return;
    }
    let cancelled = false;
    api
      .get<{ rules: unknown }>('/schema/rules')
      .then((body) => {
        if (!cancelled) {
          setRules(fromPayload(body.rules));
        }
      })
      .catch(() => {
        if (!cancelled) {
          setMessage(t('Schema rules could not be loaded.'));
        }
      });
    return () => {
      cancelled = true;
    };
  }, [api, nonce, restUrl]);

  if (restUrl === '' || nonce === '') {
    return <p>{t('Schema rules are unavailable in this session.')}</p>;
  }

  function update(index: number, next: SchemaRule) {
    setRules((current) => current.map((rule, ruleIndex) => (ruleIndex === index ? next : rule)));
  }

  async function save() {
    try {
      const saved = await api.put<{ rules: unknown }>('/schema/rules', toPayload(rules));
      setRules(fromPayload(saved.rules));
      setMessage(t('Schema rules saved.'));
    } catch {
      setMessage(t('Schema rules could not be saved.'));
    }
  }

  return (
    <section aria-labelledby="qn-schema-builder">
      <h1 id="qn-schema-builder">{t('Schema builder')}</h1>
      <p>
        {t(
          'WebSite, Organization, WebPage, article, product, offer, and breadcrumb entities are connected automatically. Rules add more entities. Empty values are left out. They are not sent as zero.',
        )}
      </p>
      {rules.map((rule, index) => (
        <fieldset key={rule.id}>
          <legend>
            {t('Rule')} {index + 1}
          </legend>
          <label>
            {t('Schema type')}
            <select
              value={rule.type}
              onChange={(event) => {
                const type = SCHEMA_TYPES.find((item) => item === event.target.value) ?? rule.type;
                update(index, { ...rule, type });
              }}
            >
              {SCHEMA_TYPES.map((type) => (
                <option key={type} value={type}>
                  {t(type)}
                </option>
              ))}
            </select>
          </label>
          <label>
            {t('@id template')}
            <input
              value={rule.idTemplate}
              onChange={(event) => update(index, { ...rule, idTemplate: event.target.value })}
            />
          </label>
          {rule.mappings.map((mapping, mappingIndex) => (
            <div key={`${rule.id}-mapping-${mappingIndex}`}>
              <label>
                {t('Property')}
                <input
                  value={mapping.property}
                  onChange={(event) => {
                    const mappings = rule.mappings.map((item, itemIndex) =>
                      itemIndex === mappingIndex ? { ...item, property: event.target.value } : item,
                    );
                    update(index, { ...rule, mappings });
                  }}
                />
              </label>
              <label>
                {t('Source')}
                <select
                  value={mapping.source}
                  onChange={(event) => {
                    const source = SCHEMA_SOURCES.find((item) => item === event.target.value) ?? mapping.source;
                    const mappings = rule.mappings.map((item, itemIndex) =>
                      itemIndex === mappingIndex ? { ...item, source } : item,
                    );
                    update(index, { ...rule, mappings });
                  }}
                >
                  {SCHEMA_SOURCES.map((source) => (
                    <option key={source} value={source}>
                      {t(source)}
                    </option>
                  ))}
                </select>
              </label>
              <label>
                {t('Field, template, or value')}
                <input
                  value={mapping.key}
                  onChange={(event) => {
                    const mappings = rule.mappings.map((item, itemIndex) =>
                      itemIndex === mappingIndex ? { ...item, key: event.target.value } : item,
                    );
                    update(index, { ...rule, mappings });
                  }}
                />
              </label>
            </div>
          ))}
          <button
            type="button"
            onClick={() =>
              update(index, {
                ...rule,
                mappings: [...rule.mappings, { property: 'name', source: 'wordpress', key: 'title' }],
              })
            }
          >
            {t('Add property')}
          </button>
          <label>
            {t('Condition source')}
            <select
              value={rule.conditions[0]?.source ?? 'custom'}
              onChange={(event) => {
                const source = event.target.value;
                if (source !== 'wordpress' && source !== 'woocommerce' && source !== 'custom' && source !== 'template' && source !== 'literal') {
                  return;
                }
                update(index, {
                  ...rule,
                  conditions: [
                    {
                      source,
                      key: rule.conditions[0]?.key ?? '',
                      operator: rule.conditions[0]?.operator ?? 'exists',
                      expected: rule.conditions[0]?.expected ?? '',
                    },
                  ],
                });
              }}
            >
              <option value="wordpress">{t('wordpress')}</option>
              <option value="woocommerce">{t('woocommerce')}</option>
              <option value="custom">{t('custom')}</option>
              <option value="template">{t('template')}</option>
              <option value="literal">{t('literal')}</option>
            </select>
          </label>
          <label>
            {t('Condition field')}
            <input
              value={rule.conditions[0]?.key ?? ''}
              onChange={(event) =>
                update(index, {
                  ...rule,
                  conditions: [
                    {
                      source: rule.conditions[0]?.source ?? 'custom',
                      key: event.target.value,
                      operator: rule.conditions[0]?.operator ?? 'exists',
                      expected: rule.conditions[0]?.expected ?? '',
                    },
                  ],
                })
              }
            />
          </label>
          <label>
            {t('Condition')}
            <select
              value={rule.conditions[0]?.operator ?? 'exists'}
              onChange={(event) => {
                const operator = SCHEMA_OPERATORS.find((item) => item === event.target.value) ?? 'exists';
                update(index, {
                  ...rule,
                  conditions: [
                    {
                      source: rule.conditions[0]?.source ?? 'custom',
                      key: rule.conditions[0]?.key ?? '',
                      operator,
                      expected: rule.conditions[0]?.expected ?? '',
                    },
                  ],
                });
              }}
            >
              {SCHEMA_OPERATORS.map((operator) => (
                <option key={operator} value={operator}>
                  {t(operator)}
                </option>
              ))}
            </select>
          </label>
          <button type="button" onClick={() => setRules((current) => current.filter((item) => item.id !== rule.id))}>
            {t('Remove rule')}
          </button>
        </fieldset>
      ))}
      <button type="button" onClick={() => setRules((current) => [...current, emptyRule()])}>
        {t('Add schema type')}
      </button>
      <button type="button" onClick={() => void save()}>
        {t('Save schema rules')}
      </button>
      {message !== '' ? <p role="status">{message}</p> : null}
    </section>
  );
}
