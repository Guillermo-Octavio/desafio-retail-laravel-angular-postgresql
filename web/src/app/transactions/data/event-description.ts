import { TransactionEvent } from './transaction.models';

/** One readable line per event of the transaction history (HU-09). */
export function describeEvent(event: TransactionEvent): string {
  if (event.type === 'STATUS_CHANGED') {
    const transition = `${event.from_status ?? 'Created'} → ${event.to_status}`;

    return event.reason === null ? transition : `${transition} (${event.reason})`;
  }

  const metadata = event.metadata;

  if (metadata === null) {
    return 'Acquirer attempt';
  }

  const attempt = `Attempt ${metadata['attempt']}: ${metadata['outcome']} in ${metadata['duration_ms']} ms`;

  return metadata['error_code'] === undefined ? attempt : `${attempt} (${metadata['error_code']})`;
}
