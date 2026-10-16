import { TestBed } from '@angular/core/testing';
import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { firstValueFrom } from 'rxjs';
import { TransactionsApi } from './transactions-api';
import { aTransactionDetails, aTransactionPage } from './transaction.fixtures';

describe('TransactionsApi', () => {
  let api: TransactionsApi;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [provideHttpClient(), provideHttpClientTesting()],
    });
    api = TestBed.inject(TransactionsApi);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('lists transactions with only the filters that are set', async () => {
    const page = aTransactionPage();
    const result = firstValueFrom(
      api.list({ merchantId: 'merchant-001', status: 'DECLINED', page: 2 }),
    );

    const request = http.expectOne((req) => req.url === '/api/payments');
    expect(request.request.method).toBe('GET');
    expect(request.request.params.keys().sort()).toEqual(['merchant_id', 'page', 'status']);
    expect(request.request.params.get('merchant_id')).toBe('merchant-001');
    expect(request.request.params.get('status')).toBe('DECLINED');
    expect(request.request.params.get('page')).toBe('2');
    request.flush(page);

    expect(await result).toEqual(page);
  });

  it('sends no query parameters for the first page without filters', async () => {
    const result = firstValueFrom(api.list({ merchantId: '', status: null, page: 1 }));

    const request = http.expectOne((req) => req.url === '/api/payments');
    expect(request.request.params.keys()).toEqual([]);
    request.flush(aTransactionPage());

    await result;
  });

  it('gets one transaction with its events', async () => {
    const details = aTransactionDetails();
    const result = firstValueFrom(api.get('01a14306-750b-7019-8c17-1cfe1d981425'));

    http.expectOne('/api/payments/01a14306-750b-7019-8c17-1cfe1d981425').flush(details);

    expect(await result).toEqual(details);
  });

  it('encodes the transaction id in the URL', async () => {
    const result = firstValueFrom(api.get('a/b?c'));

    http.expectOne('/api/payments/a%2Fb%3Fc').flush(aTransactionDetails());

    await result;
  });
});
