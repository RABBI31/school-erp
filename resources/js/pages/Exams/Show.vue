<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import ExamStatusBadge from '@/components/exams/ExamStatusBadge.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ExamStatus, ExamSubjectRow, MarkPart } from '@/types/exams';
import type { ClassOption } from '@/types/fees';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Copy, Lock, LockOpen, Pencil, PlayCircle, Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    exam: { id: number; name_en: string; name_bn: string; start_date: string | null; status: ExamStatus; grading_scheme_id: number };
    classes: ClassOption[];
    subjects: ExamSubjectRow[];
    schemes: { id: number; name: string }[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.actions.setup', href: `/exams/${props.exam.id}` },
]);

const parts: MarkPart[] = ['cq', 'mcq', 'practical'];
const n = (value: string | number | null) => (value === null ? '' : formatNumber(String(value).replace(/\.00$/, ''), locale.value));
const plain = (value: string | null) => (value === null ? '' : value.replace(/\.00$/, ''));

// Class tabs: start on the first class that has subjects.
const classId = ref(props.classes.find((c) => props.subjects.some((s) => s.school_class_id === c.id))?.id ?? props.classes[0]?.id ?? 0);
const classSubjects = computed(() => props.subjects.filter((s) => s.school_class_id === classId.value));
const countFor = (id: number) => props.subjects.filter((s) => s.school_class_id === id).length;

const post = (url: string, data: Record<string, unknown> = {}) => router.post(url, data as never, { preserveScroll: true });
const addClassSubjects = () => post(route('exams.subjects.store', { exam: props.exam.id }), { school_class_id: classId.value });
const copyPrevious = () => post(route('exams.subjects.copy', { exam: props.exam.id }));

// Full / pass marks dialog.
const editing = ref<ExamSubjectRow | null>(null);
const marksForm = useForm({
    cq_full: '', cq_pass: '', mcq_full: '', mcq_pass: '', practical_full: '', practical_pass: '', total_full: '', total_pass: '',
});
const usesParts = computed(() => parts.some((p) => marksForm[`${p}_full`] !== ''));
const partsTotal = computed(() => parts.reduce((sum, p) => sum + (Number(marksForm[`${p}_full`]) || 0), 0));
const edit = (row: ExamSubjectRow) => {
    editing.value = row;
    marksForm.defaults({
        cq_full: plain(row.cq_full), cq_pass: plain(row.cq_pass), mcq_full: plain(row.mcq_full), mcq_pass: plain(row.mcq_pass),
        practical_full: plain(row.practical_full), practical_pass: plain(row.practical_pass), total_full: plain(row.total_full), total_pass: plain(row.total_pass),
    });
    marksForm.reset();
    marksForm.clearErrors();
};
const saveMarks = () =>
    editing.value &&
    marksForm
        .transform((data) => Object.fromEntries(Object.entries(data).map(([key, value]) => [key, value === '' ? null : value])))
        .put(route('exams.subjects.update', { exam: props.exam.id, subject: editing.value.id }), { preserveScroll: true, onSuccess: () => (editing.value = null) });

const removing = ref<ExamSubjectRow | null>(null);
const remove = () => {
    if (removing.value) {
        router.delete(route('exams.subjects.destroy', { exam: props.exam.id, subject: removing.value.id }), { preserveScroll: true, onFinish: () => (removing.value = null) });
    }
};

// Exam details.
const editingExam = ref(false);
const examForm = useForm({ name_en: props.exam.name_en, name_bn: props.exam.name_bn, start_date: props.exam.start_date ?? '', grading_scheme_id: props.exam.grading_scheme_id });
const saveExam = () => examForm.put(route('exams.update', { exam: props.exam.id }), { preserveScroll: true, onSuccess: () => (editingExam.value = false) });
const deletingExam = ref(false);
const deleteExam = () => router.delete(route('exams.destroy', { exam: props.exam.id }), { onFinish: () => (deletingExam.value = false) });

// Status changes, each confirmed.
const confirming = ref<'publish' | 'unpublish' | null>(null);
const changeStatus = (action: 'open' | 'publish' | 'unpublish') =>
    router.post(route(`exams.${action}`, { exam: props.exam.id }), {}, { preserveScroll: true, onFinish: () => (confirming.value = null) });
</script>

<template>
    <Head :title="localized(exam)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="localized(exam)" :description="$t('exams.show.description')">
                <ExamStatusBadge :status="exam.status" class="self-center" />
                <Button variant="outline" size="sm" @click="editingExam = true"><Pencil class="size-4" />{{ $t('common.edit') }}</Button>
                <Button v-if="exam.status === 'draft'" size="sm" @click="changeStatus('open')"><PlayCircle class="size-4" />{{ $t('exams.show.open') }}</Button>
                <Button v-if="exam.status === 'marks_entry' && can('results.publish')" size="sm" @click="confirming = 'publish'">
                    <Lock class="size-4" />{{ $t('exams.show.publish') }}
                </Button>
                <Button v-if="exam.status === 'published' && can('results.publish')" size="sm" variant="outline" @click="confirming = 'unpublish'">
                    <LockOpen class="size-4" />{{ $t('exams.show.unpublish') }}
                </Button>
            </PageHeader>

            <p v-if="exam.status === 'draft'" class="text-sm text-muted-foreground">{{ $t('exams.show.openHelp') }}</p>

            <div class="flex flex-wrap gap-2" role="tablist">
                <Button
                    v-for="schoolClass in classes"
                    :key="schoolClass.id"
                    role="tab"
                    size="sm"
                    :aria-selected="classId === schoolClass.id"
                    :variant="classId === schoolClass.id ? 'default' : 'outline'"
                    @click="classId = schoolClass.id"
                >
                    {{ localized(schoolClass) }}
                    <span class="text-xs opacity-70">{{ formatNumber(countFor(schoolClass.id), locale) }}</span>
                </Button>
            </div>

            <div v-if="exam.status !== 'published'" class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="addClassSubjects"><Plus class="size-4" />{{ $t('exams.show.addClassSubjects') }}</Button>
                <Button variant="outline" size="sm" @click="copyPrevious"><Copy class="size-4" />{{ $t('exams.show.copyPrevious') }}</Button>
            </div>

            <p v-if="classSubjects.length === 0" class="text-sm text-muted-foreground">{{ $t('exams.show.noSubjects') }}</p>

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="px-3 py-2 text-left">{{ $t('exams.show.subject') }}</th>
                            <th v-for="part in parts" :key="part" class="px-3 py-2 text-center">{{ $t(`exams.show.${part}`) }}</th>
                            <th class="px-3 py-2 text-center">{{ $t('exams.show.total') }}</th>
                            <th class="px-3 py-2"><span class="sr-only">{{ $t('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in classSubjects" :key="row.id" class="border-b last:border-0">
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ localized(row.subject) }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ row.subject.code }}<template v-if="row.subject.group"> · {{ row.subject.group.name }}</template>
                                    <template v-if="row.marks_count"> · {{ $t('exams.show.marksEntered', { count: formatNumber(row.marks_count, locale) }) }}</template>
                                </div>
                            </td>
                            <td v-for="part in parts" :key="part" class="px-3 py-2 text-center tabular-nums">
                                <template v-if="row[`${part}_full`] !== null">{{ n(row[`${part}_full`]) }} / {{ n(row[`${part}_pass`]) || '—' }}</template>
                                <span v-else class="text-muted-foreground">—</span>
                            </td>
                            <td class="px-3 py-2 text-center font-medium tabular-nums">{{ n(row.total_full) }} / {{ n(row.total_pass) }}</td>
                            <td class="px-3 py-2">
                                <div v-if="exam.status !== 'published'" class="flex justify-end gap-1">
                                    <Button size="icon" variant="ghost" :aria-label="$t('exams.show.editMarks')" @click="edit(row)"><Pencil class="size-4" /></Button>
                                    <Button v-if="!row.marks_count" size="icon" variant="ghost" :aria-label="$t('exams.show.deleteSubject')" @click="removing = row">
                                        <Trash2 class="size-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Button v-if="exam.status === 'draft'" variant="ghost" size="sm" class="text-red-600" @click="deletingExam = true">
                <Trash2 class="size-4" />{{ $t('exams.delete') }}
            </Button>
        </div>

        <!-- Full and pass marks -->
        <Dialog :open="editing !== null" @update:open="(open) => !open && (editing = null)">
            <DialogContent>
                <form class="space-y-5" @submit.prevent="saveMarks">
                    <DialogHeader>
                        <DialogTitle>{{ editing ? localized(editing.subject) : '' }} — {{ $t('exams.show.editMarks') }}</DialogTitle>
                        <DialogDescription>{{ $t('exams.show.partsHelp') }}</DialogDescription>
                    </DialogHeader>
                    <div class="grid grid-cols-[1fr_6rem_6rem] items-center gap-x-3 gap-y-2 text-sm">
                        <span></span>
                        <span class="text-center font-medium">{{ $t('exams.show.full') }}</span>
                        <span class="text-center font-medium">{{ $t('exams.show.pass') }}</span>
                        <template v-for="part in parts" :key="part">
                            <label :for="`${part}_full`">{{ $t(`exams.show.${part}`) }}</label>
                            <Input :id="`${part}_full`" v-model="marksForm[`${part}_full`]" type="number" min="0" step="0.01" class="text-right" />
                            <Input v-model="marksForm[`${part}_pass`]" type="number" min="0" step="0.01" class="text-right" :disabled="marksForm[`${part}_full`] === ''" :aria-label="`${$t(`exams.show.${part}`)} ${$t('exams.show.pass')}`" />
                        </template>
                        <label for="total_full" class="font-medium">{{ $t('exams.show.total') }}</label>
                        <Input v-if="usesParts" id="total_full" :model-value="String(partsTotal)" disabled class="text-right" />
                        <Input v-else id="total_full" v-model="marksForm.total_full" type="number" min="0" step="0.01" required class="text-right" />
                        <Input v-model="marksForm.total_pass" type="number" min="0" step="0.01" required class="text-right" :aria-label="`${$t('exams.show.total')} ${$t('exams.show.pass')}`" />
                    </div>
                    <ul v-if="Object.keys(marksForm.errors).length" class="space-y-1 text-sm text-red-600">
                        <li v-for="(message, key) in marksForm.errors" :key="key">{{ message }}</li>
                    </ul>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="editing = null">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="marksForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Exam details -->
        <Dialog v-model:open="editingExam">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="saveExam">
                    <DialogHeader>
                        <DialogTitle>{{ $t('exams.edit') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="exam_name_en" :label="$t('exams.nameEn')" :error="examForm.errors.name_en" required v-slot="{ id }">
                        <Input :id="id" v-model="examForm.name_en" required />
                    </FormField>
                    <FormField id="exam_name_bn" :label="$t('exams.nameBn')" :error="examForm.errors.name_bn" required v-slot="{ id }">
                        <Input :id="id" v-model="examForm.name_bn" required />
                    </FormField>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="exam_start_date" :label="$t('exams.startDate')" :error="examForm.errors.start_date" v-slot="{ id }">
                            <Input :id="id" v-model="examForm.start_date" type="date" />
                        </FormField>
                        <FormField v-if="schemes.length > 1" id="exam_scheme" :label="$t('exams.scheme')" :error="examForm.errors.grading_scheme_id" v-slot="{ id }">
                            <NativeSelect :id="id" v-model="examForm.grading_scheme_id">
                                <option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="editingExam = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="examForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <!-- Publish / unpublish -->
        <Dialog :open="confirming !== null" @update:open="(open) => !open && (confirming = null)">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t(`exams.show.${confirming}Title`) }}</DialogTitle>
                    <DialogDescription>{{ $t(`exams.show.${confirming}Help`) }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="confirming = null">{{ $t('common.cancel') }}</Button>
                    <Button @click="confirming && changeStatus(confirming)">{{ $t(`exams.show.${confirming}`) }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="removing ? localized(removing.subject) : null" @confirm="remove" @cancel="removing = null" />
        <ConfirmDelete :name="deletingExam ? localized(exam) : null" @confirm="deleteExam" @cancel="deletingExam = false" />
    </AppLayout>
</template>
