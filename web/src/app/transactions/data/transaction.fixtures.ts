import { TransactionDetails, TransactionPage, TransactionSummary } from './transaction.models';

/** Test data shaped like real API responses. */
export function aTransaction(overrides: Partial<TransactionSummary> = {}): TransactionSummary {
  return {
    transaction_id: '01a14306-750b-7019-8c17-1cfe1d981425',
    merchant_id: 'merchant-001',
    amount: 15000,
    currency: 'CLP',
    status: 'APPROVED',
    decline_reason: null,
    card: { brand: 'VISA', last4: '1111' },
    created_at: '2026-10-15T12:00:00.000+00:00',
    updated_at: '2026-10-15T12:00:01.000+00:00',
    ...overrides,
  };
}

export function aTransactionPage(
  data: TransactionSummary[] = [aTransaction()],
  page = 1,
  lastPage = 1,
): TransactionPage {
  return {
    data,
    meta: { current_page: page, per_page: 20, total: data.length, last_page: lastPage },
  };
}

export function aTransactionDetails(
  overrides: Partial<TransactionDetails> = {},
): TransactionDetails {
  return {
    ...aTransaction(),
    events: [
      {
        type: 'STATUS_CHANGED',
        from_status: null,
        to_status: 'PENDING',
        reason: null,
        metadata: null,
        correlation_id: 'corr-123',
        occurred_at: '2026-10-15T12:00:00.000+00:00',
      },
      {
        type: 'STATUS_CHANGED',
        from_status: 'PENDING',
        to_status: 'PROCESSING',
        reason: null,
        metadata: null,
        correlation_id: 'corr-123',
        occurred_at: '2026-10-15T12:00:00.100+00:00',
      },
      {
        type: 'ACQUIRER_ATTEMPT',
        from_status: null,
        to_status: null,
        reason: null,
        metadata: {
          attempt: 1,
          outcome: 'TEMPORARY_ERROR',
          duration_ms: 200,
          error_code: 'HTTP_503',
        },
        correlation_id: 'corr-123',
        occurred_at: '2026-10-15T12:00:00.300+00:00',
      },
      {
        type: 'ACQUIRER_ATTEMPT',
        from_status: null,
        to_status: null,
        reason: null,
        metadata: { attempt: 2, outcome: 'APPROVED', duration_ms: 85 },
        correlation_id: 'corr-123',
        occurred_at: '2026-10-15T12:00:00.700+00:00',
      },
      {
        type: 'STATUS_CHANGED',
        from_status: 'PROCESSING',
        to_status: 'APPROVED',
        reason: null,
        metadata: null,
        correlation_id: 'corr-123',
        occurred_at: '2026-10-15T12:00:01.000+00:00',
      },
    ],
    ...overrides,
  };
}
