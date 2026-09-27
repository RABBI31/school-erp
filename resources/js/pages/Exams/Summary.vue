<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ExamStatus } from '@/types/exams';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    exam: { id: number; name_en: string; name_bn: string; status: ExamStatus };
    summary: {
        students: number;
        passed: number;
        pass_rate: number;
        grades: Record<string, number>;
        subject_fails: { name_en: string; name_bn: string; fails: number }[];
    };
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'exams.title', href: '/exams' },
    { title: 'exams.summary.title', href: `/exams/${props.exam.id}/summary` },
];

const letters = ['A+', 'A', 'A-', 'B', 'C', 'D', 'F'];
const grades = computed(() => {
    const known = letters.map((letter) => ({ letter, count: props.summary.grades[letter] ?? 0 }));
    const other = Object.keys(props.summary.grades).filter((letter) => !letters.includes(letter)).map((letter) => ({ letter, count: props.summary.grades[letter] }));
    return [...known, ...other];
});
const max = computed(() => Math.max(1, ...grades.value.map((g) => g.count)));
</script>

<template>
    <Head :title="`${$t('exams.summary.title')} — ${localized(exam)}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6 p-4 md:p-6">
            <PageHeader :title="`${$t('exams.summary.title')} — ${localized(exam)}`" />

            <dl class="grid grid-cols-3 gap-4">
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('exams.summary.students') }}</dt>
                    <dd class="text-3xl font-bold tabular-nums">{{ formatNumber(summary.students, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('exams.summary.passed') }}</dt>
                    <dd class="text-3xl font-bold tabular-nums">{{ formatNumber(summary.passed, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('exams.summary.passRate') }}</dt>
                    <dd class="text-3xl font-bold tabular-nums text-green-700">{{ formatNumber(summary.pass_rate, locale) }}%</dd>
                </div>
            </dl>

            <section class="rounded-xl border p-4">
                <h2 class="mb-4 font-semibold">{{ $t('exams.summary.grades') }}</h2>
                <ul class="space-y-2">
                    <li v-for="grade in grades" :key="grade.letter" class="flex items-center gap-3 text-sm">
                        <span class="w-8 font-medium">{{ grade.letter }}</span>
                        <span class="h-5 flex-1 rounded bg-muted">
                            <span
                                :class="['block h-5 rounded', grade.letter === 'F' ? 'bg-red-500' : 'bg-primary']"
                                :style="{ width: `${(grade.count / max) * 100}%` }"
                            ></span>
                        </span>
                        <span class="w-10 text-right tabular-nums">{{ formatNumber(grade.count, locale) }}</span>
                    </li>
                </ul>
            </section>

            <section class="rounded-xl border p-4">
                <h2 class="mb-4 font-semibold">{{ $t('exams.summary.subjectFails') }}</h2>
                <p v-if="summary.subject_fails.length === 0" class="text-sm text-muted-foreground">{{ $t('exams.summary.noFails') }}</p>
                <ul v-else class="divide-y text-sm">
                    <li v-for="subject in summary.subject_fails" :key="subject.name_en" class="flex justify-between py-2">
                        <span>{{ localized(subject) }}</span>
                        <span class="font-medium tabular-nums text-red-700">{{ formatNumber(subject.fails, locale) }}</span>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
