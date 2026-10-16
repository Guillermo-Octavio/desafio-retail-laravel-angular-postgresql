import { Component, input, output } from '@angular/core';

export type LoadStateKind = 'loading' | 'empty' | 'error';

@Component({
  selector: 'app-load-state',
  template: `
    @switch (state()) {
      @case ('loading') {
        <p role="status" class="py-6 text-slate-700">Loading…</p>
      }
      @case ('empty') {
        <p role="status" class="py-6 text-slate-700">No transactions match these filters.</p>
      }
      @case ('error') {
        <div
          role="alert"
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
    }
  `,
})
export class LoadState {
  readonly state = input.required<LoadStateKind>();
  readonly retry = output<void>();
}
