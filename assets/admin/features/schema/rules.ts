import { apiErrorDetail } from '../../core/api/client';

export const SCHEMA_TYPES = [
  'WebSite',
  'Organization',
  'Person',
  'WebPage',
  'CollectionPage',
  'Article',
  'BlogPosting',
  'BreadcrumbList',
  'Product',
  'ProductGroup',
  'Offer',
  'FAQPage',
  'AggregateOffer',
  'AggregateRating',
  'Review',
  'Brand',
  'Service',
  'LocalBusiness',
  'MedicalOrganization',
  'Physician',
  'SoftwareApplication',
  'Course',
  'Event',
  'VideoObject',
] as const;

export const SCHEMA_SOURCES = ['wordpress', 'woocommerce', 'custom', 'template', 'literal', 'link'] as const;

export const SCHEMA_OPERATORS = ['exists', 'missing', 'equals', 'not_equals'] as const;

export interface PropertyMapping {
  property: string;
  source: (typeof SCHEMA_SOURCES)[number];
  key: string;
}

export interface RuleCondition {
  source: 'wordpress' | 'woocommerce' | 'custom' | 'template' | 'literal';
  key: string;
  operator: (typeof SCHEMA_OPERATORS)[number];
  expected: string;
}

export interface SchemaRule {
  id: string;
  type: (typeof SCHEMA_TYPES)[number];
  idTemplate: string;
  conditions: RuleCondition[];
  mappings: PropertyMapping[];
}

export function emptyRule(type: (typeof SCHEMA_TYPES)[number] = 'Course'): SchemaRule {
  return {
    id: `rule-${crypto.randomUUID()}`,
    type,
    idTemplate: '%%permalink%%#entity',
    conditions: [],
    mappings: [{ property: 'name', source: 'wordpress', key: 'title' }],
  };
}

export function schemaLoadMessage(error: unknown, hadStoredRules: boolean): string {
  const detail = apiErrorDetail(error);
  if (hadStoredRules) {
    const lead = 'Stored schema rules are shown. Refreshing them from the server failed.';
    return detail === '' ? lead : `${lead} ${detail}`;
  }
  const lead = 'Schema rules could not be loaded.';
  return detail === '' ? lead : `${lead} ${detail}`;
}

export function fromPayload(input: unknown): SchemaRule[] {
  if (!Array.isArray(input)) {
    return [];
  }
  const rules: SchemaRule[] = [];
  for (const row of input) {
    const rule = ruleFrom(row);
    if (rule) {
      rules.push(rule);
    }
  }
  return rules;
}

export function toPayload(rules: SchemaRule[]): { rules: object[] } {
  return {
    rules: rules.map((rule) => ({
      id: rule.id,
      type: rule.type,
      id_template: rule.idTemplate,
      conditions: rule.conditions
        .filter((condition) => condition.key !== '')
        .map((condition) => ({
        source: condition.source,
        key: condition.key,
        operator: condition.operator,
        expected: condition.expected,
      })),
      mappings: rule.mappings.map((mapping) => ({
        property: mapping.property,
        source: mapping.source,
        key: mapping.key,
      })),
    })),
  };
}

function ruleFrom(row: unknown): SchemaRule | null {
  if (!isRecord(row) || !isSchemaType(row.type) || typeof row.id !== 'string' || typeof row.id_template !== 'string') {
    return null;
  }
  if (!/^[a-z0-9_-]{1,64}$/.test(row.id) || row.id_template.trim() === '') {
    return null;
  }
  const conditions = conditionsFrom(row.conditions);
  const mappings = mappingsFrom(row.mappings);
  if (!conditions || !mappings) {
    return null;
  }
  return {
    id: row.id,
    type: row.type,
    idTemplate: row.id_template,
    conditions,
    mappings,
  };
}

function conditionsFrom(input: unknown): RuleCondition[] | null {
  if (!Array.isArray(input)) {
    return null;
  }
  const conditions: RuleCondition[] = [];
  for (const row of input) {
    if (!isRecord(row) || !isConditionSource(row.source) || !isOperator(row.operator)) {
      return null;
    }
    if (typeof row.key !== 'string' || typeof row.expected !== 'string') {
      return null;
    }
    conditions.push({
      source: row.source,
      key: row.key,
      operator: row.operator,
      expected: row.expected,
    });
  }
  return conditions;
}

function mappingsFrom(input: unknown): PropertyMapping[] | null {
  if (!Array.isArray(input)) {
    return null;
  }
  const mappings: PropertyMapping[] = [];
  for (const row of input) {
    if (!isRecord(row) || typeof row.property !== 'string' || !isSource(row.source) || typeof row.key !== 'string') {
      return null;
    }
    if (!/^[A-Za-z][A-Za-z0-9]*$/.test(row.property)) {
      return null;
    }
    mappings.push({ property: row.property, source: row.source, key: row.key });
  }
  return mappings;
}

function isSchemaType(value: unknown): value is SchemaRule['type'] {
  return typeof value === 'string' && SCHEMA_TYPES.some((type) => type === value);
}

function isSource(value: unknown): value is PropertyMapping['source'] {
  return typeof value === 'string' && SCHEMA_SOURCES.some((source) => source === value);
}

function isConditionSource(value: unknown): value is RuleCondition['source'] {
  return value === 'wordpress' || value === 'woocommerce' || value === 'custom' || value === 'template' || value === 'literal';
}

function isOperator(value: unknown): value is RuleCondition['operator'] {
  return typeof value === 'string' && SCHEMA_OPERATORS.some((operator) => operator === value);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null;
}
