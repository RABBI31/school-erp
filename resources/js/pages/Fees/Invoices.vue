<script setup lang="ts">
import InvoiceStatusBadge from '@/components/fees/InvoiceStatusBadge.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatMoney, formatMonth, formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { InvoiceRow, InvoiceStatus, SectionOption } from '@/types/fees';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { FilePlus2 } from 'lucide-vue-next';
import { ref } from 'vue';

const props = defineProps<{
    invoices: Paginated<InvoiceRow>;
    filters: Record<string, string>;
    sections: SectionOption[];
    statuses: InvoiceStatus[];
    thisMonth: string;
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.invoices.title', href: '/fees/invoices' },
];

const columns: DataTableColumn[] = [
    { key: 'invoice_no', label: 'fees.fields.invoiceNo' },
    { key: 'month', label: 'fees.fields.month' },
    { key: 'student', label: 'fees.fields.student' },
    { key: 'class', label: 'fees.fields.class' },
    { key: 'total', label: 'fees.fields.total', class: 'text-right' },
    { key: 'paid_total', label: 'fees.fields.paid', class: 'text-right' },
    { key: 'due_date', label: 'fees.fields.dueDate' },
    { key: 'status', label: 'fees.fields.status' },
];

const generating = ref(false);
const form = useForm({ month: props.filters.month || props.thisMonth });
const generate = () => form.post(route('fees.invoices.generate'), { preserveScroll: true, onSuccess: () => (generating.value = false) });
</script>

<template>
    <Head :title="$t('fees.invoices.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader
                :title="$t('fees.invoices.title')"
                :description="$t('fees.invoices.description', { year: formatNumber($page.props.academicYear?.name ?? '', locale) })"
            >
                <Button v-if="can('fees.manage')" @click="generating = true"><FilePlus2 class="size-4" />{{ $t('fees.invoices.generate') }}</Button>
            </PageHeader>

            <DataTable :rows="invoices" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <Input v-model="state.month as string" type="month" :aria-label="$t('fees.fields.month')" class="sm:w-44" />
                    <NativeSelect v-model="state.section_id" :aria-label="$t('fees.fields.section')" class="sm:w-52">
                        <option value="">{{ $t('fees.invoices.allSections') }}</option>
                        <option v-for="section in sections" :key="section.id" :value="section.id">
                            {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                        </option>
                    </NativeSelect>
                    <NativeSelect v-model="state.status" :aria-label="$t('fees.fields.status')" class="sm:w-44">
                        <option value="">{{ $t('fees.invoices.allStatuses') }}</option>
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.invoiceStatus.${status}`) }}</option>
                    </NativeSelect>
                </template>
                <template #cell-invoice_no="{ row }"><span class="font-mono text-xs">{{ row.invoice_no }}</span></template>
                <template #cell-month="{ row }">{{ formatMonth(row.month, locale) }}</template>
                <template #cell-student="{ row }">
                    <Link :href="`/students/${row.enrollment.student.id}`" class="hover:underline">
                        {{ localized({ name_en: row.enrollment.student.name_en, name_bn: row.enrollment.student.name_bn ?? row.enrollment.student.name_en }) }}
                    </Link>
                </template>
                <template #cell-class="{ row }">
                    {{ localized(row.enrollment.section.school_class) }} — {{ row.enrollment.section.name }}, {{ formatNumber(row.enrollment.roll, locale) }}
                </template>
                <template #cell-total="{ row }"><span class="tabular-nums">{{ formatMoney(row.total, locale) }}</span></template>
                <template #cell-paid_total="{ row }"><span class="tabular-nums">{{ formatMoney(row.paid_total, locale) }}</span></template>
                <template #cell-due_date="{ row }">{{ formatDate(row.due_date, locale) }}</template>
                <template #cell-status="{ row }"><InvoiceStatusBadge :status="row.status" /></template>
            </DataTable>
        </div>

        <Dialog v-model:open="generating">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="generate">
                    <DialogHeader>
                        <DialogTitle>{{ $t('fees.invoices.generateTitle') }}</DialogTitle>
                        <DialogDescription>{{ $t('fees.invoices.generateHelp') }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="month" :label="$t('fees.fields.month')" :error="form.errors.month" required v-slot="{ id }">
                        <Input :id="id" v-model="form.month" type="month" required />
                    </FormField>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="generating = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('fees.invoices.generate') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
