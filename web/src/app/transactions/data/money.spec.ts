import { formatAmount } from './money';

describe('formatAmount', () => {
  it('shows minor units with the decimals of each currency and its code', () => {
    expect(formatAmount(15000, 'CLP').replace(/\s/g, ' ')).toBe('CLP 15,000');
    expect(formatAmount(1050, 'USD').replace(/\s/g, ' ')).toBe('USD 10.50');
    expect(formatAmount(1, 'USD').replace(/\s/g, ' ')).toBe('USD 0.01');
    expect(formatAmount(5000000, 'CLP').replace(/\s/g, ' ')).toBe('CLP 5,000,000');
  });
});
