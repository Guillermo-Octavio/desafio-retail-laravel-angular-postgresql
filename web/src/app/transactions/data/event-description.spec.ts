import { describeEvent } from './event-description';
import { TransactionEvent } from './transaction.models';

function anEvent(overrides: Partial<TransactionEvent>): TransactionEvent {
  return {
    type: 'STATUS_CHANGED',
    from_status: null,
    to_status: 'PENDING',
    reason: null,
    metadata: null,
    correlation_id: 'corr-123',
    occurred_at: '2026-10-15T12:00:00.000+00:00',
    ...overrides,
  };
}

describe('describeEvent', () => {
  it('describes a status change, its creation and its reason', () => {
    expect(describeEvent(anEvent({}))).toBe('Creada → PENDING');
    expect(
      describeEvent(
        anEvent({ from_status: 'PENDING', to_status: 'DECLINED', reason: 'CARD_EXPIRED' }),
      ),
    ).toBe('PENDING → DECLINED (CARD_EXPIRED)');
  });

  it('describes an acquirer attempt with and without an error code', () => {
    const failed = anEvent({
      type: 'ACQUIRER_ATTEMPT',
      to_status: null,
      metadata: {
        attempt: 1,
        outcome: 'TEMPORARY_ERROR',
        duration_ms: 2001,
        error_code: 'TIMEOUT',
      },
    });
    const approved = anEvent({
      type: 'ACQUIRER_ATTEMPT',
      to_status: null,
      metadata: { attempt: 2, outcome: 'APPROVED', duration_ms: 85 },
    });

    expect(describeEvent(failed)).toBe('Intento 1: TEMPORARY_ERROR en 2001 ms (TIMEOUT)');
    expect(describeEvent(approved)).toBe('Intento 2: APPROVED en 85 ms');
  });

  it('never shows undefined values when an attempt has no metadata', () => {
    expect(
      describeEvent(anEvent({ type: 'ACQUIRER_ATTEMPT', to_status: null, metadata: null })),
    ).toBe('Intento al adquirente');
  });
});
