<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ExamStatus, ResultRow } from '@/types/exams';
import type { SectionOption } from '@/types/fees';
import { Head, Link, router } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { Download, FileText, Files, Loader2, MessageSquare, Sheet } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    exam: { id: number; name_en: string; name_bn: string; status: ExamStatus };
    sections: SectionOption[];
    sectionId: number | null;
    results: ResultRow[];
    marksheetsReady: boolean;
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.results.title', href: `/exams/${props.exam.id}/results` },
];

const choose = (id: string | number | null | undefined) => id && router.get(route('exams.results', { exam: props.exam.id }), { section_id: id });
const n = (value: string | number | null) => formatNumber(value === null ? '' : String(value).replace(/\.00$/, ''), locale.value);
const letterClass = (letter: string) => (letter === 'F' ? 'text-red-700 font-semibold' : letter === 'A+' ? 'text-green-700 font-semibold' : '');

// Section mark sheets are made in the background: poll until the file is ready.
const preparing = ref(false);
const { pause, resume } = useIntervalFn(() => router.reload({ only: ['marksheetsReady'] }), 5000, { immediate: false });
const prepare = () =>
    router.post(route('exams.marksheets.queue', { exam: props.exam.id, section: props.sectionId! }), {}, {
        preserveScroll: true,
        onSuccess: () => {
            preparing.value = true;
            resume();
        },
    });
watch(
    () => props.marksheetsReady,
    (ready) => {
        if (ready) {
            preparing.value = false;
            pause();
        }
    },
);

const confirmingSms = ref(false);
const sendSms = () => router.post(route('exams.result-sms', { exam: props.exam.id }), {}, { preserveScroll: true, onFinish: () => (confirmingSms.value = false) });
</script>

<template>
    <Head :title="`${$t('exams.results.title')} — ${localized(exam)}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4 md:p-6">
            <PageHeader :title="`${$t('exams.results.title')} — ${localized(exam)}`">
                <Button v-if="can('sms.send') && exam.status === 'published'" variant="outline" @click="confirmingSms = true">
                    <MessageSquare class="size-4" />{{ $t('exams.results.sendSms') }}
                </Button>
            </PageHeader>

            <p v-if="exam.status !== 'published'" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $t('exams.results.notPublished') }}</p>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                <NativeSelect :model-value="sectionId ?? ''" :aria-label="$t('exams.results.pick')" class="sm:w-60" @update:model-value="choose">
                    <option value="" disabled>{{ $t('exams.results.pick') }}…</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">
                        {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                    </option>
                </NativeSelect>

                <template v-if="sectionId && exam.status === 'published'">
                    <Button variant="outline" as-child>
                        <a :href="route('exams.tabulation', { exam: exam.id, section: sectionId })" target="_blank"><Sheet class="size-4" />{{ $t('exams.results.tabulation') }}</a>
                    </Button>
                    <Button v-if="marksheetsReady" variant="outline" as-child>
                        <a :href="route('exams.marksheets.download', { exam: exam.id, section: sectionId })"><Download class="size-4" />{{ $t('exams.results.downloadMarksheets') }}</a>
                    </Button>
                    <Button v-else-if="!preparing" variant="outline" @click="prepare"><Files class="size-4" />{{ $t('exams.results.prepareMarksheets') }}</Button>
                    <span v-else class="flex items-center gap-2 text-sm text-muted-foreground"><Loader2 class="size-4 animate-spin" />{{ $t('exams.results.preparing') }}</span>
                </template>
            </div>

            <p v-if="sectionId && exam.status === 'published' && results.length === 0" class="text-sm text-muted-foreground">{{ $t('exams.results.none') }}</p>

            <div v-if="results.length" class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-3 py-2">{{ $t('exams.results.position') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.roll') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.student') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('exams.results.total') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('exams.results.gpa') }}</th>
                            <th class="px-3 py-2 text-center">{{ $t('exams.results.letter') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('exams.results.fails') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('exams.results.positionClass') }}</th>
                            <th class="px-3 py-2"><span class="sr-only">{{ $t('exams.results.marksheet') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="result in results" :key="result.id" class="border-b last:border-0">
                            <td class="px-3 py-2 font-semibold tabular-nums">{{ n(result.merit_position_section) }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ n(result.roll) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="`/students/${result.student_id}`" class="hover:underline">
                                    {{ localized({ name_en: result.name_en, name_bn: result.name_bn ?? result.name_en }) }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ n(result.total_marks) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(result.gpa, locale) }}</td>
                            <td :class="['px-3 py-2 text-center', letterClass(result.letter)]">{{ result.letter }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ result.fail_count ? n(result.fail_count) : '' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ n(result.merit_position_class) }}</td>
                            <td class="px-3 py-2 text-right">
                                <Button size="icon" variant="ghost" as-child>
                                    <a :href="route('exams.marksheet', { exam: exam.id, result: result.id })" target="_blank" :aria-label="$t('exams.results.marksheet')">
                                        <FileText class="size-4" />
                                    </a>
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="confirmingSms">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('exams.results.sendSmsTitle') }}</DialogTitle>
                    <DialogDescription>{{ $t('exams.results.sendSmsBody') }}</DialogDescription>
                </DialogHeader>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="confirmingSms = false">{{ $t('common.cancel') }}</Button>
                    <Button @click="sendSms">{{ $t('exams.results.send') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
