<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { GradingSchemeRow } from '@/types/exams';
import { Head, useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{ schemes: GradingSchemeRow[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.grading.title', href: '/exams/grading' },
];

const plain = (value: string | number) => String(value).replace(/\.00$/, '');

// One form per scheme (normally just the default GPA 5.0).
const forms = props.schemes.map((scheme) => ({
    scheme,
    form: useForm({
        rules: scheme.rules.map((rule) => ({ min_percent: plain(rule.min_percent), max_percent: plain(rule.max_percent), letter: rule.letter, point: String(rule.point) })) as Record<string, string>[],
    }),
}));

const save = (entry: (typeof forms)[number]) => entry.form.put(route('exams.grading.update', { scheme: entry.scheme.id }), { preserveScroll: true });
const addRule = (entry: (typeof forms)[number]) => entry.form.rules.push({ min_percent: '', max_percent: '', letter: '', point: '' });
const removeRule = (entry: (typeof forms)[number], index: number) => entry.form.rules.splice(index, 1);
</script>

<template>
    <Head :title="$t('exams.grading.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('exams.grading.title')" :description="$t('exams.grading.description')" />

            <form v-for="entry in forms" :key="entry.scheme.id" class="space-y-4 rounded-xl border p-4" @submit.prevent="save(entry)">
                <h2 class="font-semibold">{{ entry.scheme.name }}</h2>
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-muted-foreground">
                            <th class="pb-2 font-medium">{{ $t('exams.grading.min') }}</th>
                            <th class="pb-2 font-medium">{{ $t('exams.grading.max') }}</th>
                            <th class="pb-2 font-medium">{{ $t('exams.grading.letter') }}</th>
                            <th class="pb-2 font-medium">{{ $t('exams.grading.point') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(rule, index) in entry.form.rules" :key="index">
                            <td class="py-1 pr-2"><Input v-model="rule.min_percent" type="number" min="0" max="100" required class="w-20" :aria-label="$t('exams.grading.min')" /></td>
                            <td class="py-1 pr-2"><Input v-model="rule.max_percent" type="number" min="0" max="100" required class="w-20" :aria-label="$t('exams.grading.max')" /></td>
                            <td class="py-1 pr-2"><Input v-model="rule.letter" maxlength="3" required class="w-20" :aria-label="$t('exams.grading.letter')" /></td>
                            <td class="py-1 pr-2">
                                <Input v-model="rule.point" type="number" min="0" max="5" step="0.01" required class="w-24" :aria-label="$t('exams.grading.point')" />
                            </td>
                            <td class="py-1 text-right">
                                <Button type="button" size="icon" variant="ghost" :aria-label="$t('exams.grading.removeRule')" @click="removeRule(entry, index)">
                                    <Trash2 class="size-4" />
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <ul v-if="Object.keys(entry.form.errors).length" class="space-y-1 text-sm text-red-600">
                    <li v-for="(message, key) in entry.form.errors" :key="key">{{ message }}</li>
                </ul>
                <div class="flex gap-2">
                    <Button type="button" variant="outline" size="sm" @click="addRule(entry)"><Plus class="size-4" />{{ $t('exams.grading.addRule') }}</Button>
                    <Button type="submit" size="sm" :disabled="entry.form.processing">{{ $t('common.save') }}</Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
