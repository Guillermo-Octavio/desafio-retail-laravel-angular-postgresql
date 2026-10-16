import { HttpErrorResponse, HttpStatusCode } from '@angular/common/http';
import { Component, computed, inject, input } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { RouterLink } from '@angular/router';
import { TransactionsApi } from '../data/transactions-api';
import { LoadState } from '../ui/load-state';
import { TransactionDetail } from '../ui/transaction-detail';

/** Container: loads one transaction with its history (HU-07, HU-09). */
@Component({
  selector: 'app-transaction-detail-page',
  imports: [LoadState, RouterLink, TransactionDetail],
  template: `
    <div class="flex flex-col gap-6">
      <a routerLink="/transactions" class="self-start text-blue-800 underline"
        >← Back to transactions</a
      >

      @if (notFound()) {
        <p role="alert" class="rounded border border-amber-300 bg-amber-50 p-4 text-amber-900">
          This transaction does not exist.
        </p>
      } @else if (transaction.error()) {
        <app-load-state state="error" (retry)="transaction.reload()" />
      } @else if (transaction.isLoading()) {
        <app-load-state state="loading" />
      } @else if (details(); as details) {
        <app-transaction-detail [transaction]="details" />
      }
    </div>
  `,
})
export class TransactionDetailPage {
  // Filled from the :id route parameter by withComponentInputBinding, so no ActivatedRoute is needed.
  readonly id = input.required<string>();

  private readonly api = inject(TransactionsApi);

  protected readonly transaction = rxResource({
    params: () => this.id(),
    stream: ({ params }) => this.api.get(params),
  });

  protected readonly details = computed(() =>
    this.transaction.hasValue() ? this.transaction.value() : undefined,
  );

  protected readonly notFound = computed(() => {
    const error = this.transaction.error();

    return error instanceof HttpErrorResponse && error.status === HttpStatusCode.NotFound;
  });
}
