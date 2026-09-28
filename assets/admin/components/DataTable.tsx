import { t } from '../i18n';
import { pageWindow } from './data-table';

export interface DataColumn {
  label: string;
  value: (row: Record<string, unknown>) => string;
}

interface Props {
  caption: string;
  columns: DataColumn[];
  rows: Record<string, unknown>[];
  page: number;
  perPage: number;
  total: number;
  empty: string;
  previousLabel: string;
  nextLabel: string;
  onPage: (page: number) => void;
}

export function DataTable({ caption, columns, rows, page, perPage, total, empty, previousLabel, nextLabel, onPage }: Props) {
  const window = pageWindow(rows, page, perPage, total);

  return (
    <>
      {window.rows.length === 0 ? <p>{empty}</p> : (
        <table>
          <caption>{caption}</caption>
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.label} scope="col">{column.label}</th>
              ))}
            </tr>
          </thead>
          <tbody>
            {window.rows.map((row, index) => (
              <tr key={`${caption}-${index}`}>
                {columns.map((column) => (
                  <td key={column.label}>{column.value(row)}</td>
                ))}
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <p>{t('Page')} {window.page} / {window.pages} · {window.total}</p>
      <button type="button" onClick={() => onPage(Math.max(1, window.page - 1))}>{previousLabel}</button>
      <button type="button" onClick={() => onPage(window.page + 1)}>{nextLabel}</button>
    </>
  );
}
