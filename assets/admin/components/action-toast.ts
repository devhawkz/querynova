export function missingSessionToast(action: 'save' | 'queue'): string {
  if (action === 'queue') {
    return 'Not queued. The handler was not run.';
  }
  return 'Not saved. Live content was not changed.';
}
