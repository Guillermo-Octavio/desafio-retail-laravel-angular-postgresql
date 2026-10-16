import { Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';
import { BUSINESS_TIME_ZONE_LABEL, formatBusinessDateTime } from '../data/business-time';
import { formatAmount } from '../data/money';
import { TransactionSummary } from '../data/transaction.models';
import { StatusBadge } from './status-badge';

@Component({
  selector: 'app-transaction-table',
  imports: [RouterLink, StatusBadge],
  template: `
    <table class="w-full border-collapse text-left text-sm">
      <caption class="sr-only">
        Transactions, newest first
      </caption>
      <thead class="border-b border-slate-300 text-slate-800">
        <tr>
          <th scope="col" class="py-2 pr-4">Transaction</th>
          <th scope="col" class="py-2 pr-4">Merchant</th>
          <th scope="col" class="py-2 pr-4">Amount</th>
          <th scope="col" class="py-2 pr-4">Status</th>
          <th scope="col" class="py-2 pr-4">Reason</th>
          <th scope="col" class="py-2 pr-4">Card</th>
          <th scope="col" class="py-2">Created ({{ timeZone }})</th>
        </tr>
      </thead>
      <tbody>
        @for (transaction of transactions(); track transaction.transaction_id) {
          <tr class="border-b border-slate-200">
            <td class="py-2 pr-4 font-mono">
              <a
                class="text-blue-800 underline"
                [routerLink]="['/transactions', transaction.transaction_id]"
              >
                {{ transaction.transaction_id }}
              </a>
            </td>
            <td class="py-2 pr-4">{{ transaction.merchant_id }}</td>
            <td class="py-2 pr-4 tabular-nums">{{ amount(transaction) }}</td>
            <td class="py-2 pr-4"><app-status-badge [status]="transaction.status" /></td>
            <td class="py-2 pr-4">{{ transaction.decline_reason ?? '—' }}</td>
            <td class="py-2 pr-4">
              {{ transaction.card.brand }} <span aria-hidden="true">••••</span
              ><span class="sr-only">ending in</span>
              {{ transaction.card.last4 }}
            </td>
            <td class="py-2 tabular-nums">
              {{ dateTime(transaction.created_at) }}
            </td>
          </tr>
        }
      </tbody>
    </table>
  `,
})
export class TransactionTable {
  readonly transactions = input.required<TransactionSummary[]>();

  protected readonly timeZone = BUSINESS_TIME_ZONE_LABEL;

  protected dateTime(isoInstant: string): string {
    return formatBusinessDateTime(isoInstant);
  }

  protected amount(transaction: TransactionSummary): string {
    return formatAmount(transaction.amount, transaction.currency);
  }
}
