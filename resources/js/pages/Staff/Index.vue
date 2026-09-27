<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { StaffRow, StaffStatus } from '@/types/staff';
import { Head, Link } from '@inertiajs/vue3';
import { ClipboardList, UserPlus } from 'lucide-vue-next';

defineProps<{
    rows: Paginated<StaffRow>;
    filters: Record<string, string>;
    statuses: StaffStatus[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'staff.title', href: '/staff' }];

const columns: DataTableColumn[] = [
    { key: 'employee_code', label: 'staff.code', class: 'w-24' },
    { key: 'name', label: 'staff.name' },
    { key: 'designation', label: 'staff.designation' },
    { key: 'phone', label: 'staff.phone' },
    { key: 'login', label: 'staff.login.title' },
    { key: 'status', label: 'staff.status' },
];
</script>

<template>
    <Head :title="$t('staff.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('staff.title')" :description="$t('staff.description')">
                <template v-if="can('staff.manage')">
                    <Button variant="outline" as-child>
                        <Link :href="route('staff.assignments.edit')"><ClipboardList class="size-4" />{{ $t('staff.assignments') }}</Link>
                    </Button>
                    <Button as-child>
                        <Link :href="route('staff.create')"><UserPlus class="size-4" />{{ $t('staff.add') }}</Link>
                    </Button>
                </template>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <NativeSelect v-model="state.status" class="sm:w-40" :aria-label="$t('staff.status')">
                        <option value="">{{ $t('staff.status') }}: {{ $t('common.all') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.staffStatus.${status}`) }}</option>
                    </NativeSelect>
                </template>
                <template #cell-name="{ row }">
                    <Link v-if="can('staff.manage')" :href="route('staff.edit', { staff: row.id })" class="font-medium hover:underline">
                        {{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}
                    </Link>
                    <span v-else>{{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}</span>
                </template>
                <template #cell-phone="{ row }">{{ formatNumber(row.phone, locale) }}</template>
                <template #cell-login="{ row }">
                    <span v-if="row.user?.roles[0]" class="rounded-full bg-muted px-2 py-0.5 text-xs">{{
                        $t(`enums.role.${row.user.roles[0].name}`)
                    }}</span>
                </template>
                <template #cell-status="{ row }">{{ $t(`enums.staffStatus.${row.status}`) }}</template>
            </DataTable>
        </div>
    </AppLayout>
</template>
