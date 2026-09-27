import { ErrorBoundary } from '../components/ErrorBoundary';
import { WhatMattersNow, type TodayAction } from '../features/dashboard/WhatMattersNow';

declare global {
  interface Window {
    querynovaAdmin?: {
      wooCommerceActive?: boolean;
      actions?: TodayAction[];
    };
  }
}

export function App() {
  const boot = window.querynovaAdmin ?? {};
  return (
    <ErrorBoundary>
      <WhatMattersNow actions={boot.actions ?? []} wooCommerceActive={boot.wooCommerceActive === true} />
    </ErrorBoundary>
  );
}
