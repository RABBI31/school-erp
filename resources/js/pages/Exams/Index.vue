<script setup lang="ts">
import ExamStatusBadge from '@/components/exams/ExamStatusBadge.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ExamListItem } from '@/types/exams';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { BarChart3, History, ListChecks, PencilLine, Plus, Settings2, Trophy } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    exams: ExamListItem[];
    schemes: { id: number; name: string; is_default: boolean }[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'exams.title', href: '/exams' }];

const creating = ref(false);
const form = useForm({ name_en: '', name_bn: '', start_date: '', grading_scheme_id: props.schemes[0]?.id ?? null });
const create = () => form.post(route('exams.store'), { onSuccess: () => (creating.value = false) });
</script>

<template>
    <Head :title="$t('exams.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('exams.title')" :description="$t('exams.description', { year: formatNumber($page.props.academicYear?.name ?? '', locale) })">
                <template v-if="can('exams.manage')">
                    <Button variant="outline" as-child>
                        <Link :href="route('exams.grading')"><Settings2 class="size-4" />{{ $t('exams.actions.grading') }}</Link>
                    </Button>
                    <Button @click="creating = true"><Plus class="size-4" />{{ $t('exams.add') }}</Button>
                </template>
            </PageHeader>

            <p v-if="exams.length === 0" class="text-sm text-muted-foreground">{{ $t('exams.none') }}</p>

            <div class="grid gap-4 md:grid-cols-2">
                <section v-for="exam in exams" :key="exam.id" class="space-y-3 rounded-xl border p-4">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-semibold">{{ localized(exam) }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ formatDate(exam.start_date, locale) }}
                                <template v-if="exam.start_date">·</template>
                                {{ $t('exams.subjects', { count: formatNumber(exam.subjects_count, locale) }) }}
                            </p>
                        </div>
                        <ExamStatusBadge :status="exam.status" />
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button v-if="can('exams.manage')" size="sm" variant="outline" as-child>
                            <Link :href="route('exams.show', { exam: exam.id })"><ListChecks class="size-4" />{{ $t('exams.actions.setup') }}</Link>
                        </Button>
                        <Button v-if="can('marks.enter') && exam.status !== 'draft'" size="sm" variant="outline" as-child>
                            <Link :href="route('exams.marks', { exam: exam.id })"><PencilLine class="size-4" />{{ $t('exams.actions.marks') }}</Link>
                        </Button>
                        <template v-if="exam.status === 'published'">
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="route('exams.results', { exam: exam.id })"><Trophy class="size-4" />{{ $t('exams.actions.results') }}</Link>
                            </Button>
                            <Button size="sm" variant="outline" as-child>
                                <Link :href="route('exams.summary', { exam: exam.id })"><BarChart3 class="size-4" />{{ $t('exams.actions.summary') }}</Link>
                            </Button>
                        </template>
                        <Button v-if="can('exams.manage') && exam.status !== 'draft'" size="sm" variant="ghost" as-child>
                            <Link :href="route('exams.audits', { exam: exam.id })"><History class="size-4" />{{ $t('exams.actions.audits') }}</Link>
                        </Button>
                    </div>
                </section>
            </div>
        </div>

        <Dialog v-model:open="creating">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="create">
                    <DialogHeader>
                        <DialogTitle>{{ $t('exams.add') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="name_en" :label="$t('exams.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_en" required autofocus placeholder="Half Yearly Exam" />
                    </FormField>
                    <FormField id="name_bn" :label="$t('exams.nameBn')" :error="form.errors.name_bn" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_bn" required placeholder="অর্ধবার্ষিক পরীক্ষা" />
                    </FormField>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="start_date" :label="$t('exams.startDate')" :error="form.errors.start_date" v-slot="{ id }">
                            <Input :id="id" v-model="form.start_date" type="date" />
                        </FormField>
                        <FormField v-if="schemes.length > 1" id="grading_scheme_id" :label="$t('exams.scheme')" :error="form.errors.grading_scheme_id" v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.grading_scheme_id">
                                <option v-for="scheme in schemes" :key="scheme.id" :value="scheme.id">{{ scheme.name }}</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="creating = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
