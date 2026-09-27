<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Input } from '@/components/ui/input';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { Money, MonthlyClassRow, PaymentMethod } from '@/types/fees';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    month: string;
    report: {
        classes: MonthlyClassRow[];
        billed: { subtotal: Money; waived: Money; total: Money; paid: Money; due: Money };
        collected: Partial<Record<PaymentMethod, Money>>;
        collected_total: Money;
    };
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.reports.monthly.title', href: '/fees/reports/monthly' },
];

const go = (month: string) => router.get(route('fees.reports.monthly'), { month }, { preserveState: true });

const amountColumns = ['subtotal', 'waived', 'total', 'paid', 'due'] as const;
</script>

<template>
    <Head :title="$t('fees.reports.monthly.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('fees.reports.monthly.title')" :description="$t('fees.reports.monthly.description')">
                <Input type="month" :model-value="month" :aria-label="$t('fees.fields.month')" class="w-44" @update:model-value="(v) => v && go(String(v))" />
            </PageHeader>

            <dl class="grid grid-cols-2 gap-4 md:grid-cols-4">
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('fees.reports.monthly.billed') }}</dt>
                    <dd class="text-2xl font-bold tabular-nums">৳ {{ formatMoney(report.billed.total, locale) }}</dd>
                    <dd class="text-xs text-muted-foreground">{{ $t('fees.fields.waived') }}: {{ formatMoney(report.billed.waived, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('fees.fields.paid') }}</dt>
                    <dd class="text-2xl font-bold tabular-nums text-green-700">৳ {{ formatMoney(report.billed.paid, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('fees.fields.due') }}</dt>
                    <dd class="text-2xl font-bold tabular-nums text-red-700">৳ {{ formatMoney(report.billed.due, locale) }}</dd>
                </div>
                <div class="rounded-xl border p-4">
                    <dt class="text-sm text-muted-foreground">{{ $t('fees.reports.monthly.collected') }}</dt>
                    <dd class="text-2xl font-bold tabular-nums">৳ {{ formatMoney(report.collected_total, locale) }}</dd>
                    <dd v-for="(amount, method) in report.collected" :key="method" class="flex justify-between text-xs text-muted-foreground">
                        <span>{{ $t(`enums.paymentMethod.${method}`) }}</span><span class="tabular-nums">{{ formatMoney(amount, locale) }}</span>
                    </dd>
                </div>
            </dl>

            <p v-if="report.classes.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.reports.monthly.none') }}</p>

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-3 py-2">{{ $t('fees.fields.class') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('fees.reports.monthly.invoices') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('fees.reports.monthly.paidInvoices') }}</th>
                            <th v-for="column in amountColumns" :key="column" class="px-3 py-2 text-right">{{ $t(`fees.fields.${column}`) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in report.classes" :key="row.id" class="border-b">
                            <td class="px-3 py-2">{{ localized(row) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(row.invoices, locale) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(row.paid_invoices, locale) }}</td>
                            <td v-for="column in amountColumns" :key="column" class="px-3 py-2 text-right tabular-nums">{{ formatMoney(row[column], locale) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-muted/50 font-semibold">
                            <td class="px-3 py-2" colspan="3">{{ $t('fees.fields.total') }}</td>
                            <td v-for="column in amountColumns" :key="column" class="px-3 py-2 text-right tabular-nums">{{ formatMoney(report.billed[column], locale) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AppLayout>
</template>
