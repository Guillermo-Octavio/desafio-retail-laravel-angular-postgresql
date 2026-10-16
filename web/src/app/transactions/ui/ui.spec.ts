import { Type } from '@angular/core';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { expectNoAxeViolations } from '../../../testing/axe';
import { aTransaction, aTransactionDetails } from '../../../testing/transaction.fixtures';
import { TransactionStatus } from '../data/transaction.models';
import { LoadState } from './load-state';
import { Pagination } from './pagination';
import { StatusBadge } from './status-badge';
import { TransactionDetail } from './transaction-detail';
import { TransactionFilters, TransactionFiltersValue } from './transaction-filters';
import { TransactionTable } from './transaction-table';

function render<T>(component: Type<T>, inputs: Record<string, unknown> = {}) {
  TestBed.configureTestingModule({ imports: [component], providers: [provideRouter([])] });
  const fixture = TestBed.createComponent(component);
  Object.entries(inputs).forEach(([name, value]) => fixture.componentRef.setInput(name, value));
  fixture.detectChanges();

  return { fixture, element: fixture.nativeElement as HTMLElement };
}

describe('StatusBadge', () => {
  it('shows the status as text, not only as a color', async () => {
    const { element } = render(StatusBadge, { status: 'DECLINED' satisfies TransactionStatus });

    expect(element.textContent?.trim()).toBe('DECLINED');
    await expectNoAxeViolations(element);
  });
});

describe('TransactionFilters', () => {
  it('labels every control and emits the filters when searching', async () => {
    const { fixture, element } = render(TransactionFilters, { merchantId: '', status: null });
    const emitted: TransactionFiltersValue[] = [];
    fixture.componentInstance.filtersChange.subscribe((value) => emitted.push(value));

    const merchant = element.querySelector<HTMLInputElement>('input#merchant-filter')!;
    merchant.value = 'merchant-001';
    const status = element.querySelector<HTMLSelectElement>('select#status-filter')!;
    status.value = 'DECLINED';
    element.querySelector('form')!.dispatchEvent(new Event('submit'));

    expect(element.querySelector('label[for="merchant-filter"]')?.textContent).toContain(
      'Comercio',
    );
    expect(element.querySelector('label[for="status-filter"]')?.textContent).toContain('Estado');
    expect(emitted).toEqual([{ merchantId: 'merchant-001', status: 'DECLINED' }]);
    await expectNoAxeViolations(element);
  });

  it('applies a new status as soon as it is selected', () => {
    const { fixture, element } = render(TransactionFilters, {
      merchantId: 'merchant-001',
      status: null,
    });
    const emitted: TransactionFiltersValue[] = [];
    fixture.componentInstance.filtersChange.subscribe((value) => emitted.push(value));

    const status = element.querySelector<HTMLSelectElement>('select#status-filter')!;
    status.value = 'FAILED';
    status.dispatchEvent(new Event('change'));

    expect(emitted).toEqual([{ merchantId: 'merchant-001', status: 'FAILED' }]);
  });

  it('offers every status plus all of them', () => {
    const { element } = render(TransactionFilters, { merchantId: '', status: 'APPROVED' });

    const options = [...element.querySelectorAll('option')].map((option) => option.value);
    expect(options).toEqual(['', 'PENDING', 'PROCESSING', 'APPROVED', 'DECLINED', 'FAILED']);
    expect(element.querySelector<HTMLSelectElement>('select')!.value).toBe('APPROVED');
  });
});

describe('TransactionTable', () => {
  it('lists each transaction with a keyboard reachable link to its detail', async () => {
    const transactions = [
      aTransaction(),
      aTransaction({
        transaction_id: '01a14306-0000-7000-8000-000000000002',
        status: 'DECLINED',
        decline_reason: 'INSUFFICIENT_FUNDS',
        amount: 1050,
        currency: 'USD',
      }),
    ];
    const { element } = render(TransactionTable, { transactions });

    const rows = element.querySelectorAll('tbody tr');
    expect(rows).toHaveLength(2);
    expect(rows[1].textContent).toContain('USD');
    expect(rows[1].textContent).toContain('10.50');
    expect(rows[1].textContent).toContain('INSUFFICIENT_FUNDS');
    expect(rows[1].textContent).toMatch(/VISA\s*••••\s*terminada en\s*1111/);
    expect(rows[1].querySelector('a')?.getAttribute('href')).toBe(
      '/transactions/01a14306-0000-7000-8000-000000000002',
    );
    expect(element.querySelector('caption')).not.toBeNull();
    expect(element.querySelector('thead')?.textContent).toContain('Creada (hora del negocio)');
    expect(rows[0].textContent).toContain('2026-10-15 09:00:00 (UTC-03:00)');
    expect(
      [...element.querySelectorAll('th')].every((header) => header.getAttribute('scope') === 'col'),
    ).toBe(true);
    await expectNoAxeViolations(element);
  });
});

describe('Pagination', () => {
  it('moves between pages and disables the buttons at the ends', async () => {
    const { fixture, element } = render(Pagination, { page: 1, lastPage: 3 });
    const pages: number[] = [];
    fixture.componentInstance.pageChange.subscribe((page) => pages.push(page));

    const [previous, next] = element.querySelectorAll('button');
    expect(previous.disabled).toBe(true);
    next.click();
    expect(pages).toEqual([2]);
    expect(element.textContent).toContain('Página 1 de 3');
    await expectNoAxeViolations(element);

    fixture.componentRef.setInput('page', 3);
    fixture.detectChanges();
    expect(element.querySelectorAll('button')[1].disabled).toBe(true);
  });
});

describe('LoadState', () => {
  it('keeps both live regions in the page, so screen readers announce every change', async () => {
    const { fixture, element } = render(LoadState, { state: null });

    expect(element.querySelector('[role="status"]')?.textContent?.trim()).toBe('');
    expect(element.querySelector('[role="alert"]')?.textContent?.trim()).toBe('');

    fixture.componentRef.setInput('state', 'loading');
    fixture.detectChanges();
    expect(element.querySelector('[role="status"]')?.textContent).toContain('Cargando');

    fixture.componentRef.setInput('state', 'empty');
    fixture.detectChanges();
    expect(element.querySelector('[role="status"]')?.textContent).toContain('No hay transacciones');
    await expectNoAxeViolations(element);
  });

  it('shows an error alert with a retry button', async () => {
    const { fixture, element } = render(LoadState, { state: 'error' });
    let retries = 0;
    fixture.componentInstance.retry.subscribe(() => retries++);

    expect(element.querySelector('[role="alert"]')?.textContent).toContain('No se pudieron cargar');
    element.querySelector('button')!.click();

    expect(retries).toBe(1);
    await expectNoAxeViolations(element);
  });

  it('offers a way back when the page is past the last one', async () => {
    const { fixture, element } = render(LoadState, { state: 'beyond-last-page' });
    let requests = 0;
    fixture.componentInstance.firstPage.subscribe(() => requests++);

    expect(element.querySelector('[role="status"]')?.textContent).toContain('después de la última');
    element.querySelector('button')!.click();

    expect(requests).toBe(1);
    await expectNoAxeViolations(element);
  });
});

describe('TransactionDetail', () => {
  it('shows the transaction and its events in chronological order', async () => {
    const { element } = render(TransactionDetail, { transaction: aTransactionDetails() });

    expect(element.querySelector('h1')?.textContent).toContain(
      '01a14306-750b-7019-8c17-1cfe1d981425',
    );
    expect(element.textContent).toContain('CLP');
    expect(element.textContent).toContain('15,000');
    const events = [...element.querySelectorAll('ol li')].map((item) =>
      item.textContent?.replace(/\s+/g, ' ').trim(),
    );
    expect(events).toHaveLength(5);
    expect(events[0]).toContain('→ PENDING');
    expect(events[1]).toContain('PENDING → PROCESSING');
    expect(events[2]).toContain('Intento 1: TEMPORARY_ERROR');
    expect(events[2]).toContain('HTTP_503');
    expect(events[3]).toContain('Intento 2: APPROVED');
    expect(events[4]).toContain('PROCESSING → APPROVED');
    expect(element.textContent).toContain('corr-123');
    expect(element.textContent).toContain('Creada (hora del negocio)');
    expect(element.textContent).toContain('2026-10-15 09:00:00.000 (UTC-03:00)');
    expect(element.textContent).toContain('2026-10-15 09:00:01.000 (UTC-03:00)');
    expect(element.querySelector('ol li time')?.textContent?.trim()).toBe('09:00:00.000');
    await expectNoAxeViolations(element);
  });
});
