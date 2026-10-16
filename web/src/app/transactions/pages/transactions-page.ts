import {
  Component,
  computed,
  ElementRef,
  inject,
  input,
  linkedSignal,
  viewChild,
} from '@angular/core';
import { rxResource } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import {
  FIRST_PAGE,
  TRANSACTION_STATUSES,
  TransactionPage,
  TransactionQuery,
} from '../data/transaction.models';
import { TransactionsApi } from '../data/transactions-api';
import { LoadState, LoadStateKind } from '../ui/load-state';
import { Pagination } from '../ui/pagination';
import { TransactionFilters, TransactionFiltersValue } from '../ui/transaction-filters';
import { TransactionTable } from '../ui/transaction-table';

/** Container: reads the filters from the URL, loads the page and coordinates the views (HU-12). */
@Component({
  selector: 'app-transactions-page',
  imports: [LoadState, Pagination, TransactionFilters, TransactionTable],
  template: `
    <section class="flex flex-col gap-6" aria-labelledby="transactions-heading">
      <h1
        #heading
        id="transactions-heading"
        tabindex="-1"
        class="text-2xl font-semibold text-slate-900"
      >
        Transactions
      </h1>

      <app-transaction-filters
        [merchantId]="query().merchantId"
        [status]="query().status"
        (filtersChange)="applyFilters($event)"
      />

      <app-load-state [state]="loadState()" (retry)="retry()" (firstPage)="goToPage(firstPage)" />

      @if (visiblePage(); as page) {
        <div [attr.aria-busy]="transactions.isLoading()">
          <app-transaction-table [transactions]="page.data" />
        </div>
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
  private readonly heading = viewChild.required<ElementRef<HTMLElement>>('heading');

  protected readonly firstPage = FIRST_PAGE;

  protected readonly query = computed<TransactionQuery>(() => ({
    merchantId: this.merchantIdParam() ?? '',
    status: TRANSACTION_STATUSES.find((status) => status === this.statusParam()) ?? null,
    page: Math.max(FIRST_PAGE, Number.parseInt(this.pageParam() ?? '', 10) || FIRST_PAGE),
  }));

  protected readonly transactions = rxResource({
    params: () => this.query(),
    stream: ({ params }) => this.api.list(params),
  });

  // Keeps the last loaded page on screen while the next one loads, so the table and the
  // pagination button the user just pressed (and its focus) stay in place.
  private readonly lastLoaded = linkedSignal<
    TransactionPage | undefined,
    TransactionPage | undefined
  >({
    source: () => (this.transactions.hasValue() ? this.transactions.value() : undefined),
    computation: (loaded, previous) => loaded ?? previous?.value,
  });

  protected readonly loadState = computed<LoadStateKind | null>(() => {
    if (this.transactions.error()) {
      return 'error';
    }

    if (this.transactions.isLoading()) {
      return 'loading';
    }

    const page = this.lastLoaded();

    if (page === undefined || page.data.length > 0) {
      return null;
    }

    return page.meta.current_page > page.meta.last_page ? 'beyond-last-page' : 'empty';
  });

  protected readonly visiblePage = computed(() => {
    const page = this.lastLoaded();
    const state = this.loadState();

    return page !== undefined && page.data.length > 0 && (state === null || state === 'loading')
      ? page
      : undefined;
  });

  protected applyFilters(filters: TransactionFiltersValue): void {
    this.navigate({ merchantId: filters.merchantId, status: filters.status, page: FIRST_PAGE });
  }

  protected goToPage(page: number): void {
    this.navigate({ ...this.query(), page });
  }

  /** The Retry button disappears once the data loads, so the focus moves to a stable place first. */
  protected retry(): void {
    this.heading().nativeElement.focus();
    this.transactions.reload();
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
