/**
 * Tailwind 4 colors used by the views, copied from node_modules/tailwindcss/theme.css. Every text
 * and background color used in a template must be listed here, with its contrast pairs checked in
 * contrast.spec.ts. Decorative borders are not covered; the form control border is checked as a
 * UI component (3:1).
 */
export type Oklch = readonly [lightness: number, chroma: number, hue: number];

export const PALETTE = {
  'red-50': [0.971, 0.013, 17.38],
  'red-100': [0.936, 0.032, 17.717],
  'red-800': [0.444, 0.177, 26.899],
  'amber-50': [0.987, 0.022, 95.277],
  'amber-100': [0.962, 0.059, 95.617],
  'amber-900': [0.414, 0.112, 45.904],
  'green-100': [0.962, 0.044, 156.743],
  'green-800': [0.448, 0.119, 151.328],
  'sky-100': [0.951, 0.026, 236.824],
  'sky-800': [0.443, 0.11, 240.79],
  'blue-800': [0.424, 0.199, 265.638],
  'slate-100': [0.968, 0.007, 247.896],
  'slate-300': [0.869, 0.022, 252.894],
  'slate-500': [0.554, 0.046, 257.417],
  'slate-700': [0.372, 0.044, 257.287],
  'slate-800': [0.279, 0.041, 260.031],
  'slate-900': [0.208, 0.042, 265.755],
  white: [1, 0, 0],
  black: [0, 0, 0],
} as const satisfies Record<string, Oklch>;

export type PaletteColor = keyof typeof PALETTE;
