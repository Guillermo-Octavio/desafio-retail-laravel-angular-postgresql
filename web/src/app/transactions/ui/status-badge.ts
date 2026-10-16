import { Component, computed, input } from '@angular/core';
import { TransactionStatus } from '../data/transaction.models';

// Dark text on a light tint of the same hue: every pair passes WCAG AA contrast.
const STATUS_CLASSES: Record<TransactionStatus, string> = {
  PENDING: 'bg-slate-100 text-slate-800',
  PROCESSING: 'bg-sky-100 text-sky-800',
  APPROVED: 'bg-green-100 text-green-800',
  DECLINED: 'bg-amber-100 text-amber-900',
  FAILED: 'bg-red-100 text-red-800',
};

@Component({
  selector: 'app-status-badge',
  template: `<span class="rounded px-2 py-0.5 text-xs font-semibold" [class]="classes()">{{
    status()
  }}</span>`,
})
export class StatusBadge {
  readonly status = input.required<TransactionStatus>();

  protected readonly classes = computed(() => STATUS_CLASSES[this.status()]);
}
