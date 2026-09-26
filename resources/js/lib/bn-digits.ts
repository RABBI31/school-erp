const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];

/**
 * Replace ASCII digits 0-9 with Bangla digits ০-৯. Everything else is left as-is,
 * so it is safe for dates ("26/09/2026"), money ("1,250.50") and mixed text.
 * Mirrors the PHP bn_digits() helper.
 */
export function toBnDigits(value: string | number): string {
    return String(value).replace(/[0-9]/g, (digit) => BN_DIGITS[Number(digit)]);
}
