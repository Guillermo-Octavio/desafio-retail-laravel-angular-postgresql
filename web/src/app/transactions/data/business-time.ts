/**
 * The business timezone of the payments service (PAYMENTS_BUSINESS_TIMEZONE in the API). Angular's
 * DatePipe only takes fixed offsets, and Santiago changes offset with the season, so Intl is used.
 */
export const BUSINESS_TIME_ZONE = 'America/Santiago';

export const BUSINESS_TIME_ZONE_LABEL = 'Santiago';

const DATE_TIME = new Intl.DateTimeFormat('en-CA', {
  timeZone: BUSINESS_TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  hourCycle: 'h23',
});

const TIME = new Intl.DateTimeFormat('en-CA', {
  timeZone: BUSINESS_TIME_ZONE,
  hour: '2-digit',
  minute: '2-digit',
  second: '2-digit',
  fractionalSecondDigits: 3,
  hourCycle: 'h23',
});

function parts(format: Intl.DateTimeFormat, isoInstant: string): Record<string, string> {
  return Object.fromEntries(
    format.formatToParts(new Date(isoInstant)).map((part) => [part.type, part.value]),
  );
}

/** `yyyy-MM-dd HH:mm:ss` in Santiago time. */
export function formatBusinessDateTime(isoInstant: string): string {
  const { year, month, day, hour, minute, second } = parts(DATE_TIME, isoInstant);

  return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
}

/** `HH:mm:ss.SSS` in Santiago time. */
export function formatBusinessTime(isoInstant: string): string {
  const { hour, minute, second, fractionalSecond } = parts(TIME, isoInstant);

  return `${hour}:${minute}:${second}.${fractionalSecond}`;
}
