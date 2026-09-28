import { describe, expect, it } from 'vitest';
import { fromPayload, schemaLoadMessage, toPayload } from './rules';

describe('schema rules', () => {
  it('drops unknown types and keeps a linked course', () => {
    const rules = fromPayload([
      { id: 'bad', type: 'RankWidget', id_template: 'https://example.test/#x', conditions: [], mappings: [] },
      {
        id: 'course-1',
        type: 'Course',
        id_template: '%%permalink%%#course',
        conditions: [{ source: 'custom', key: 'course_name', operator: 'exists', expected: '' }],
        mappings: [{ property: 'provider', source: 'link', key: '%%site_url%%/#organization' }],
      },
    ]);

    expect(rules).toHaveLength(1);
    expect(rules[0]?.type).toBe('Course');
    expect(toPayload(rules).rules[0]).toMatchObject({
      type: 'Course',
      id_template: '%%permalink%%#course',
    });
  });

  it('rejects a property name that could change the json-ld context', () => {
    const rules = fromPayload([
      {
        id: 'course-1',
        type: 'Course',
        id_template: '%%permalink%%#course',
        conditions: [],
        mappings: [{ property: '@context', source: 'literal', key: 'https://evil.example' }],
      },
    ]);

    expect(rules).toEqual([]);
  });

  it('keeps the server status when a refresh fails after stored rules were provided', () => {
    const message = schemaLoadMessage(
      { status: 401, message: 'Sorry, you are not allowed to do that.', errorReference: 'QN-AB12CD34' },
      true,
    );

    expect(message).toContain('Stored schema rules are shown.');
    expect(message).toContain('HTTP 401');
    expect(message).toContain('Sorry, you are not allowed to do that.');
    expect(message).toContain('QN-AB12CD34');
    expect(schemaLoadMessage({}, false)).toBe('Schema rules could not be loaded.');
  });
});
