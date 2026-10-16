import { Routes } from '@angular/router';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'transactions' },
  {
    path: 'transactions',
    title: 'Transactions',
    loadComponent: () =>
      import('./transactions/pages/transactions-page').then((page) => page.TransactionsPage),
  },
  {
    path: 'transactions/:id',
    title: 'Transaction',
    loadComponent: () =>
      import('./transactions/pages/transaction-detail-page').then(
        (page) => page.TransactionDetailPage,
      ),
  },
  { path: '**', redirectTo: 'transactions' },
];
