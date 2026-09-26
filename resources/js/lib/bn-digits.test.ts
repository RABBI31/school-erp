import { describe, expect, it } from 'vitest';
import { toBnDigits } from './bn-digits';

describe('toBnDigits', () => {
    it('converts every ascii digit', () => {
        expect(toBnDigits('0123456789')).toBe('০১২৩৪৫৬৭৮৯');
    });

    it('accepts numbers', () => {
        expect(toBnDigits(2026)).toBe('২০২৬');
        expect(toBnDigits(4.5)).toBe('৪.৫');
        expect(toBnDigits(-15)).toBe('-১৫');
    });

    it('keeps separators in dates and money', () => {
        expect(toBnDigits('26/09/2026')).toBe('২৬/০৯/২০২৬');
        expect(toBnDigits('1,250.50')).toBe('১,২৫০.৫০');
    });

    it('leaves non-digit text untouched', () => {
        expect(toBnDigits('Roll 12, রোল ১২')).toBe('Roll ১২, রোল ১২');
        expect(toBnDigits('')).toBe('');
    });
});
