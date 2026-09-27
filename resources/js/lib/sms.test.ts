import { describe, expect, it } from 'vitest';
import { smsParts } from './sms';

describe('smsParts', () => {
    it('counts English as 160 / 153 per part', () => {
        expect(smsParts('')).toBe(0);
        expect(smsParts('a'.repeat(160))).toBe(1);
        expect(smsParts('a'.repeat(161))).toBe(2);
        expect(smsParts('a'.repeat(307))).toBe(3);
    });

    it('counts any Bangla as Unicode: 70 / 67 per part', () => {
        expect(smsParts('ক'.repeat(70))).toBe(1);
        expect(smsParts('ক'.repeat(71))).toBe(2);
        expect(smsParts('ক'.repeat(134))).toBe(2);
        expect(smsParts('ক'.repeat(135))).toBe(3);
        expect(smsParts('a'.repeat(100) + 'ক')).toBe(2);
    });
});
