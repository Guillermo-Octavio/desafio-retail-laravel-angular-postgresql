import { Component, computed, inject, input } from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { TRANSACTION_STATUSES, TransactionQuery } from '../data/transaction.models';
import { TransactionsApi } from '../data/transactions-api';
import { LoadState, LoadStateKind } from '../ui/load-state';
import { Pagination } from '../ui/pagination';
import { TransactionFilters, TransactionFiltersValue } from '../ui/transaction-filters';
import { TransactionTable } from '../ui/transaction-table';

const FIRST_PAGE = 1;

/** Container: reads the filters from the URL, loads the page and coordinates the views (HU-12). */
@Component({
  selector: 'app-transactions-page',
  imports: [LoadState, Pagination, TransactionFilters, TransactionTable],
  template: `
    <section class="flex flex-col gap-6" aria-labelledby="transactions-heading">
      <h1 id="transactions-heading" class="text-2xl font-semibold text-slate-900">Transactions</h1>

      <app-transaction-filters
        [merchantId]="query().merchantId"
        [status]="query().status"
        (filtersChange)="applyFilters($event)"
      />

      @if (loadState(); as state) {
        <app-load-state [state]="state" (retry)="transactions.reload()" />
      } @else if (page(); as page) {
        <app-transaction-table [transactions]="page.data" />
        <app-pagination
          [page]="page.meta.current_page"
          [lastPage]="page.meta.last_page"
          (pageChange)="goToPage($event)"
        />
      }
    </section>
  `,
})
export class TransactionsPage {
  // Bound from the URL query parameters, so a search survives a reload and can be shared.
  readonly merchantIdParam = input<string>(undefined, { alias: 'merchant_id' });
  readonly statusParam = input<string>(undefined, { alias: 'status' });
  readonly pageParam = input<string>(undefined, { alias: 'page' });

  private readonly api = inject(TransactionsApi);
  private readonly router = inject(Router);

  protected readonly query = computed<TransactionQuery>(() => ({
    merchantId: this.merchantIdParam() ?? '',
    status: TRANSACTION_STATUSES.find((status) => status === this.statusParam()) ?? null,
    page: Math.max(FIRST_PAGE, Number.parseInt(this.pageParam() ?? '', 10) || FIRST_PAGE),
  }));

  protected readonly transactions = rxResource({
    params: () => this.query(),
    stream: ({ params }) => this.api.list(params),
  });

  protected readonly page = computed(() =>
    this.transactions.hasValue() ? this.transactions.value() : undefined,
  );

  protected readonly loadState = computed<LoadStateKind | null>(() => {
    if (this.transactions.error()) {
      return 'error';
    }

    if (this.transactions.isLoading()) {
      return 'loading';
    }

    return this.page()?.data.length === 0 ? 'empty' : null;
  });

  protected applyFilters(filters: TransactionFiltersValue): void {
    this.navigate({ merchantId: filters.merchantId, status: filters.status, page: FIRST_PAGE });
  }

  protected goToPage(page: number): void {
    this.navigate({ ...this.query(), page });
  }

  private navigate(query: TransactionQuery): void {
    void this.router.navigate(['/transactions'], {
      queryParams: {
        merchant_id: query.merchantId || null,
        status: query.status,
        page: query.page === FIRST_PAGE ? null : query.page,
      },
    });
  }
}
