import { BUSINESS_TIME_LABEL, formatApiDateTime, formatApiTime } from './api-time';

describe('API time', () => {
  it('shows the wall-clock time the API sent, in the business timezone, with its offset', () => {
    expect(formatApiDateTime('2026-10-15T09:00:00.936-03:00')).toBe(
      '2026-10-15 09:00:00 (UTC-03:00)',
    );
    expect(formatApiDateTime('2026-06-15T08:00:00.000-04:00')).toBe(
      '2026-06-15 08:00:00 (UTC-04:00)',
    );
  });

  it('keeps the milliseconds when asked to', () => {
    expect(formatApiDateTime('2026-10-15T09:00:00.936-03:00', { milliseconds: true })).toBe(
      '2026-10-15 09:00:00.936 (UTC-03:00)',
    );
  });

  it('shows the time of an event with milliseconds', () => {
    expect(formatApiTime('2026-10-15T09:00:01.250-03:00')).toBe('09:00:01.250');
  });

  it('follows whatever business timezone the API is configured with', () => {
    expect(formatApiDateTime('2026-10-15T12:00:00.000+00:00')).toBe(
      '2026-10-15 12:00:00 (UTC+00:00)',
    );
  });

  it('shows an unexpected value unchanged instead of guessing', () => {
    expect(formatApiDateTime('not a date')).toBe('not a date');
    expect(formatApiTime('not a date')).toBe('not a date');
  });

  it('names the column the same whatever the configured zone is', () => {
    expect(BUSINESS_TIME_LABEL).toBe('business time');
  });
});
