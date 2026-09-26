import { createI18n } from 'vue-i18n';
import bn from './bn.json';
import en from './en.json';

export type Locale = 'bn' | 'en';

export const i18n = createI18n({
    legacy: false,
    locale: 'bn',
    fallbackLocale: 'en',
    messages: { bn, en },
});

export function setLocale(locale: Locale): void {
    i18n.global.locale.value = locale;
    document.documentElement.lang = locale;
}
