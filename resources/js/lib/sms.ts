/**
 * SMS parts, the same rule as App\Services\Sms\SmsParts: any non-ASCII
 * (Bangla) text is Unicode — 70 characters for one part, 67 per part when
 * split; plain text is 160 and 153. Characters are counted as code points.
 */
export function smsParts(message: string): number {
    if (message === '') {
        return 0;
    }

    const length = [...message].length;
    const unicode = /[^\x00-\x7F]/.test(message);
    const [single, multi] = unicode ? [70, 67] : [160, 153];

    return length <= single ? 1 : Math.ceil(length / multi);
}
