/**
 * jsdom cannot compute colors, so axe skips the contrast rule in the other specs. This spec checks
 * every text and background pair the views use against WCAG AA, with the Tailwind 4 palette values
 * copied from node_modules/tailwindcss/theme.css.
 */
import { Oklch, PALETTE, PaletteColor } from '../../../testing/palette';

const TEXT_MINIMUM = 4.5;
const UI_COMPONENT_MINIMUM = 3;

/** OKLCH to linear sRGB (Björn Ottosson's OKLab matrices), clamped to the displayable gamut. */
function linearRgb([lightness, chroma, hue]: Oklch): [number, number, number] {
  const radians = (hue * Math.PI) / 180;
  const a = chroma * Math.cos(radians);
  const b = chroma * Math.sin(radians);
  const l = (lightness + 0.3963377774 * a + 0.2158037573 * b) ** 3;
  const m = (lightness - 0.1055613458 * a - 0.0638541728 * b) ** 3;
  const s = (lightness - 0.0894841775 * a - 1.291485548 * b) ** 3;
  const clamp = (value: number) => Math.min(1, Math.max(0, value));

  return [
    clamp(4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s),
    clamp(-1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s),
    clamp(-0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s),
  ];
}

function luminance(color: PaletteColor): number {
  const [red, green, blue] = linearRgb(PALETTE[color]);

  return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

function contrast(foreground: PaletteColor, background: PaletteColor): number {
  const [lighter, darker] = [luminance(foreground), luminance(background)].sort((x, y) => y - x);

  return (lighter + 0.05) / (darker + 0.05);
}

describe('Color contrast (WCAG AA)', () => {
  it.each<[PaletteColor, PaletteColor, string]>([
    ['slate-800', 'slate-100', 'PENDING badge'],
    ['sky-800', 'sky-100', 'PROCESSING badge'],
    ['green-800', 'green-100', 'APPROVED badge'],
    ['amber-900', 'amber-100', 'DECLINED badge'],
    ['red-800', 'red-100', 'FAILED badge'],
    ['red-800', 'red-50', 'error alert'],
    ['amber-900', 'amber-50', 'not found alert'],
    ['slate-700', 'white', 'secondary text'],
    ['slate-800', 'white', 'labels and headers'],
    ['slate-900', 'white', 'headings'],
    ['blue-800', 'white', 'links'],
    ['white', 'slate-800', 'search button'],
    ['white', 'red-800', 'retry button'],
  ])('%s on %s (%s) reaches 4.5:1 for text', (foreground, background) => {
    expect(contrast(foreground, background)).toBeGreaterThanOrEqual(TEXT_MINIMUM);
  });

  it('gives form control borders 3:1 against the page', () => {
    expect(contrast('slate-500', 'white')).toBeGreaterThanOrEqual(UI_COMPONENT_MINIMUM);
  });

  it('rejects a light gray on white, which proves the check can fail', () => {
    expect(contrast('slate-300', 'white')).toBeLessThan(TEXT_MINIMUM);
  });

  it('measures black on white as 21:1, which proves the conversion', () => {
    expect(contrast('black', 'white')).toBeCloseTo(21, 1);
  });
});
