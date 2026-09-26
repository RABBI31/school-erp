import { describe, expect, it } from 'vitest';
import { formatDate, formatNumber } from './format';

describe('formatDate', () => {
    it('formats as DD/MM/YYYY', () => {
        expect(formatDate('2026-01-31', 'en')).toBe('31/01/2026');
    });

    it('uses Bangla digits for bn', () => {
        expect(formatDate('2026-01-31', 'bn')).toBe('৩১/০১/২০২৬');
    });

    it('ignores the time part without shifting the day', () => {
        expect(formatDate('2026-12-31T23:30:00+06:00', 'en')).toBe('31/12/2026');
    });

    it('returns an empty string for missing dates', () => {
        expect(formatDate(null, 'en')).toBe('');
    });
});

describe('formatNumber', () => {
    it('uses Bangla digits for bn only', () => {
        expect(formatNumber(40, 'bn')).toBe('৪০');
        expect(formatNumber(40, 'en')).toBe('40');
        expect(formatNumber(null, 'bn')).toBe('');
    });
});
