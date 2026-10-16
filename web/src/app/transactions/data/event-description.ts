import { TransactionEvent } from './transaction.models';

/** One readable line per event of the transaction history (HU-09). */
export function describeEvent(event: TransactionEvent): string {
  if (event.type === 'STATUS_CHANGED') {
    const transition = `${event.from_status ?? 'Created'} → ${event.to_status}`;

    return event.reason === null ? transition : `${transition} (${event.reason})`;
  }

  const attempt = event.metadata?.['attempt'];
  const outcome = event.metadata?.['outcome'];

  if (attempt === undefined || outcome === undefined) {
    return 'Acquirer attempt';
  }

  const duration = event.metadata?.['duration_ms'];
  const errorCode = event.metadata?.['error_code'];
  const timing = duration === undefined ? '' : ` in ${duration} ms`;

  return `Attempt ${attempt}: ${outcome}${timing}${errorCode === undefined ? '' : ` (${errorCode})`}`;
}
