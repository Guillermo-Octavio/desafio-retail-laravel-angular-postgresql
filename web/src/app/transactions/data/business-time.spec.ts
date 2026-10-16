import {
  BUSINESS_TIME_ZONE_LABEL,
  formatBusinessDateTime,
  formatBusinessTime,
} from './business-time';

describe('business time', () => {
  it('shows an instant in Santiago time, following the offset of each season', () => {
    expect(formatBusinessDateTime('2026-10-15T12:00:00.000+00:00')).toBe('2026-10-15 09:00:00');
    expect(formatBusinessDateTime('2026-06-15T12:00:00.000+00:00')).toBe('2026-06-15 08:00:00');
  });

  it('gives the same result whatever offset the API used for the instant', () => {
    expect(formatBusinessDateTime('2026-10-15T09:00:00.000-03:00')).toBe('2026-10-15 09:00:00');
  });

  it('shows the time of an event with milliseconds', () => {
    expect(formatBusinessTime('2026-10-15T12:00:01.250+00:00')).toBe('09:00:01.250');
  });

  it('names the zone for the column labels', () => {
    expect(BUSINESS_TIME_ZONE_LABEL).toBe('Santiago');
  });
});
