import { Component, input, output } from '@angular/core';

@Component({
  selector: 'app-pagination',
  template: `
    <nav aria-label="Pagination" class="flex items-center gap-4">
      <button
        type="button"
        class="rounded border border-slate-500 px-3 py-1 disabled:opacity-50"
        [disabled]="page() <= 1"
        (click)="pageChange.emit(page() - 1)"
      >
        Previous page
      </button>
      <span aria-live="polite">Page {{ page() }} of {{ lastPage() }}</span>
      <button
        type="button"
        class="rounded border border-slate-500 px-3 py-1 disabled:opacity-50"
        [disabled]="page() >= lastPage()"
        (click)="pageChange.emit(page() + 1)"
      >
        Next page
      </button>
    </nav>
  `,
})
export class Pagination {
  readonly page = input.required<number>();
  readonly lastPage = input.required<number>();
  readonly pageChange = output<number>();
}
