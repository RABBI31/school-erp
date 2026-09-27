<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

type Status = 'present' | 'absent' | 'late' | 'leave';

const props = defineProps<{
    date: string;
    today: string;
    rows: { staff_id: number; name_en: string; name_bn: string | null; designation: string | null; status: Status | null; in_time: string | null; out_time: string | null }[];
}>();

const { localized } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'attendance.title', href: '/attendance' },
    { title: 'attendance.staff', href: '/staff-attendance' },
];

const statuses: Status[] = ['present', 'absent', 'late', 'leave'];

type Row = { status: Status | ''; in_time: string; out_time: string };

const fromProps = () =>
    Object.fromEntries(
        props.rows.map((row) => [row.staff_id, { status: row.status ?? '', in_time: row.in_time ?? '', out_time: row.out_time ?? '' }]),
    ) as Record<string, Row>;

const form = useForm({ date: props.date, rows: fromProps() });

watch(
    () => [props.date, props.rows],
    () => {
        form.defaults({ date: props.date, rows: fromProps() });
        form.reset();
    },
);

// Only rows with a status are saved; blank means "not recorded".
const save = () =>
    form
        .transform((data) => ({
            date: data.date,
            rows: Object.fromEntries(
                Object.entries(data.rows)
                    .filter(([, row]) => row.status !== '')
                    .map(([id, row]) => [id, { status: row.status, in_time: row.in_time || null, out_time: row.out_time || null }]),
            ),
        }))
        .put(route('staff-attendance.update'), { preserveScroll: true });

const allPresent = () => Object.values(form.rows).forEach((row) => (row.status ||= 'present'));
</script>

<template>
    <Head :title="$t('attendance.staff')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
            <PageHeader :title="$t('attendance.staff')" :description="$t('attendance.staffDescription')" />

            <div class="flex gap-2">
                <Input
                    type="date"
                    :model-value="date"
                    :max="today"
                    :aria-label="$t('attendance.date')"
                    class="h-11 w-48"
                    @update:model-value="(value) => value && router.get(route('staff-attendance.edit'), { date: String(value) })"
                />
                <Button variant="outline" class="h-11" @click="allPresent">{{ $t('attendance.allPresent') }}</Button>
            </div>

            <ul class="divide-y rounded-xl border">
                <li v-for="row in rows" :key="row.staff_id" class="grid gap-2 p-3 sm:grid-cols-[1fr_9rem_6rem_6rem] sm:items-center">
                    <div>
                        <div class="font-medium">{{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}</div>
                        <div class="text-xs text-muted-foreground">{{ row.designation }}</div>
                    </div>
                    <NativeSelect v-model="form.rows[row.staff_id].status" :aria-label="row.name_en">
                        <option value="">{{ $t('attendance.notRecorded') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.attendance.${status}`) }}</option>
                    </NativeSelect>
                    <Input v-model="form.rows[row.staff_id].in_time" type="time" :aria-label="$t('attendance.inTime')" />
                    <Input v-model="form.rows[row.staff_id].out_time" type="time" :aria-label="$t('attendance.outTime')" />
                </li>
            </ul>

            <p v-if="form.errors.date || (form.errors as Record<string, string>).rows" class="text-sm text-red-600">
                {{ form.errors.date ?? (form.errors as Record<string, string>).rows }}
            </p>
            <Button size="lg" :disabled="form.processing" @click="save">{{ $t('common.save') }}</Button>
        </div>
    </AppLayout>
</template>
