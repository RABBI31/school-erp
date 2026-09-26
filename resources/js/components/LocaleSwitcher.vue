<script setup lang="ts">
import type { Locale } from '@/i18n';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';

const page = usePage<SharedData>();

const options: { value: Locale; label: string }[] = [
    { value: 'bn', label: 'বাংলা' },
    { value: 'en', label: 'EN' },
];

function switchTo(locale: Locale) {
    if (locale !== page.props.locale) {
        router.post(route('locale.update'), { locale }, { preserveScroll: true });
    }
}
</script>

<template>
    <div class="inline-flex rounded-md border p-0.5 text-sm" role="group" :aria-label="$t('shell.language')">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="page.props.locale === option.value"
            :class="[
                'rounded px-2.5 py-1 transition-colors',
                page.props.locale === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
            ]"
            @click="switchTo(option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
