<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/students/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { ClassOption } from '@/types/setup';
import type { SectionOption, StudentRow, StudentStatus } from '@/types/students';
import { Head, Link } from '@inertiajs/vue3';
import { FileUp, UserPlus } from 'lucide-vue-next';

defineProps<{
    rows: Paginated<StudentRow>;
    filters: Record<string, string>;
    classes: ClassOption[];
    sections: SectionOption[];
    statuses: StudentStatus[];
    hasYear: boolean;
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'students.title', href: '/students' }];

const columns: DataTableColumn[] = [
    { key: 'roll', label: 'students.roll', class: 'w-16' },
    { key: 'student', label: 'students.student' },
    { key: 'class', label: 'students.classSection' },
    { key: 'group', label: 'students.group' },
    { key: 'status', label: 'students.status' },
];
</script>

<template>
    <Head :title="$t('students.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('students.title')" :description="$t('students.description')">
                <template v-if="can('students.manage')">
                    <Button variant="outline" as-child>
                        <Link :href="route('students.import.create')"><FileUp class="size-4" />{{ $t('students.import') }}</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('students.create')"><UserPlus class="size-4" />{{ $t('students.admit') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <p v-if="!hasYear" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $t('students.noYear') }}</p>

            <DataTable v-else :rows="rows" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <NativeSelect v-model="state.school_class_id" class="sm:w-40" :aria-label="$t('students.class')" @change="state.section_id = ''">
                        <option value="">{{ $t('students.class') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in classes" :key="option.id" :value="String(option.id)">{{ localized(option) }}</option>
                    </NativeSelect>
                    <NativeSelect v-model="state.section_id" class="sm:w-40" :aria-label="$t('students.section')">
                        <option value="">{{ $t('students.section') }}: {{ $t('common.all') }}</option>
                        <option
                            v-for="section in sections.filter((s) => !state.school_class_id || s.school_class_id === Number(state.school_class_id))"
                            :key="section.id"
                            :value="String(section.id)"
                        >
                            {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                        </option>
                    </NativeSelect>
                    <NativeSelect v-model="state.status" class="sm:w-40" :aria-label="$t('students.status')">
                        <option value="">{{ $t('students.status') }}: {{ $t('common.all') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.studentStatus.${status}`) }}</option>
                    </NativeSelect>
                </template>
                <template #cell-roll="{ row }">{{ formatNumber(row.roll, locale) }}</template>
                <template #cell-student="{ row }">
                    <Link :href="route('students.show', { student: row.student.id })" class="font-medium hover:underline">
                        {{ localized({ name_en: row.student.name_en, name_bn: row.student.name_bn ?? row.student.name_en }) }}
                    </Link>
                    <div class="text-xs text-muted-foreground">{{ row.student.student_uid }}</div>
                </template>
                <template #cell-class="{ row }">{{ localized(row.section.school_class) }} — {{ row.section.name }}</template>
                <template #cell-group="{ row }">{{ row.group?.name ?? '' }}</template>
                <template #cell-status="{ row }"><StatusBadge :status="row.student.status" /></template>
            </DataTable>
        </div>
    </AppLayout>
</template>
