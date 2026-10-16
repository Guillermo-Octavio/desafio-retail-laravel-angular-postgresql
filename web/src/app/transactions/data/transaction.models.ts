/**
 * Types of the payments API contract (docs/02-user-stories.md, HU-01, HU-07 to HU-09).
 * Field names mirror the API, which uses snake_case.
 */
export const TRANSACTION_STATUSES = [
  'PENDING',
  'PROCESSING',
  'APPROVED',
  'DECLINED',
  'FAILED',
] as const;

/** The API starts counting pages at 1 and uses it when no page is sent. */
export const FIRST_PAGE = 1;

export type TransactionStatus = (typeof TRANSACTION_STATUSES)[number];

export type CurrencyCode = 'CLP' | 'USD';

export interface MaskedCard {
  brand: string;
  last4: string;
}

export interface TransactionSummary {
  transaction_id: string;
  merchant_id: string;
  amount: number;
  currency: CurrencyCode;
  status: TransactionStatus;
  decline_reason: string | null;
  card: MaskedCard;
  created_at: string;
  updated_at: string;
}

export type TransactionEventType = 'STATUS_CHANGED' | 'ACQUIRER_ATTEMPT';

export interface TransactionEvent {
  type: TransactionEventType;
  from_status: TransactionStatus | null;
  to_status: TransactionStatus | null;
  reason: string | null;
  metadata: Record<string, string | number> | null;
  correlation_id: string;
  occurred_at: string;
}

export interface TransactionDetails extends TransactionSummary {
  events: TransactionEvent[];
}

export interface PageMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface TransactionPage {
  data: TransactionSummary[];
  meta: PageMeta;
}

export interface TransactionQuery {
  merchantId: string;
  status: TransactionStatus | null;
  page: number;
}
