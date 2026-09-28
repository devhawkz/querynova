import { describe, expect, it } from 'vitest';
import { pageWindow } from './data-table';

describe('data table', () => {
  it('renders one page of a stored list and keeps an already paged response', () => {
    const stored = Array.from({ length: 45 }, (_, index) => index + 1);
    const first = pageWindow(stored, 1, 20);
    const last = pageWindow(stored, 3, 20);
    const serverPage = pageWindow(stored.slice(20, 40), 2, 20, 45);

    expect(first.rows).toHaveLength(20);
    expect(first.total).toBe(45);
    expect(last.rows).toEqual([41, 42, 43, 44, 45]);
    expect(serverPage.rows).toHaveLength(20);
    expect(serverPage.rows[0]).toBe(21);
    expect(serverPage.total).toBe(45);
  });
});
