import { TestBed } from '@angular/core/testing';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { RouterTestingHarness } from '@angular/router/testing';
import { of, throwError } from 'rxjs';
import { expectNoAxeViolations } from '../../../testing/axe';
import { FakeTransactionsApi, unavailable } from '../../../testing/fake-transactions-api';
import {
  aTransaction,
  aTransactionDetails,
  aTransactionPage,
} from '../../../testing/transaction.fixtures';
import { routes } from '../../app.routes';
import { TransactionsApi } from '../data/transactions-api';

describe('Transactions pages (HU-12)', () => {
  let api: FakeTransactionsApi;
  let harness: RouterTestingHarness;

  async function open(url: string): Promise<HTMLElement> {
    await harness.navigateByUrl(url);
    await harness.fixture.whenStable();
    harness.detectChanges();

    return harness.routeNativeElement as HTMLElement;
  }

  async function settle(): Promise<HTMLElement> {
    await harness.fixture.whenStable();
    harness.detectChanges();

    return harness.routeNativeElement as HTMLElement;
  }

  beforeEach(async () => {
    api = new FakeTransactionsApi();
    TestBed.configureTestingModule({
      providers: [
        provideRouter(routes, withComponentInputBinding()),
        { provide: TransactionsApi, useValue: api },
      ],
    });
    harness = await RouterTestingHarness.create();
  });

  it('opens the transactions list from the root path', async () => {
    api.answerList(aTransactionPage());

    await open('/');

    expect(api.listCalls).toEqual([{ merchantId: '', status: null, page: 1 }]);
  });

  it('filters by status: only declined transactions are listed', async () => {
    const declined = aTransaction({ status: 'DECLINED', decline_reason: 'INSUFFICIENT_FUNDS' });
    api.listAnswer = (query) =>
      of(aTransactionPage(query.status === 'DECLINED' ? [declined] : [aTransaction(), declined]));
    let element = await open('/transactions');

    const status = element.querySelector<HTMLSelectElement>('select#status-filter')!;
    status.value = 'DECLINED';
    status.dispatchEvent(new Event('change'));
    element = await settle();

    expect(api.listCalls.at(-1)).toEqual({ merchantId: '', status: 'DECLINED', page: 1 });
    const rows = element.querySelectorAll('tbody tr');
    expect(rows).toHaveLength(1);
    expect(rows[0].textContent).toContain('DECLINED');
  });

  it('reads the filters and the page from the URL, so a search can be shared', async () => {
    api.answerList(aTransactionPage([aTransaction()], 2, 3));

    const element = await open('/transactions?merchant_id=merchant-001&status=APPROVED&page=2');

    expect(api.listCalls).toEqual([{ merchantId: 'merchant-001', status: 'APPROVED', page: 2 }]);
    expect(element.querySelector<HTMLInputElement>('input#merchant-filter')!.value).toBe(
      'merchant-001',
    );
    expect(element.textContent).toContain('Page 2 of 3');
  });

  it('ignores an unknown status in the URL', async () => {
    api.answerList(aTransactionPage());

    await open('/transactions?status=PAID&page=abc');

    expect(api.listCalls).toEqual([{ merchantId: '', status: null, page: 1 }]);
  });

  it('moves to the next page', async () => {
    api.listAnswer = (query) => of(aTransactionPage([aTransaction()], query.page, 2));
    let element = await open('/transactions?status=APPROVED');

    [...element.querySelectorAll('button')]
      .find((button) => button.textContent?.includes('Next page'))!
      .click();
    element = await settle();

    expect(api.listCalls.at(-1)).toEqual({ merchantId: '', status: 'APPROVED', page: 2 });
    expect(element.textContent).toContain('Page 2 of 2');
  });

  it('shows an empty state when nothing matches', async () => {
    api.answerList(aTransactionPage([]));

    const element = await open('/transactions?merchant_id=merchant-999');

    expect(element.querySelector('[role="status"]')?.textContent).toContain('No transactions');
    expect(element.querySelector('table')).toBeNull();
  });

  it('shows an error with a retry option when the API does not answer, and recovers on retry', async () => {
    let element = await open('/transactions');

    expect(element.querySelector('[role="alert"]')?.textContent).toContain('could not be loaded');

    api.answerList(aTransactionPage());
    element.querySelector<HTMLButtonElement>('[role="alert"] button')!.click();
    element = await settle();

    expect(api.listCalls).toHaveLength(2);
    expect(element.querySelector('[role="alert"]')).toBeNull();
    expect(element.querySelectorAll('tbody tr')).toHaveLength(1);
  });

  it('opens a transaction and shows its detail and its events in chronological order', async () => {
    api.answerList(aTransactionPage());
    api.getAnswer = () => of(aTransactionDetails());
    let element = await open('/transactions');

    await harness.navigateByUrl(element.querySelector('tbody a')!.getAttribute('href')!);
    element = await settle();

    expect(api.getCalls).toEqual(['01a14306-750b-7019-8c17-1cfe1d981425']);
    expect(element.querySelector('h1')?.textContent).toContain(
      '01a14306-750b-7019-8c17-1cfe1d981425',
    );
    expect(element.querySelectorAll('ol li')).toHaveLength(5);
    expect(element.querySelector('a[href="/transactions"]')?.textContent).toContain(
      'Back to transactions',
    );
    await expectNoAxeViolations(element);
  });

  it('moves the keyboard focus to the transaction heading when the detail opens', async () => {
    api.getAnswer = () => of(aTransactionDetails());

    const element = await open('/transactions/01a14306-750b-7019-8c17-1cfe1d981425');

    expect(document.activeElement).toBe(element.querySelector('h1'));
  });

  it('says that a transaction does not exist instead of offering a retry', async () => {
    api.getAnswer = () => throwError(() => unavailable(404));

    const element = await open('/transactions/01a14306-0000-7000-8000-000000000000');

    expect(element.querySelector('[role="alert"]')?.textContent).toContain('does not exist');
    expect(element.querySelector('[role="alert"] button')).toBeNull();
  });

  it('passes the accessibility checks on the list page', async () => {
    api.answerList(
      aTransactionPage(
        [aTransaction(), aTransaction({ transaction_id: 'second', status: 'FAILED' })],
        1,
        2,
      ),
    );

    const element = await open('/transactions');

    await expectNoAxeViolations(element);
  });
});
