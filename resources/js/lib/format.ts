import { toBnDigits } from './bn-digits';

/** "2026-01-31" (or an ISO timestamp) -> "31/01/2026", with Bangla digits for bn. */
export function formatDate(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const [year, month, day] = value.slice(0, 10).split('-');
    const formatted = `${day}/${month}/${year}`;

    return locale === 'bn' ? toBnDigits(formatted) : formatted;
}

/** Numbers in the UI language: 2026 -> "২০২৬" in bn. */
export function formatNumber(value: number | string | null | undefined, locale: string): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    return locale === 'bn' ? toBnDigits(value) : String(value);
}

/**
 * "125000.50" -> "1,25,000.50" (Bangladeshi lakh grouping; whole taka drop
 * the ".00"), Bangla digits for bn. Amounts arrive from the server as strings.
 */
export function formatMoney(value: number | string | null | undefined, locale: string): string {
    if (value === null || value === undefined || value === '') {
        return '';
    }

    const [whole, fraction = ''] = String(value).split('.');
    const negative = whole.startsWith('-');
    const digits = negative ? whole.slice(1) : whole;
    const grouped = digits.length > 3 ? `${digits.slice(0, -3).replace(/\B(?=(\d{2})+$)/g, ',')},${digits.slice(-3)}` : digits;
    const cents = fraction.padEnd(2, '0').slice(0, 2);
    const text = `${negative ? '-' : ''}${grouped}${cents === '00' ? '' : `.${cents}`}`;

    return locale === 'bn' ? toBnDigits(text) : text;
}

/** "2026-03" -> "03/2026", with Bangla digits for bn. */
export function formatMonth(value: string | null | undefined, locale: string): string {
    if (!value) {
        return '';
    }

    const [year, month] = value.split('-');
    const formatted = `${month}/${year}`;

    return locale === 'bn' ? toBnDigits(formatted) : formatted;
}
