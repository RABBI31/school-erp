<script setup lang="ts">
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Send } from 'lucide-vue-next';

interface LogRow {
    id: number;
    to_phone: string;
    message: string;
    type: string;
    status: 'queued' | 'sent' | 'failed';
    parts: number;
    error: string | null;
    created_at: string;
}

defineProps<{
    logs: Paginated<LogRow>;
    filters: Record<string, string>;
    types: string[];
    statuses: string[];
    balance: number;
    month: { sent: number; failed: number; parts: number };
}>();

const { locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'sms.log.title', href: '/sms/log' }];

const columns: DataTableColumn[] = [
    { key: 'created_at', label: 'sms.log.when' },
    { key: 'to_phone', label: 'sms.log.to' },
    { key: 'message', label: 'sms.log.message' },
    { key: 'type', label: 'sms.log.type' },
    { key: 'parts', label: 'sms.log.parts', class: 'text-right' },
    { key: 'status', label: 'sms.log.status' },
];

const statusStyles: Record<LogRow['status'], string> = {
    queued: 'bg-muted text-muted-foreground',
    sent: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    failed: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
};
</script>

<template>
    <Head :title="$t('sms.log.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('sms.log.title')" :description="$t('sms.log.description')">
                <Button as-child>
                    <Link :href="route('sms.create')"><Send class="size-4" />{{ $t('sms.log.compose') }}</Link>
                </Button>
            </PageHeader>

            <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('sms.log.balance') }}</dt>
                    <dd :class="['text-3xl font-bold tabular-nums', balance < 50 && 'text-red-600']">{{ formatNumber(balance, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('sms.log.sentMonth') }}</dt>
                    <dd class="text-3xl font-bold tabular-nums">{{ formatNumber(month.sent, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('sms.log.partsMonth') }}</dt>
                    <dd class="text-3xl font-bold tabular-nums">{{ formatNumber(month.parts, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('sms.log.failedMonth') }}</dt>
                    <dd :class="['text-3xl font-bold tabular-nums', month.failed > 0 && 'text-red-600']">{{ formatNumber(month.failed, locale) }}</dd>
                </div>
            </dl>

            <DataTable :rows="logs" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <NativeSelect v-model="state.type" :aria-label="$t('sms.log.type')" class="sm:w-40">
                        <option value="">{{ $t('sms.log.allTypes') }}</option>
                        <option v-for="type in types" :key="type" :value="type">{{ $t(`enums.smsType.${type}`) }}</option>
                    </NativeSelect>
                    <NativeSelect v-model="state.status" :aria-label="$t('sms.log.status')" class="sm:w-40">
                        <option value="">{{ $t('sms.log.allStatuses') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.smsStatus.${status}`) }}</option>
                    </NativeSelect>
                    <Input v-model="state.date as string" type="date" :aria-label="$t('sms.log.when')" class="sm:w-44" />
                </template>
                <template #cell-created_at="{ row }">
                    <span class="whitespace-nowrap tabular-nums">{{ formatDate(row.created_at, locale) }} {{ formatNumber(row.created_at.slice(11), locale) }}</span>
                </template>
                <template #cell-to_phone="{ row }"><span class="tabular-nums">{{ formatNumber(row.to_phone, locale) }}</span></template>
                <template #cell-message="{ row }">
                    <div class="max-w-md truncate" :title="row.message">{{ row.message }}</div>
                    <div v-if="row.error" class="text-xs text-red-600">{{ row.error }}</div>
                </template>
                <template #cell-type="{ row }">{{ $t(`enums.smsType.${row.type}`) }}</template>
                <template #cell-parts="{ row }"><span class="tabular-nums">{{ formatNumber(row.parts, locale) }}</span></template>
                <template #cell-status="{ row }">
                    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', statusStyles[row.status]]">{{ $t(`enums.smsStatus.${row.status}`) }}</span>
                </template>
            </DataTable>
        </div>
    </AppLayout>
</template>
