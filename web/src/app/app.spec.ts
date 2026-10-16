import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { expectNoAxeViolations } from '../testing/axe';
import { App } from './app';

describe('App', () => {
  afterEach(() =>
    document.querySelectorAll('app-root, [ng-version]').forEach((node) => node.remove()),
  );

  async function render(): Promise<HTMLElement> {
    TestBed.configureTestingModule({ imports: [App], providers: [provideRouter([])] });
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    document.body.appendChild(fixture.nativeElement);

    return fixture.nativeElement as HTMLElement;
  }

  it('renders the page landmarks and a skip link to the main content', async () => {
    const element = await render();

    expect(element.querySelector('header')?.textContent).toContain('Procesamiento de pagos');
    expect(element.querySelector('main#main')).not.toBeNull();
    expect(element.querySelector('a[href="#main"]')?.textContent).toContain('Saltar al contenido');
    await expectNoAxeViolations(element);
  });

  it('moves the focus to the main content without navigating away', async () => {
    const element = await render();
    const skipLink = element.querySelector<HTMLAnchorElement>('a[href="#main"]')!;

    const click = new MouseEvent('click', { bubbles: true, cancelable: true });
    skipLink.dispatchEvent(click);

    // With <base href="/">, following "#main" would reload the root and lose the current view.
    expect(document.activeElement).toBe(element.querySelector('main#main'));
    expect(click.defaultPrevented).toBe(true);
  });
});
