import { CurrencyCode } from './transaction.models';

/** ISO 4217 exponent of each supported currency: amounts travel in minor units (SUP-02). */
const CURRENCY_EXPONENT: Record<CurrencyCode, number> = { CLP: 0, USD: 2 };

/**
 * The currency code is shown instead of a symbol, because "$" means both CLP and USD.
 */
export function formatAmount(amountInMinorUnits: number, currency: CurrencyCode): string {
  const exponent = CURRENCY_EXPONENT[currency];

  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency,
    currencyDisplay: 'code',
    minimumFractionDigits: exponent,
    maximumFractionDigits: exponent,
  }).format(amountInMinorUnits / 10 ** exponent);
}
