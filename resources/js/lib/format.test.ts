import { describe, expect, it } from 'vitest';
import { formatDate, formatMoney, formatNumber } from './format';

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

describe('formatMoney', () => {
    it('groups by lakh and drops .00', () => {
        expect(formatMoney('125000.50', 'en')).toBe('1,25,000.50');
        expect(formatMoney('1250.00', 'en')).toBe('1,250');
        expect(formatMoney('999', 'en')).toBe('999');
        expect(formatMoney('12345678.5', 'en')).toBe('1,23,45,678.50');
        expect(formatMoney(null, 'en')).toBe('');
    });

    it('uses Bangla digits for bn', () => {
        expect(formatMoney('1500.00', 'bn')).toBe('১,৫০০');
    });
});
