import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { expectNoAxeViolations } from '../testing/axe';
import { App } from './app';

describe('App', () => {
  it('renders the page landmarks and a skip link to the main content', async () => {
    TestBed.configureTestingModule({ imports: [App], providers: [provideRouter([])] });
    const fixture = TestBed.createComponent(App);
    await fixture.whenStable();
    const element = fixture.nativeElement as HTMLElement;

    expect(element.querySelector('header')?.textContent).toContain('Payment Processing Service');
    expect(element.querySelector('main#main')).not.toBeNull();
    expect(element.querySelector('a[href="#main"]')?.textContent).toContain('Skip to content');
    await expectNoAxeViolations(element);
  });
});
