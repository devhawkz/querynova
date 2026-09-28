export interface PageWindow<T> {
  rows: T[];
  page: number;
  perPage: number;
  total: number;
  pages: number;
}

export function pageWindow<T>(rows: readonly T[], page: number, perPage: number, total?: number): PageWindow<T> {
  const size = Math.max(1, Math.min(100, Math.floor(perPage) || 1));
  const current = Math.max(1, Math.floor(page) || 1);
  const count = typeof total === 'number' && total >= rows.length ? total : rows.length;
  const visible = rows.length > size ? rows.slice((current - 1) * size, current * size) : rows.slice(0, size);

  return {
    rows: [...visible],
    page: current,
    perPage: size,
    total: count,
    pages: Math.max(1, Math.ceil(count / size)),
  };
}

export function textCell(row: Record<string, unknown>, keys: string[]): string {
  for (const key of keys) {
    const value = row[key];
    if (typeof value === 'string' || typeof value === 'number') {
      return String(value);
    }
  }
  return '';
}
