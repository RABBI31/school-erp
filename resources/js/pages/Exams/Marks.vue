<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ExamStatus, MarkOption, MarkPart, MarkRow } from '@/types/exams';
import { Head, router } from '@inertiajs/vue3';
import { useDebounceFn, useEventListener } from '@vueuse/core';
import { CheckCircle2, CircleAlert, Loader2 } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

type Column = MarkPart | 'total';
type Entry = { cq: string; mcq: string; practical: string; total: string; is_absent: boolean };

const props = defineProps<{
    exam: { id: number; name_en: string; name_bn: string; status: ExamStatus };
    options: MarkOption[];
    sectionId: number | null;
    examSubjectId: number | null;
    examSubject: { id: number; cq_full: string | null; mcq_full: string | null; practical_full: string | null; total_full: string; total_pass: string; parts: MarkPart[] } | null;
    rows: MarkRow[];
    editable: boolean;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.marks.title', href: `/exams/${props.exam.id}/marks` },
];

const optionKey = (o: { section_id: number | null; exam_subject_id: number | null }) => `${o.section_id}:${o.exam_subject_id}`;
const selected = computed(() => (props.sectionId && props.examSubjectId ? optionKey({ section_id: props.sectionId, exam_subject_id: props.examSubjectId }) : ''));
const choose = (key: string) => {
    const [section_id, exam_subject_id] = key.split(':');
    router.get(route('exams.marks', { exam: props.exam.id }), { section_id, exam_subject_id });
};

// The columns typed into: the parts in use, or just the total.
const columns = computed<Column[]>(() => (props.examSubject?.parts.length ? props.examSubject.parts : ['total']));
const fullOf = (column: Column) => Number(column === 'total' ? props.examSubject?.total_full : props.examSubject?.[`${column}_full`]);
const plain = (value: string | null) => (value === null ? '' : value.replace(/\.00$/, ''));

// Local copy of the grid; the server's rows are only read when the page loads.
const entries = reactive<Record<number, Entry>>(
    Object.fromEntries(
        props.rows.map((row) => [row.enrollment_id, { cq: plain(row.cq), mcq: plain(row.mcq), practical: plain(row.practical), total: plain(row.total), is_absent: row.is_absent }]),
    ),
);

const invalid = (id: number, column: Column) => {
    const value = entries[id][column];
    if (value === '') return false;
    const number = Number(value);
    return Number.isNaN(number) || number < 0 || number > fullOf(column);
};
const rowInvalid = (id: number) => columns.value.some((column) => invalid(id, column));
const rowTotal = (id: number) => {
    if (entries[id].is_absent) return '';
    if (columns.value[0] === 'total') return entries[id].total;
    const filled = columns.value.filter((c) => entries[id][c] !== '');
    return filled.length ? String(Math.round(filled.reduce((sum, c) => sum + Number(entries[id][c]) * 100, 0)) / 100) : '';
};

// Autosave: changed rows are sent 1.5 s after the last keystroke.
const dirty = reactive(new Set<number>());
const status = ref<'idle' | 'saving' | 'saved' | 'failed'>('idle');
const serverErrors = ref<Record<string, string>>({});

const save = () => {
    const ids = [...dirty].filter((id) => !rowInvalid(id));
    if (!ids.length || status.value === 'saving' || !props.examSubjectId || !props.sectionId) return;

    const marks = Object.fromEntries(
        ids.map((id) => {
            const entry = entries[id];
            const values: Record<string, string | boolean | null> = { is_absent: entry.is_absent };
            columns.value.forEach((column) => (values[column] = entry.is_absent || entry[column] === '' ? null : entry[column]));
            return [id, values];
        }),
    );

    ids.forEach((id) => dirty.delete(id));
    status.value = 'saving';

    router.put(route('marks.update', { examSubject: props.examSubjectId, section: props.sectionId }), { marks, autosave: true } as never, {
        preserveScroll: true,
        preserveState: true,
        only: ['editable'],
        onSuccess: (page) => {
            serverErrors.value = page.props.errors as Record<string, string>;
            status.value = Object.keys(serverErrors.value).length ? 'failed' : dirty.size ? 'idle' : 'saved';
            if (dirty.size) debouncedSave();
        },
        onError: (errors) => {
            serverErrors.value = errors;
            ids.forEach((id) => dirty.add(id));
            status.value = 'failed';
        },
    });
};
const debouncedSave = useDebounceFn(save, 1500);

const changed = (id: number) => {
    dirty.add(id);
    status.value = 'idle';
    debouncedSave();
};
const toggleAbsent = (id: number) => {
    entries[id].is_absent = !entries[id].is_absent;
    changed(id);
};

const errorFor = (id: number, column: Column) =>
    invalid(id, column) ? `${formatNumber(fullOf(column), locale.value)}` : (serverErrors.value[`marks.${id}.${column}`] ?? '');

// Enter or Tab moves down the column (then to the next column); Shift goes back.
const move = (event: KeyboardEvent, row: number, col: number) => {
    if (event.key !== 'Enter' && event.key !== 'Tab') return;
    event.preventDefault();
    const rows = props.rows.length;
    let r = row + (event.shiftKey ? -1 : 1);
    let c = col;
    if (r >= rows) [r, c] = [0, col + 1];
    if (r < 0) [r, c] = [rows - 1, col - 1];
    if (c < 0 || c >= columns.value.length) return;
    document.querySelector<HTMLInputElement>(`[data-cell="${r}-${c}"]`)?.focus();
};

// Don't lose unsaved marks by leaving the page.
useEventListener(window, 'beforeunload', (event: BeforeUnloadEvent) => {
    if (dirty.size) event.preventDefault();
});
</script>

<template>
    <Head :title="$t('exams.marks.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
            <PageHeader :title="`${$t('exams.marks.title')} — ${localized(exam)}`" :description="$t('exams.marks.help')" />

            <p v-if="options.length === 0" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $t('exams.marks.noOptions') }}</p>

            <NativeSelect v-else :model-value="selected" :aria-label="$t('exams.marks.pick')" class="h-12" @update:model-value="(value) => choose(String(value))">
                <option value="" disabled>{{ $t('exams.marks.pick') }}…</option>
                <option v-for="option in options" :key="optionKey(option)" :value="optionKey(option)">
                    {{ localized({ name_en: option.class_name_en, name_bn: option.class_name_bn }) }} {{ option.section_name }} —
                    {{ localized({ name_en: option.subject_name_en, name_bn: option.subject_name_bn }) }}
                </option>
            </NativeSelect>

            <template v-if="examSubject">
                <p v-if="exam.status === 'published'" class="rounded-md border bg-muted p-3 text-sm">{{ $t('exams.marks.locked') }}</p>
                <p v-else-if="!editable" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ $t('exams.marks.notOpen') }}</p>

                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <span class="text-muted-foreground">
                        {{ $t('exams.marks.full', { full: formatNumber(plain(examSubject.total_full), locale) }) }} ·
                        {{ $t('exams.marks.pass', { pass: formatNumber(plain(examSubject.total_pass), locale) }) }}
                    </span>
                    <span v-if="editable" class="flex items-center gap-2" aria-live="polite">
                        <template v-if="status === 'saving'"><Loader2 class="size-4 animate-spin" />{{ $t('exams.marks.saving') }}</template>
                        <template v-else-if="status === 'failed'"><CircleAlert class="size-4 text-red-600" /><span class="text-red-600">{{ $t('exams.marks.failed') }}</span></template>
                        <template v-else-if="dirty.size">{{ $t('exams.marks.unsaved') }}</template>
                        <template v-else-if="status === 'saved'"><CheckCircle2 class="size-4 text-green-600" />{{ $t('exams.marks.saved') }}</template>
                        <Button v-if="dirty.size" size="sm" variant="outline" @click="save">{{ $t('exams.marks.saveNow') }}</Button>
                    </span>
                </div>

                <p v-if="rows.length === 0" class="text-sm text-muted-foreground">{{ $t('exams.marks.noStudents') }}</p>

                <div v-else class="overflow-x-auto rounded-md border">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="w-12 px-2 py-2 text-left">{{ $t('fees.fields.roll') }}</th>
                                <th class="px-2 py-2 text-left">{{ $t('fees.fields.student') }}</th>
                                <th v-for="column in columns" :key="column" class="w-24 px-2 py-2 text-center">
                                    {{ column === 'total' ? $t('exams.marks.total') : $t(`exams.show.${column}`) }}
                                    <div class="text-xs font-normal text-muted-foreground">/ {{ formatNumber(fullOf(column), locale) }}</div>
                                </th>
                                <th v-if="columns[0] !== 'total'" class="w-16 px-2 py-2 text-center">{{ $t('exams.marks.total') }}</th>
                                <th class="w-20 px-2 py-2 text-center">{{ $t('exams.marks.absent') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(row, r) in rows" :key="row.enrollment_id" :class="['border-b last:border-0', entries[row.enrollment_id].is_absent && 'bg-muted/40']">
                                <td class="px-2 py-1 tabular-nums">{{ formatNumber(row.roll, locale) }}</td>
                                <td class="truncate px-2 py-1">{{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}</td>
                                <td v-for="(column, c) in columns" :key="column" class="px-2 py-1">
                                    <input
                                        v-model="entries[row.enrollment_id][column]"
                                        :data-cell="`${r}-${c}`"
                                        type="text"
                                        inputmode="decimal"
                                        autocomplete="off"
                                        :disabled="!editable || entries[row.enrollment_id].is_absent"
                                        :aria-label="`${formatNumber(row.roll, locale)} ${column}`"
                                        :aria-invalid="!!errorFor(row.enrollment_id, column)"
                                        :title="errorFor(row.enrollment_id, column) ? $t('exams.marks.tooHigh', { full: formatNumber(fullOf(column), locale) }) : undefined"
                                        :class="[
                                            'h-9 w-full rounded-md border bg-background px-2 text-right tabular-nums focus:outline-none focus:ring-2 focus:ring-ring disabled:opacity-50',
                                            errorFor(row.enrollment_id, column) && 'border-red-500 bg-red-50 text-red-700 dark:bg-red-950',
                                        ]"
                                        @input="changed(row.enrollment_id)"
                                        @keydown="move($event, r, c)"
                                    />
                                </td>
                                <td v-if="columns[0] !== 'total'" class="px-2 py-1 text-center font-medium tabular-nums">{{ formatNumber(rowTotal(row.enrollment_id), locale) }}</td>
                                <td class="px-2 py-1 text-center">
                                    <input
                                        type="checkbox"
                                        class="size-5 accent-primary"
                                        :checked="entries[row.enrollment_id].is_absent"
                                        :disabled="!editable"
                                        :aria-label="`${formatNumber(row.roll, locale)} ${$t('exams.marks.absent')}`"
                                        @change="toggleAbsent(row.enrollment_id)"
                                    />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
