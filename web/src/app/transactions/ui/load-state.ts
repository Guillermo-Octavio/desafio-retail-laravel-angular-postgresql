import { Component, input, output } from '@angular/core';

export type LoadStateKind = 'loading' | 'empty' | 'beyond-last-page' | 'error';

/**
 * Both live regions stay in the page and only their content changes: screen readers announce
 * an update to an existing region more reliably than a region that was just inserted.
 */
@Component({
  selector: 'app-load-state',
  template: `
    <div role="status" class="text-slate-700">
      @switch (state()) {
        @case ('loading') {
          <p class="py-2">Loading…</p>
        }
        @case ('empty') {
          <p class="py-6">No transactions match these filters.</p>
        }
        @case ('beyond-last-page') {
          <div class="flex items-center gap-4 py-6">
            <p>This page is past the last one.</p>
            <button
              type="button"
              class="rounded border border-slate-500 px-3 py-1"
              (click)="firstPage.emit()"
            >
              Go to the first page
            </button>
          </div>
        }
      }
    </div>
    <div role="alert">
      @if (state() === 'error') {
        <div
          class="flex items-center gap-4 rounded border border-red-300 bg-red-50 p-4 text-red-800"
        >
          <p>The data could not be loaded. Check that the API is running.</p>
          <button
            type="button"
            class="rounded bg-red-800 px-3 py-1 font-medium text-white"
            (click)="retry.emit()"
          >
            Retry
          </button>
        </div>
      }
    </div>
  `,
})
export class LoadState {
  readonly state = input.required<LoadStateKind | null>();
  readonly retry = output<void>();
  readonly firstPage = output<void>();
}
