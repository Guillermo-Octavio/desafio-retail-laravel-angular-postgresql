import { DatePipe } from '@angular/common';
import { afterNextRender, Component, computed, ElementRef, input, viewChild } from '@angular/core';
import { describeEvent } from '../data/event-description';
import { formatAmount } from '../data/money';
import { TransactionDetails } from '../data/transaction.models';
import { StatusBadge } from './status-badge';

@Component({
  selector: 'app-transaction-detail',
  imports: [DatePipe, StatusBadge],
  template: `
    <article class="flex flex-col gap-6">
      <h1 #heading tabindex="-1" class="text-xl font-semibold text-slate-900">
        Transaction <span class="font-mono">{{ transaction().transaction_id }}</span>
      </h1>

      <dl class="grid grid-cols-[max-content_1fr] gap-x-6 gap-y-2 text-sm">
        <dt class="font-medium text-slate-700">Status</dt>
        <dd><app-status-badge [status]="transaction().status" /></dd>
        <dt class="font-medium text-slate-700">Reason</dt>
        <dd>{{ transaction().decline_reason ?? '—' }}</dd>
        <dt class="font-medium text-slate-700">Merchant</dt>
        <dd>{{ transaction().merchant_id }}</dd>
        <dt class="font-medium text-slate-700">Amount</dt>
        <dd class="tabular-nums">{{ amount() }}</dd>
        <dt class="font-medium text-slate-700">Card</dt>
        <dd>{{ transaction().card.brand }} ending in {{ transaction().card.last4 }}</dd>
        <dt class="font-medium text-slate-700">Created (UTC)</dt>
        <dd class="tabular-nums">
          {{ transaction().created_at | date: 'yyyy-MM-dd HH:mm:ss.SSS' : 'UTC' }}
        </dd>
        <dt class="font-medium text-slate-700">Updated (UTC)</dt>
        <dd class="tabular-nums">
          {{ transaction().updated_at | date: 'yyyy-MM-dd HH:mm:ss.SSS' : 'UTC' }}
        </dd>
      </dl>

      <section aria-labelledby="history-heading">
        <h2 id="history-heading" class="mb-2 text-lg font-semibold text-slate-900">History</h2>
        <ol class="flex flex-col gap-2 border-l-2 border-slate-300 pl-4 text-sm">
          @for (event of events(); track $index) {
            <li>
              <time class="tabular-nums text-slate-700" [attr.datetime]="event.occurredAt">
                {{ event.occurredAt | date: 'HH:mm:ss.SSS' : 'UTC' }}
              </time>
              <span class="ml-2">{{ event.description }}</span>
              <span class="ml-2 text-slate-700">correlation {{ event.correlationId }}</span>
            </li>
          }
        </ol>
      </section>
    </article>
  `,
})
export class TransactionDetail {
  readonly transaction = input.required<TransactionDetails>();

  private readonly heading = viewChild.required<ElementRef<HTMLElement>>('heading');

  constructor() {
    // Moves keyboard and screen reader focus to the new view, as the router does not.
    afterNextRender(() => this.heading().nativeElement.focus());
  }

  protected readonly amount = computed(() =>
    formatAmount(this.transaction().amount, this.transaction().currency),
  );

  protected readonly events = computed(() =>
    this.transaction().events.map((event) => ({
      occurredAt: event.occurred_at,
      description: describeEvent(event),
      correlationId: event.correlation_id,
    })),
  );
}
