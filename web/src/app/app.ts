import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

@Component({
  imports: [RouterOutlet],
  selector: 'app-root',
  templateUrl: './app.html',
})
export class App {
  /**
   * With <base href="/"> a plain "#main" link resolves to the site root and reloads the app,
   * losing the current filters or detail, so the focus is moved by hand instead.
   */
  protected skipToContent(event: Event, main: HTMLElement): void {
    event.preventDefault();
    main.focus();
  }
}
