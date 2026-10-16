import axe from 'axe-core';

/**
 * Fails the test when axe finds accessibility violations. jsdom cannot compute colors, so the
 * contrast rule is reported as incomplete there; colors are checked against the palette instead.
 */
export async function expectNoAxeViolations(element: Element): Promise<void> {
  const results = await axe.run(element);
  const violations = results.violations.map((violation) => `${violation.id}: ${violation.help}`);

  expect(violations).toEqual([]);
}
