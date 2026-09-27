<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';

type Status = 'present' | 'absent' | 'late' | 'leave';

const props = defineProps<{
    sections: { id: number; name: string; class_name_en: string; class_name_bn: string }[];
    sectionId: number | null;
    month: string;
    days: number;
    fridays: number[];
    rows: {
        enrollment_id: number;
        roll: number;
        name_en: string;
        name_bn: string | null;
        days: Record<string, Status>;
        stats: { days: number; present: number; absent: number; late: number; leave: number; percent: number | null };
    }[];
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'attendance.title', href: '/attendance' },
    { title: 'attendance.sheet', href: '/attendance/sheet' },
];

const cellStyles: Record<Status, string> = {
    present: 'text-green-700 dark:text-green-300',
    absent: 'bg-red-100 font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-200',
    late: 'text-amber-700 dark:text-amber-300',
    leave: 'text-blue-700 dark:text-blue-300',
};

const go = (query: { section_id?: number | string | null; month?: string }) =>
    router.get(route('attendance.sheet'), { section_id: query.section_id ?? props.sectionId ?? undefined, month: query.month ?? props.month });

const dayNumbers = () => Array.from({ length: props.days }, (_, i) => i + 1);
</script>

<template>
    <Head :title="$t('attendance.sheet')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-4 p-4 md:p-6">
            <PageHeader :title="$t('attendance.sheet')" :description="$t('attendance.sheetDescription')" />

            <div class="flex flex-col gap-2 sm:flex-row">
                <NativeSelect
                    :model-value="sectionId ?? ''"
                    :aria-label="$t('attendance.section')"
                    class="sm:w-60"
                    @update:model-value="(id) => go({ section_id: id })"
                >
                    <option value="" disabled>{{ $t('attendance.section') }}…</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">
                        {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                    </option>
                </NativeSelect>
                <Input
                    type="month"
                    :model-value="month"
                    :aria-label="$t('attendance.month')"
                    class="sm:w-48"
                    @update:model-value="(value) => value && go({ month: String(value) })"
                />
            </div>

            <p v-if="!sectionId" class="text-sm text-muted-foreground">{{ $t('attendance.pickSection') }}</p>
            <p v-else-if="rows.length === 0" class="text-sm text-muted-foreground">{{ $t('attendance.noStudents') }}</p>

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full border-collapse text-xs">
                    <thead>
                        <tr class="border-b bg-muted/50">
                            <th class="sticky left-0 z-10 bg-muted px-2 py-2 text-left">{{ $t('attendance.roll') }}</th>
                            <th class="sticky left-10 z-10 min-w-36 bg-muted px-2 py-2 text-left">{{ $t('attendance.student') }}</th>
                            <th
                                v-for="day in dayNumbers()"
                                :key="day"
                                :class="['w-7 px-1 py-2 text-center font-medium', fridays.includes(day) && 'bg-neutral-200 dark:bg-neutral-800']"
                            >
                                {{ formatNumber(day, locale) }}
                            </th>
                            <th class="px-2 py-2 text-center">{{ $t('enums.attendanceShort.present') }}</th>
                            <th class="px-2 py-2 text-center">{{ $t('enums.attendanceShort.absent') }}</th>
                            <th class="px-2 py-2 text-center">{{ $t('attendance.percent') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.enrollment_id" class="border-b last:border-0">
                            <td class="sticky left-0 z-10 bg-background px-2 py-1.5 tabular-nums">{{ formatNumber(row.roll, locale) }}</td>
                            <td class="sticky left-10 z-10 min-w-36 truncate bg-background px-2 py-1.5">
                                {{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}
                            </td>
                            <td
                                v-for="day in dayNumbers()"
                                :key="day"
                                :class="[
                                    'px-1 py-1.5 text-center',
                                    fridays.includes(day) && 'bg-neutral-200 dark:bg-neutral-800',
                                    row.days[day] && cellStyles[row.days[day]],
                                ]"
                            >
                                {{ row.days[day] ? $t(`enums.attendanceShort.${row.days[day]}`) : '' }}
                            </td>
                            <td class="px-2 py-1.5 text-center tabular-nums">{{ formatNumber(row.stats.present + row.stats.late, locale) }}</td>
                            <td class="px-2 py-1.5 text-center tabular-nums">{{ formatNumber(row.stats.absent, locale) }}</td>
                            <td class="px-2 py-1.5 text-center tabular-nums">
                                {{ row.stats.percent === null ? '—' : formatNumber(row.stats.percent, locale) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
