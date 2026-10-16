import { TransactionEvent } from './transaction.models';

/** One readable line per event of the transaction history (HU-09). */
export function describeEvent(event: TransactionEvent): string {
  if (event.type === 'STATUS_CHANGED') {
    const transition = `${event.from_status ?? 'Creada'} → ${event.to_status}`;

    return event.reason === null ? transition : `${transition} (${event.reason})`;
  }

  const attempt = event.metadata?.['attempt'];
  const outcome = event.metadata?.['outcome'];

  if (attempt === undefined || outcome === undefined) {
    return 'Intento al adquirente';
  }

  const duration = event.metadata?.['duration_ms'];
  const errorCode = event.metadata?.['error_code'];
  const timing = duration === undefined ? '' : ` en ${duration} ms`;

  return `Intento ${attempt}: ${outcome}${timing}${errorCode === undefined ? '' : ` (${errorCode})`}`;
}
