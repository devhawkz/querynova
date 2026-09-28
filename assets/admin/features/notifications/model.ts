export const NOTIFICATION_NOTE = 'These notices stay inside QueryNova. WordPress security notices stay visible.';

export interface NoticeItem {
  id: string;
  level: 'warning' | 'info';
  message: string;
}

export function notificationFeed(input: unknown): { items: NoticeItem[]; hidesSecurity: false; note: string } {
  const record = isRecord(input) ? input : {};
  const raw = Array.isArray(record.items) ? record.items : [];
  const items = raw.flatMap((item, index) => {
    if (!isRecord(item) || typeof item.message !== 'string' || item.message.trim() === '') {
      return [];
    }
    const id = typeof item.id === 'string' && item.id !== '' ? item.id : `notice-${index}`;
    return [{
      id,
      level: item.level === 'warning' ? 'warning' as const : 'info' as const,
      message: item.message,
    }];
  });

  return {
    items,
    hidesSecurity: false,
    note: NOTIFICATION_NOTE,
  };
}

function isRecord(input: unknown): input is Record<string, unknown> {
  return typeof input === 'object' && input !== null;
}
