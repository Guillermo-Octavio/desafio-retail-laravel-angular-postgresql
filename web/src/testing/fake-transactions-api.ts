import { HttpErrorResponse } from '@angular/common/http';
import { Observable, of, throwError } from 'rxjs';
import {
  TransactionDetails,
  TransactionPage,
  TransactionQuery,
} from '../app/transactions/data/transaction.models';

/** Stands in for TransactionsApi: records every call and answers with what each test sets. */
export class FakeTransactionsApi {
  readonly listCalls: TransactionQuery[] = [];
  readonly getCalls: string[] = [];
  listAnswer: (query: TransactionQuery) => Observable<TransactionPage> = () =>
    throwError(() => unavailable());
  getAnswer: (id: string) => Observable<TransactionDetails> = () => throwError(() => unavailable());

  list(query: TransactionQuery): Observable<TransactionPage> {
    this.listCalls.push(query);

    return this.listAnswer(query);
  }

  get(transactionId: string): Observable<TransactionDetails> {
    this.getCalls.push(transactionId);

    return this.getAnswer(transactionId);
  }

  answerList(page: TransactionPage): void {
    this.listAnswer = () => of(page);
  }
}

export function unavailable(status = 0): HttpErrorResponse {
  return new HttpErrorResponse({ status, statusText: 'Unknown Error' });
}
