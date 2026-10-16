import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Service } from '@angular/core';
import { Observable } from 'rxjs';
import { TransactionDetails, TransactionPage, TransactionQuery } from './transaction.models';

const PAYMENTS_URL = '/api/payments';

const FIRST_PAGE = 1;

/** The only place that talks HTTP to the payments API; components never use HttpClient. */
@Service()
export class TransactionsApi {
  private readonly http = inject(HttpClient);

  list(query: TransactionQuery): Observable<TransactionPage> {
    let params = new HttpParams();

    // The API already defaults to the first page, so it is only sent when it differs.
    if (query.page !== FIRST_PAGE) {
      params = params.set('page', query.page);
    }

    if (query.merchantId !== '') {
      params = params.set('merchant_id', query.merchantId);
    }

    if (query.status !== null) {
      params = params.set('status', query.status);
    }

    return this.http.get<TransactionPage>(PAYMENTS_URL, { params });
  }

  get(transactionId: string): Observable<TransactionDetails> {
    return this.http.get<TransactionDetails>(
      `${PAYMENTS_URL}/${encodeURIComponent(transactionId)}`,
    );
  }
}
