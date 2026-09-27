<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CalendarDays, UserCheck, Users } from 'lucide-vue-next';
import { computed, watch } from 'vue';

type Status = 'present' | 'absent' | 'late' | 'leave';

const props = defineProps<{
    sections: { id: number; name: string; class_name_en: string; class_name_bn: string }[];
    sectionId: number | null;
    date: string;
    today: string;
    refusal: string | null;
    taken: boolean;
    students: { enrollment_id: number; roll: number; name_en: string; name_bn: string | null; status: Status }[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'attendance.title', href: '/attendance' }];

const order: Status[] = ['present', 'absent', 'late', 'leave'];
const styles: Record<Status, string> = {
    present: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    absent: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    late: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    leave: 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
};

const statusesFromProps = () => Object.fromEntries(props.students.map((student) => [student.enrollment_id, student.status])) as Record<string, Status>;

const form = useForm({ date: props.date, statuses: statusesFromProps() });

// New section or date: start from what the server sent.
watch(
    () => [props.sectionId, props.date, props.students],
    () => {
        form.defaults({ date: props.date, statuses: statusesFromProps() });
        form.reset();
    },
);

const cycle = (id: number) => {
    const current = form.statuses[id];
    form.statuses[id] = order[(order.indexOf(current) + 1) % order.length];
};

const allPresent = () => Object.keys(form.statuses).forEach((id) => (form.statuses[id] = 'present'));

const counts = computed(() => {
    const result: Record<Status, number> = { present: 0, absent: 0, late: 0, leave: 0 };
    Object.values(form.statuses).forEach((status) => result[status]++);
    return result;
});

const go = (query: { section_id?: number | string | null; date?: string }) =>
    router.get(route('attendance.take'), { section_id: query.section_id ?? props.sectionId ?? undefined, date: query.date ?? props.date });

const save = () => form.put(route('attendance.update', { section: props.sectionId! }), { preserveScroll: true });
</script>

<template>
    <Head :title="$t('attendance.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-2xl space-y-4 p-4 pb-28 md:p-6 md:pb-28">
            <PageHeader :title="$t('attendance.title')" :description="$t('attendance.description')">
                <Button variant="outline" size="sm" as-child>
                    <Link :href="route('attendance.sheet', sectionId ? { section_id: sectionId } : {})"><CalendarDays class="size-4" />{{ $t('attendance.sheet') }}</Link>
                </Button>
                <Button v-if="can('staff.manage')" variant="outline" size="sm" as-child>
                    <Link :href="route('staff-attendance.edit')"><Users class="size-4" />{{ $t('attendance.staff') }}</Link>
                </Button>
            </PageHeader>

            <p v-if="sections.length === 0" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $t('attendance.noSections') }}</p>

            <div v-else class="grid grid-cols-2 gap-2">
                <NativeSelect :model-value="sectionId ?? ''" :aria-label="$t('attendance.section')" class="h-12" @update:model-value="(id) => go({ section_id: id })">
                    <option value="" disabled>{{ $t('attendance.section') }}…</option>
                    <option v-for="section in sections" :key="section.id" :value="section.id">
                        {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                    </option>
                </NativeSelect>
                <Input
                    type="date"
                    :model-value="date"
                    :max="today"
                    :aria-label="$t('attendance.date')"
                    class="h-12"
                    @update:model-value="(value) => value && go({ date: String(value) })"
                />
            </div>

            <template v-if="sectionId">
                <p v-if="refusal" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">{{ refusal }}</p>
                <p v-else-if="taken" class="rounded-md border bg-muted p-3 text-sm">{{ $t('attendance.taken') }}</p>

                <p v-if="students.length === 0" class="text-sm text-muted-foreground">{{ $t('attendance.noStudents') }}</p>

                <ul v-else class="divide-y rounded-xl border">
                    <li v-for="student in students" :key="student.enrollment_id">
                        <button
                            type="button"
                            class="flex min-h-14 w-full items-center gap-3 px-3 py-2 text-left active:bg-muted disabled:opacity-60"
                            :disabled="!!refusal"
                            @click="cycle(student.enrollment_id)"
                        >
                            <span class="w-10 shrink-0 text-center text-lg font-semibold tabular-nums">{{ formatNumber(student.roll, locale) }}</span>
                            <span class="flex-1 truncate">{{ localized({ name_en: student.name_en, name_bn: student.name_bn ?? student.name_en }) }}</span>
                            <span :class="['w-24 shrink-0 rounded-full px-3 py-1.5 text-center text-sm font-medium', styles[form.statuses[student.enrollment_id]]]">
                                {{ $t(`enums.attendance.${form.statuses[student.enrollment_id]}`) }}
                            </span>
                        </button>
                    </li>
                </ul>
            </template>
        </div>

        <!-- Sticky save bar: counts and save, always in reach of the thumb. -->
        <div
            v-if="sectionId && students.length > 0 && !refusal"
            class="sticky bottom-0 z-10 border-t bg-background/95 px-4 pt-3 backdrop-blur"
            style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom, 0px))"
        >
            <div class="mx-auto flex max-w-2xl flex-wrap items-center gap-2">
                <div class="flex flex-1 flex-wrap gap-1 text-xs">
                    <span v-for="status in order" :key="status" :class="['rounded-full px-2 py-1 font-medium', styles[status]]">
                        {{ $t(`enums.attendanceShort.${status}`) }} {{ formatNumber(counts[status], locale) }}
                    </span>
                </div>
                <Button variant="ghost" size="sm" @click="allPresent"><UserCheck class="size-4" />{{ $t('attendance.allPresent') }}</Button>
                <Button size="lg" class="w-full sm:w-auto" :disabled="form.processing" @click="save">{{ $t('attendance.save') }}</Button>
            </div>
            <p v-if="form.errors.date || (form.errors as Record<string, string>).statuses" class="mx-auto mt-2 max-w-2xl text-sm text-red-600">
                {{ form.errors.date ?? (form.errors as Record<string, string>).statuses }}
            </p>
        </div>
    </AppLayout>
</template>
