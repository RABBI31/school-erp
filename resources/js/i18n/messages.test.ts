import { describe, expect, it } from 'vitest';
import bn from './bn.json';
import en from './en.json';

function keys(messages: object, prefix = ''): string[] {
    return Object.entries(messages).flatMap(([key, value]) =>
        typeof value === 'object' && value !== null ? keys(value, `${prefix}${key}.`) : [`${prefix}${key}`],
    );
}

describe('translations', () => {
    it('bn and en have exactly the same keys', () => {
        expect(keys(bn).sort()).toEqual(keys(en).sort());
    });
});
