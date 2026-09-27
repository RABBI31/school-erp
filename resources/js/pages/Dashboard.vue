<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, formatNumber } from '@/lib/format';
import { type BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BadgeDollarSign, ClipboardCheck, Paperclip, PencilLine } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    today: string;
    notices: { id: number; title_en: string; title_bn: string; body_en: string | null; body_bn: string | null; published_at: string; has_attachment: boolean }[];
    attendance?:
        | { weekend: true; sections: number }
        | { weekend: false; present: number; late: number; absent: number; leave: number; recorded: number; percent: number | null; sections: number; sections_taken: number };
    staff?: { present: number; total: number };
    fees?: { collected: string; billed: string; dues: string };
    exam?: { id: number; name_en: string; name_bn: string; students: number; passed: number; pass_rate: number; gpa5: number } | null;
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'dashboard.title', href: '/dashboard' }];

const n = (value: number | null | undefined) => formatNumber(value ?? 0, locale.value);

// Share of this month's billing collected so far (display only; the server did the money math).
const collectedShare = computed(() => {
    if (!props.fees || Number(props.fees.billed) === 0) return null;
    return Math.min(100, Math.round((Number(props.fees.collected) / Number(props.fees.billed)) * 100));
});

const quickActions = computed(() =>
    [
        { permission: 'attendance.take', href: '/attendance', label: 'dashboard.takeAttendance', icon: ClipboardCheck },
        { permission: 'fees.collect', href: '/fees/collect', label: 'dashboard.collectFee', icon: BadgeDollarSign },
        { permission: 'marks.enter', href: '/exams', label: 'dashboard.enterMarks', icon: PencilLine },
    ].filter((action) => can(action.permission)),
);
</script>

<template>
    <Head :title="$t('dashboard.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h1 class="text-xl font-semibold">{{ $t('dashboard.today') }}, {{ formatDate(today, locale) }}</h1>
            </div>

            <div v-if="quickActions.length" class="flex flex-wrap gap-2">
                <Button v-for="action in quickActions" :key="action.href" size="lg" variant="outline" as-child>
                    <Link :href="action.href"><component :is="action.icon" class="size-5" />{{ $t(action.label) }}</Link>
                </Button>
            </div>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <!-- Student attendance -->
                <section v-if="attendance" class="rounded-xl border p-4 xl:col-span-2">
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ $t('dashboard.attendanceTitle') }}</h2>
                    <p v-if="attendance.weekend" class="text-lg">{{ $t('dashboard.weekend') }}</p>
                    <template v-else>
                        <p v-if="attendance.recorded === 0" class="text-lg">{{ $t('dashboard.notTaken') }}</p>
                        <div v-else class="flex flex-wrap items-end gap-6">
                            <div class="text-4xl font-bold tabular-nums text-green-700">{{ n(attendance.percent) }}%</div>
                            <dl class="flex gap-4 text-sm">
                                <div v-for="status in ['present', 'absent', 'late', 'leave'] as const" :key="status">
                                    <dt class="text-muted-foreground">{{ $t(`enums.attendance.${status}`) }}</dt>
                                    <dd :class="['text-lg font-semibold tabular-nums', status === 'absent' && attendance[status] > 0 && 'text-red-700']">{{ n(attendance[status]) }}</dd>
                                </div>
                            </dl>
                        </div>
                        <p class="mt-2 text-sm text-muted-foreground">
                            {{ $t('dashboard.sectionsTaken', { taken: n(attendance.sections_taken), total: n(attendance.sections) }) }}
                        </p>
                    </template>
                </section>

                <!-- Staff -->
                <section v-if="staff" class="rounded-xl border p-4">
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ $t('dashboard.staffTitle') }}</h2>
                    <div class="text-4xl font-bold tabular-nums">
                        {{ n(staff.present) }}<span class="text-xl font-normal text-muted-foreground"> / {{ n(staff.total) }}</span>
                    </div>
                </section>

                <!-- Latest exam -->
                <section v-if="exam !== undefined" class="rounded-xl border p-4">
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ $t('dashboard.examTitle') }}</h2>
                    <p v-if="!exam" class="text-sm text-muted-foreground">{{ $t('dashboard.noExam') }}</p>
                    <Link v-else :href="`/exams/${exam.id}/summary`" class="block hover:underline">
                        <div class="font-medium">{{ localized(exam) }}</div>
                        <div class="text-4xl font-bold tabular-nums text-green-700">{{ n(exam.pass_rate) }}%</div>
                        <div class="text-sm text-muted-foreground">
                            {{ $t('dashboard.passRate') }} · {{ $t('dashboard.gpa5') }}: {{ n(exam.gpa5) }}
                        </div>
                    </Link>
                </section>

                <!-- Fees this month -->
                <section v-if="fees" class="rounded-xl border p-4 xl:col-span-2">
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ $t('dashboard.feesTitle') }}</h2>
                    <div class="flex flex-wrap items-end gap-6">
                        <div>
                            <div class="text-sm text-muted-foreground">{{ $t('dashboard.collected') }}</div>
                            <div class="text-3xl font-bold tabular-nums text-green-700">৳ {{ formatMoney(fees.collected, locale) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">{{ $t('dashboard.billed') }}</div>
                            <div class="text-xl font-semibold tabular-nums">৳ {{ formatMoney(fees.billed, locale) }}</div>
                        </div>
                        <div>
                            <div class="text-sm text-muted-foreground">{{ $t('dashboard.dues') }}</div>
                            <div class="text-xl font-semibold tabular-nums text-red-700">৳ {{ formatMoney(fees.dues, locale) }}</div>
                        </div>
                    </div>
                    <div v-if="collectedShare !== null" class="mt-3 h-2 rounded bg-muted" role="presentation">
                        <div class="h-2 rounded bg-green-600" :style="{ width: `${collectedShare}%` }"></div>
                    </div>
                </section>

                <!-- Notices -->
                <section class="rounded-xl border p-4 xl:col-span-2">
                    <h2 class="mb-3 text-sm font-medium text-muted-foreground">{{ $t('dashboard.noticesTitle') }}</h2>
                    <p v-if="notices.length === 0" class="text-sm text-muted-foreground">{{ $t('dashboard.noNotices') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="notice in notices" :key="notice.id" class="py-2">
                            <div class="flex items-start justify-between gap-2">
                                <span class="font-medium">{{ localized({ name_en: notice.title_en, name_bn: notice.title_bn }) }}</span>
                                <span class="shrink-0 text-xs text-muted-foreground">{{ formatDate(notice.published_at, locale) }}</span>
                            </div>
                            <p class="line-clamp-2 text-sm text-muted-foreground">{{ locale === 'bn' ? notice.body_bn || notice.body_en : notice.body_en || notice.body_bn }}</p>
                            <a v-if="notice.has_attachment" :href="`/notices/${notice.id}/attachment`" target="_blank" class="mt-1 inline-flex items-center gap-1 text-sm hover:underline">
                                <Paperclip class="size-3.5" />{{ $t('notices.open') }}
                            </a>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
