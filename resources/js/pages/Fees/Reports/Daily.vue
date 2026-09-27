<script setup lang="ts">
import CancelPaymentDialog from '@/components/fees/CancelPaymentDialog.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { DailyPayment, Money, PaymentMethod } from '@/types/fees';
import { Head, Link, router } from '@inertiajs/vue3';
import { Ban, Printer } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    date: string;
    today: string;
    report: { payments: DailyPayment[]; by_method: Partial<Record<PaymentMethod, Money>>; by_collector: Record<string, Money>; total: Money };
    methods: PaymentMethod[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.reports.daily.title', href: '/fees/reports/daily' },
];

const cancelling = ref<DailyPayment | null>(null);
const go = (date: string) => router.get(route('fees.reports.daily'), { date }, { preserveState: true });
</script>

<template>
    <Head :title="$t('fees.reports.daily.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('fees.reports.daily.title')" :description="$t('fees.reports.daily.description')">
                <Input type="date" :model-value="date" :max="today" :aria-label="$t('fees.fields.date')" class="w-44" @update:model-value="(v) => v && go(String(v))" />
            </PageHeader>

            <div class="grid gap-4 md:grid-cols-3">
                <div class="rounded-xl border p-4">
                    <div class="text-sm text-muted-foreground">{{ $t('fees.reports.daily.total') }}</div>
                    <div class="text-3xl font-bold tabular-nums">৳ {{ formatMoney(report.total, locale) }}</div>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="mb-2 text-sm text-muted-foreground">{{ $t('fees.reports.daily.byMethod') }}</div>
                    <dl class="space-y-1 text-sm">
                        <div v-for="(amount, method) in report.by_method" :key="method" class="flex justify-between">
                            <dt>{{ $t(`enums.paymentMethod.${method}`) }}</dt>
                            <dd class="tabular-nums">{{ formatMoney(amount, locale) }}</dd>
                        </div>
                    </dl>
                </div>
                <div class="rounded-xl border p-4">
                    <div class="mb-2 text-sm text-muted-foreground">{{ $t('fees.reports.daily.byCollector') }}</div>
                    <dl class="space-y-1 text-sm">
                        <div v-for="(amount, name) in report.by_collector" :key="name" class="flex justify-between">
                            <dt>{{ name }}</dt>
                            <dd class="tabular-nums">{{ formatMoney(amount, locale) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <p v-if="report.payments.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.reports.daily.none') }}</p>

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-3 py-2">{{ $t('fees.fields.time') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.receiptNo') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.student') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.class') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.method') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.collector') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('fees.fields.amount') }}</th>
                            <th class="px-3 py-2"><span class="sr-only">{{ $t('common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="payment in report.payments" :key="payment.id" :class="['border-b last:border-0', payment.cancelled && 'text-muted-foreground']">
                            <td class="px-3 py-2 tabular-nums">{{ formatNumber(payment.time, locale) }}</td>
                            <td class="px-3 py-2 font-mono text-xs">
                                <span :class="payment.cancelled && 'line-through'">{{ payment.receipt_no }}</span>
                                <div v-if="payment.cancelled" class="font-sans text-xs text-red-700">
                                    {{ $t('fees.reports.daily.cancelled') }}: {{ payment.cancel_reason }} ({{ payment.cancelled_by }})
                                </div>
                            </td>
                            <td class="px-3 py-2">
                                <Link :href="`/students/${payment.student_id}`" class="hover:underline">
                                    {{ localized({ name_en: payment.name_en, name_bn: payment.name_bn ?? payment.name_en }) }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap">
                                {{ localized({ name_en: payment.class_name_en, name_bn: payment.class_name_bn }) }} — {{ payment.section }}, {{ formatNumber(payment.roll, locale) }}
                            </td>
                            <td class="px-3 py-2">
                                {{ $t(`enums.paymentMethod.${payment.method}`) }}
                                <div v-if="payment.transaction_ref" class="text-xs text-muted-foreground">{{ payment.transaction_ref }}</div>
                            </td>
                            <td class="px-3 py-2">{{ payment.received_by }}</td>
                            <td :class="['px-3 py-2 text-right tabular-nums', payment.cancelled && 'line-through']">{{ formatMoney(payment.amount, locale) }}</td>
                            <td class="px-3 py-2">
                                <div class="flex justify-end gap-1">
                                    <Button variant="ghost" size="icon" as-child>
                                        <a :href="route('fees.payments.receipt', { payment: payment.id })" target="_blank" :aria-label="$t('fees.reports.daily.receipt')">
                                            <Printer class="size-4" />
                                        </a>
                                    </Button>
                                    <Button
                                        v-if="can('payments.cancel') && !payment.cancelled"
                                        variant="ghost"
                                        size="icon"
                                        :aria-label="$t('fees.cancel.action')"
                                        @click="cancelling = payment"
                                    >
                                        <Ban class="size-4 text-red-600" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <CancelPaymentDialog :payment="cancelling" @close="cancelling = null" />
    </AppLayout>
</template>
