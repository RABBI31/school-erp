/// <reference types="node" />
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import en from './en.json';

const root = join(__dirname, '..');

function files(dir: string): string[] {
    return readdirSync(dir).flatMap((name) => {
        const path = join(dir, name);
        if (statSync(path).isDirectory()) {
            return name === 'ui' ? [] : files(path);
        }
        return /\.(vue|ts)$/.test(name) && !name.endsWith('.test.ts') ? [path] : [];
    });
}

function has(messages: object, key: string): boolean {
    let node: unknown = messages;
    for (const part of key.split('.')) {
        if (typeof node !== 'object' || node === null || !(part in node)) {
            return false;
        }
        node = (node as Record<string, unknown>)[part];
    }
    return typeof node === 'string';
}

// Literal keys only: $t('a.b'), t('a.b'), and title/label/description: 'a.b' in nav, breadcrumbs, columns and cards.
const patterns = [/\$?\bt\(\s*'([a-zA-Z]+(?:\.[a-zA-Z]+)+)'/g, /\b(?:title|label|description):\s*'([a-zA-Z]+(?:\.[a-zA-Z]+)+)'/g];

describe('translation keys used in code', () => {
    it('all exist in en.json (and therefore bn.json)', () => {
        const missing: string[] = [];

        for (const file of files(root)) {
            const source = readFileSync(file, 'utf8');
            for (const pattern of patterns) {
                for (const [, key] of source.matchAll(pattern)) {
                    if (!has(en, key)) {
                        missing.push(`${file.replace(root, '')}: ${key}`);
                    }
                }
            }
        }

        expect(missing).toEqual([]);
    });
});
