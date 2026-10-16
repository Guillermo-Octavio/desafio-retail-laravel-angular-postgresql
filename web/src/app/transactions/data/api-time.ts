/**
 * The API sends every date already in the business timezone, with its offset
 * (PAYMENTS_BUSINESS_TIMEZONE, America/Santiago by default). The view shows that wall-clock
 * time as it comes, so it always matches the API's configuration without a zone of its own.
 */
export const BUSINESS_TIME_LABEL = 'hora del negocio';

const API_INSTANT = /^(\d{4}-\d{2}-\d{2})T(\d{2}:\d{2}:\d{2})\.(\d{3})([+-]\d{2}:\d{2})$/;

/** `yyyy-MM-dd HH:mm:ss (UTC±hh:mm)`, with `.SSS` after the seconds when asked. */
export function formatApiDateTime(
  isoInstant: string,
  options: { milliseconds?: boolean } = {},
): string {
  const match = API_INSTANT.exec(isoInstant);

  if (match === null) {
    return isoInstant;
  }

  const [, date, time, milliseconds, offset] = match;

  return `${date} ${time}${options.milliseconds ? `.${milliseconds}` : ''} (UTC${offset})`;
}

/** `HH:mm:ss.SSS`, for the events of one transaction. */
export function formatApiTime(isoInstant: string): string {
  const match = API_INSTANT.exec(isoInstant);

  return match === null ? isoInstant : `${match[2]}.${match[3]}`;
}
