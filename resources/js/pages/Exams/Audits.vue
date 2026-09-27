<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { MarkAuditRow } from '@/types/exams';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    exam: { id: number; name_en: string; name_bn: string };
    audits: Paginated<MarkAuditRow>;
}>();

const { localized, locale } = useLocalized();
const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.audits.title', href: `/exams/${props.exam.id}/audits` },
];

const columns: DataTableColumn[] = [
    { key: 'changed_at', label: 'exams.audits.when' },
    { key: 'changed_by', label: 'exams.audits.who' },
    { key: 'student', label: 'exams.audits.student' },
    { key: 'subject', label: 'exams.audits.subject' },
    { key: 'old', label: 'exams.audits.from' },
    { key: 'new', label: 'exams.audits.to' },
];

// "CQ 40 · MCQ 20 = 60", or "absent".
const describe = (values: MarkAuditRow['old']) => {
    if (!values) return '—';
    if (values.is_absent) return t('exams.audits.absent');
    const n = (v: unknown) => formatNumber(String(v).replace(/\.00$/, ''), locale.value);
    const parts = (['cq', 'mcq', 'practical'] as const).filter((p) => values[p] !== null && values[p] !== undefined).map((p) => `${t(`exams.show.${p}`)} ${n(values[p])}`);
    const total = values.total === null || values.total === undefined ? '—' : n(values.total);
    return parts.length ? `${parts.join(' · ')} = ${total}` : total;
};
</script>

<template>
    <Head :title="`${$t('exams.audits.title')} — ${localized(exam)}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="`${$t('exams.audits.title')} — ${localized(exam)}`" :description="$t('exams.audits.description')" />

            <DataTable :rows="audits" :columns="columns" :searchable="false">
                <template #cell-changed_at="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{ formatDate(row.changed_at, locale) }} {{ formatNumber(row.changed_at.slice(11), locale) }}</span>
                </template>
                <template #cell-student="{ row }">
                    {{ localized({ name_en: row.student_name_en, name_bn: row.student_name_bn ?? row.student_name_en }) }}
                    <span class="text-xs text-muted-foreground">({{ row.section }}, {{ formatNumber(row.roll, locale) }})</span>
                </template>
                <template #cell-subject="{ row }">{{ localized({ name_en: row.subject_name_en, name_bn: row.subject_name_bn }) }}</template>
                <template #cell-old="{ row }"><span class="text-muted-foreground">{{ describe(row.old) }}</span></template>
                <template #cell-new="{ row }"><span class="font-medium">{{ describe(row.new) }}</span></template>
            </DataTable>
        </div>
    </AppLayout>
</template>
