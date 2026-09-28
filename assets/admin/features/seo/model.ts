export const TEMPLATE_CONTEXTS = [
  'homepage',
  'post',
  'page',
  'product',
  'product_category',
  'tag',
  'category',
  'author',
  'archive',
  'cpt',
  'taxonomy',
  'location',
] as const;

export interface ReviewRow {
  status: string;
  explanation: string;
  evidence: string;
  howToFix: string;
  url: string;
}

export function checklistNote(input: unknown): string {
  const record = isRecord(input) ? input : {};
  return typeof record.note === 'string' && record.note !== '' ? record.note : 'This checklist does not predict rankings.';
}

export function auditNote(input: unknown): string {
  const record = isRecord(input) ? input : {};
  return typeof record.note === 'string' && record.note !== '' ? record.note : 'These findings do not estimate ranking impact.';
}

export function checklistRows(input: unknown): ReviewRow[] {
  const record = isRecord(input) ? input : {};
  return rows(record.checks);
}

export function auditFindings(input: unknown): ReviewRow[] {
  const record = isRecord(input) ? input : {};
  return rows(record.findings);
}

export function reportHasScore(input: unknown): boolean {
  return isRecord(input) && Object.prototype.hasOwnProperty.call(input, 'score');
}

export function statusLabel(status: string): string {
  if (status === 'passed') return 'Passed';
  if (status === 'warning') return 'Warning';
  if (status === 'failed') return 'Failed';
  if (status === 'info') return 'Info';
  return 'Info';
}

function rows(input: unknown): ReviewRow[] {
  if (!Array.isArray(input)) {
    return [];
  }
  return input.filter(isRecord).map((row) => ({
    status: typeof row.status === 'string' ? row.status : 'info',
    explanation: typeof row.explanation === 'string' ? row.explanation : '',
    evidence: typeof row.evidence === 'string' ? row.evidence : '',
    howToFix: typeof row.how_to_fix === 'string' ? row.how_to_fix : '',
    url: typeof row.url === 'string' ? row.url : '',
  }));
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
