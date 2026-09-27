<script setup lang="ts">
import InvoiceStatusBadge from '@/components/fees/InvoiceStatusBadge.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, formatMonth, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { CollectStudent, FeeStudent, PaymentMethod } from '@/types/fees';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { Phone, Printer, Search, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    search: string;
    results: (FeeStudent & { enrollment_id: number })[];
    student: CollectStudent | null;
    methods: PaymentMethod[];
    receiptId: number | null;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.collect.title', href: '/fees/collect' },
];

const studentName = (s: { name_en: string; name_bn: string | null }) => localized({ name_en: s.name_en, name_bn: s.name_bn ?? s.name_en });
const className = (s: FeeStudent) => `${localized({ name_en: s.class_name_en, name_bn: s.class_name_bn })} — ${s.section}`;
const plain = (amount: string) => amount.replace(/\.00$/, '');
const receiptUrl = (id: number) => route('fees.payments.receipt', { payment: id });

// Search: typing reloads the results only.
const query = ref(props.search);
watchDebounced(
    query,
    (value) => router.get(route('fees.collect'), value ? { search: value } : {}, { preserveState: true, replace: true, only: ['search', 'results'] }),
    { debounce: 300 },
);

const pick = (enrollmentId: number) => router.get(route('fees.collect'), { enrollment_id: enrollmentId });
const clear = () => {
    query.value = '';
    router.get(route('fees.collect'));
};

const form = useForm({
    enrollment_id: props.student?.enrollment_id ?? 0,
    amount: props.student ? plain(props.student.due) : '',
    method: 'cash' as PaymentMethod,
    transaction_ref: '',
    note: '',
});

// A new student (or fresh dues after saving): start the form again.
watch(
    () => props.student,
    (student) => {
        form.defaults({ enrollment_id: student?.enrollment_id ?? 0, amount: student ? plain(student.due) : '', method: 'cash', transaction_ref: '', note: '' });
        form.reset();
        form.clearErrors();
    },
);

const save = () => form.post(route('fees.payments.store'), { preserveScroll: true });
</script>

<template>
    <Head :title="$t('fees.collect.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="mx-auto w-full max-w-3xl space-y-4 p-4 md:p-6">
            <PageHeader :title="$t('fees.collect.title')" :description="$t('fees.collect.description')" />

            <!-- Receipt of the payment just saved -->
            <a
                v-if="receiptId"
                :href="receiptUrl(receiptId)"
                target="_blank"
                class="flex items-center justify-center gap-2 rounded-xl border-2 border-green-600 bg-green-50 p-4 font-medium text-green-800 hover:bg-green-100 dark:bg-green-950 dark:text-green-200"
            >
                <Printer class="size-5" />{{ $t('fees.collect.printReceipt') }}
            </a>

            <template v-if="!student">
                <div class="relative">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input v-model="query" type="search" autofocus :placeholder="$t('fees.collect.searchPlaceholder')" class="h-12 pl-9 text-base" />
                </div>

                <p v-if="search && results.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.collect.noResults') }}</p>

                <ul v-if="results.length" class="divide-y rounded-xl border">
                    <li v-for="result in results" :key="result.enrollment_id">
                        <button type="button" class="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-muted/50" @click="pick(result.enrollment_id)">
                            <span class="flex-1">
                                <span class="block font-medium">{{ studentName(result) }}</span>
                                <span class="block text-sm text-muted-foreground">
                                    {{ className(result) }} · {{ $t('fees.fields.roll') }} {{ formatNumber(result.roll, locale) }} · {{ result.student_uid }}
                                </span>
                            </span>
                        </button>
                    </li>
                </ul>
            </template>

            <template v-else>
                <!-- Student -->
                <section class="flex flex-wrap items-start justify-between gap-3 rounded-xl border p-4">
                    <div>
                        <Link :href="`/students/${student.student_id}`" class="text-lg font-semibold hover:underline">{{ studentName(student) }}</Link>
                        <div class="text-sm text-muted-foreground">
                            {{ className(student) }} · {{ $t('fees.fields.roll') }} {{ formatNumber(student.roll, locale) }} · {{ student.student_uid }}
                        </div>
                        <a v-if="student.guardian_phone" :href="`tel:${student.guardian_phone}`" class="mt-1 flex items-center gap-1 text-sm hover:underline">
                            <Phone class="size-3.5" />{{ formatNumber(student.guardian_phone, locale) }}
                        </a>
                    </div>
                    <div class="text-right">
                        <div class="text-xs text-muted-foreground">{{ $t('fees.collect.totalDue') }}</div>
                        <div :class="['text-3xl font-bold tabular-nums', student.due === '0.00' ? 'text-green-700' : 'text-red-700']">
                            ৳ {{ formatMoney(student.due, locale) }}
                        </div>
                        <Button variant="ghost" size="sm" class="mt-1" @click="clear"><X class="size-4" />{{ $t('fees.collect.change') }}</Button>
                    </div>
                </section>

                <!-- Unpaid invoices -->
                <section class="rounded-xl border">
                    <h2 class="border-b px-4 py-3 font-semibold">{{ $t('fees.collect.invoices') }}</h2>
                    <p v-if="student.invoices.length === 0" class="p-4 text-sm text-muted-foreground">{{ $t('fees.collect.nothingDue') }}</p>
                    <ul v-else class="divide-y">
                        <li v-for="invoice in student.invoices" :key="invoice.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3">
                            <div class="min-w-0 flex-1">
                                <div class="font-medium">
                                    {{ formatMonth(invoice.month, locale) }}
                                    <span class="ml-1 font-mono text-xs text-muted-foreground">{{ invoice.invoice_no }}</span>
                                </div>
                                <div class="truncate text-sm text-muted-foreground">
                                    {{ invoice.heads.map((head) => `${localized(head)} ${formatMoney(head.amount, locale)}`).join(' · ') }}
                                </div>
                                <div class="text-xs text-muted-foreground">{{ $t('fees.fields.dueDate') }}: {{ formatDate(invoice.due_date, locale) }}</div>
                            </div>
                            <InvoiceStatusBadge :status="invoice.status" />
                            <div class="w-28 text-right font-semibold tabular-nums">৳ {{ formatMoney(invoice.due, locale) }}</div>
                        </li>
                    </ul>
                </section>

                <!-- Payment -->
                <form v-if="student.invoices.length" class="space-y-4 rounded-xl border p-4" @submit.prevent="save">
                    <FormField id="amount" :label="$t('fees.fields.amount')" :error="form.errors.amount" required v-slot="{ id }">
                        <div class="flex gap-2">
                            <Input :id="id" v-model="form.amount" type="number" inputmode="decimal" min="1" step="0.01" required class="h-12 text-lg tabular-nums" />
                            <Button type="button" variant="outline" class="h-12" @click="form.amount = plain(student.due)">{{ $t('fees.collect.payAll') }}</Button>
                        </div>
                    </FormField>

                    <fieldset>
                        <legend class="mb-2 text-sm font-medium">{{ $t('fees.fields.method') }}</legend>
                        <div class="flex flex-wrap gap-2">
                            <label
                                v-for="method in methods"
                                :key="method"
                                :class="[
                                    'cursor-pointer rounded-md border px-4 py-2 text-sm has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-ring',
                                    form.method === method ? 'border-primary bg-primary text-primary-foreground' : 'hover:bg-muted',
                                ]"
                            >
                                <input v-model="form.method" type="radio" name="method" :value="method" class="sr-only" />
                                {{ $t(`enums.paymentMethod.${method}`) }}
                            </label>
                        </div>
                        <p v-if="form.errors.method" class="mt-1 text-sm text-red-600">{{ form.errors.method }}</p>
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField v-if="form.method !== 'cash'" id="transaction_ref" :label="$t('fees.fields.reference')" :error="form.errors.transaction_ref" v-slot="{ id }">
                            <Input :id="id" v-model="form.transaction_ref" maxlength="50" />
                        </FormField>
                        <FormField id="note" :label="$t('fees.fields.note')" :error="form.errors.note" v-slot="{ id }">
                            <Input :id="id" v-model="form.note" maxlength="255" />
                        </FormField>
                    </div>

                    <p class="text-xs text-muted-foreground">{{ $t('fees.collect.oldestFirst') }}</p>
                    <Button type="submit" size="lg" class="w-full sm:w-auto" :disabled="form.processing">{{ $t('fees.collect.save') }}</Button>
                </form>

                <!-- Recent payments -->
                <section v-if="student.payments.length" class="rounded-xl border">
                    <h2 class="border-b px-4 py-3 font-semibold">{{ $t('fees.collect.recentPayments') }}</h2>
                    <ul class="divide-y">
                        <li v-for="payment in student.payments" :key="payment.id" class="flex items-center gap-3 px-4 py-2 text-sm">
                            <span class="font-mono text-xs">{{ payment.receipt_no }}</span>
                            <span class="text-muted-foreground">{{ formatDate(payment.paid_at, locale) }}</span>
                            <span class="text-muted-foreground">{{ $t(`enums.paymentMethod.${payment.method}`) }}</span>
                            <span class="ml-auto font-medium tabular-nums">৳ {{ formatMoney(payment.amount, locale) }}</span>
                            <a :href="receiptUrl(payment.id)" target="_blank" :aria-label="$t('fees.reports.daily.receipt')" class="rounded p-1 hover:bg-muted">
                                <Printer class="size-4" />
                            </a>
                        </li>
                    </ul>
                </section>
            </template>
        </div>
    </AppLayout>
</template>
