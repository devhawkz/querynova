export const REPORT_KINDS = ['organic', 'content', 'keyword', 'rank', 'index', 'woocommerce', 'ai_visibility'] as const;

export function reportFormat(format: string): { format: 'csv' | 'json'; pdf: false; note: string } {
  return {
    format: format === 'csv' ? 'csv' : 'json',
    pdf: false,
    note: 'PDF is not generated.',
  };
}

export function schedulePlan(recipients: string[], kind: string, confirmed: boolean): {
  stored: boolean;
  sent: false;
  customers: false;
  note: string;
} {
  const emails = recipients.map((email) => email.trim()).filter((email) => email.includes('@'));
  if (!confirmed || emails.length === 0 || kind.trim() === '') {
    return {
      stored: false,
      sent: false,
      customers: false,
      note: 'The schedule is not stored until you confirm. No email was sent.',
    };
  }
  return {
    stored: true,
    sent: false,
    customers: false,
    note: 'The schedule is stored. No email was sent. The report uses aggregate metrics and does not include customer records.',
  };
}

export function whiteLabelPlan(): { hides_security: false; security: string } {
  return {
    hides_security: false,
    security: 'WordPress security notices stay visible. White label does not hide them.',
  };
}

export function importPreviewNote(): string {
  return 'Preview only. Nothing was written and the other plugin was not disabled.';
}

export function settingsImport(confirmed: boolean): { stored: boolean; changed_posts: false; note: string } {
  if (!confirmed) {
    return {
      stored: false,
      changed_posts: false,
      note: 'Settings import stays off until you confirm. Posts were not changed.',
    };
  }
  return {
    stored: true,
    changed_posts: false,
    note: 'That section was stored. Posts were not changed.',
  };
}
