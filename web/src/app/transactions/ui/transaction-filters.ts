import { Component, input, output } from '@angular/core';
import { TRANSACTION_STATUSES, TransactionStatus } from '../data/transaction.models';

export interface TransactionFiltersValue {
  merchantId: string;
  status: TransactionStatus | null;
}

@Component({
  selector: 'app-transaction-filters',
  template: `
    <form
      class="flex flex-wrap items-end gap-4"
      role="search"
      aria-label="Filter transactions"
      (submit)="submit($event, merchant.value, statusSelect.value)"
    >
      <div class="flex flex-col gap-1">
        <label for="merchant-filter" class="text-sm font-medium text-slate-800">Merchant</label>
        <input
          #merchant
          id="merchant-filter"
          type="search"
          class="rounded border border-slate-500 px-3 py-2"
          placeholder="merchant-001"
          [value]="merchantId()"
        />
      </div>
      <div class="flex flex-col gap-1">
        <label for="status-filter" class="text-sm font-medium text-slate-800">Status</label>
        <select
          #statusSelect
          id="status-filter"
          class="rounded border border-slate-500 px-3 py-2"
          (change)="apply(merchant.value, statusSelect.value)"
        >
          <option value="" [selected]="status() === null">All</option>
          @for (option of statuses; track option) {
            <option [value]="option" [selected]="option === status()">{{ option }}</option>
          }
        </select>
      </div>
      <button type="submit" class="rounded bg-slate-800 px-4 py-2 font-medium text-white">
        Search
      </button>
    </form>
  `,
})
export class TransactionFilters {
  readonly merchantId = input.required<string>();
  readonly status = input.required<TransactionStatus | null>();
  readonly filtersChange = output<TransactionFiltersValue>();

  protected readonly statuses = TRANSACTION_STATUSES;

  protected submit(event: Event, merchantId: string, status: string): void {
    event.preventDefault();
    this.apply(merchantId, status);
  }

  protected apply(merchantId: string, status: string): void {
    this.filtersChange.emit({
      merchantId: merchantId.trim(),
      status: TRANSACTION_STATUSES.find((option) => option === status) ?? null,
    });
  }
}
