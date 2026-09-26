import { useI18n } from 'vue-i18n';

/**
 * Pick the name in the UI language for records with name_en / name_bn,
 * and format numbers and dates the same way.
 */
export function useLocalized() {
    const { locale } = useI18n();

    const localized = (record: { name_en: string; name_bn: string } | null | undefined): string =>
        record ? (locale.value === 'bn' ? record.name_bn : record.name_en) : '';

    return { localized, locale };
}
