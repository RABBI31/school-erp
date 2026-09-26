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
