<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatMoney, formatMonth, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ClassOption, DueRow, SectionOption } from '@/types/fees';
import { Head, Link, router } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';

const props = defineProps<{
    rows: DueRow[];
    filters: { school_class_id?: string; section_id?: string };
    classes: ClassOption[];
    sections: SectionOption[];
    smsBalance: number;
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.reports.dues.title', href: '/fees/reports/dues' },
];

const filters = reactive({ school_class_id: props.filters.school_class_id ?? '', section_id: props.filters.section_id ?? '' });
const visibleSections = computed(() => props.sections.filter((s) => !filters.school_class_id || String(s.school_class_id) === String(filters.school_class_id)));
const query = () => Object.fromEntries(Object.entries(filters).filter(([, value]) => value !== ''));
const apply = () => router.get(route('fees.reports.dues'), query(), { preserveState: true, replace: true });

// Sum in paisa: never add money as floats.
const total = computed(() => {
    const paisa = props.rows.reduce((sum, row) => sum + Math.round(Number(row.due) * 100), 0);
    return (paisa / 100).toFixed(2);
});

const withPhone = computed(() => props.rows.filter((row) => row.guardian_phone).length);

// A Bangla due SMS is usually two parts.
const confirming = ref(false);
const sending = ref(false);
const sendSms = () =>
    router.post(route('fees.reports.due-sms', query()), {}, {
        preserveScroll: true,
        onStart: () => (sending.value = true),
        onFinish: () => {
            sending.value = false;
            confirming.value = false;
        },
    });
</script>

<template>
    <Head :title="$t('fees.reports.dues.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('fees.reports.dues.title')" :description="$t('fees.reports.dues.description')">
                <Button v-if="can('sms.send') && withPhone > 0" variant="outline" @click="confirming = true">
                    <MessageSquare class="size-4" />{{ $t('fees.reports.dues.sendSms') }}
                </Button>
            </PageHeader>

            <div class="flex flex-col gap-2 sm:flex-row">
                <NativeSelect
                    v-model="filters.school_class_id"
                    :aria-label="$t('fees.fields.class')"
                    class="sm:w-48"
                    @update:model-value="
                        () => {
                            filters.section_id = '';
                            apply();
                        }
                    "
                >
                    <option value="">{{ $t('fees.reports.dues.allClasses') }}</option>
                    <option v-for="schoolClass in classes" :key="schoolClass.id" :value="String(schoolClass.id)">{{ localized(schoolClass) }}</option>
                </NativeSelect>
                <NativeSelect v-model="filters.section_id" :aria-label="$t('fees.fields.section')" class="sm:w-52" @update:model-value="apply">
                    <option value="">{{ $t('fees.reports.dues.allSections') }}</option>
                    <option v-for="section in visibleSections" :key="section.id" :value="String(section.id)">
                        {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                    </option>
                </NativeSelect>
            </div>

            <div class="flex flex-wrap items-end gap-6 rounded-xl border p-4">
                <div>
                    <div class="text-sm text-muted-foreground">{{ $t('fees.reports.dues.total') }}</div>
                    <div class="text-3xl font-bold tabular-nums text-red-700">৳ {{ formatMoney(total, locale) }}</div>
                </div>
                <div class="text-sm text-muted-foreground">{{ $t('fees.reports.dues.students', { count: formatNumber(rows.length, locale) }) }}</div>
            </div>

            <p v-if="rows.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.reports.dues.none') }}</p>

            <div v-else class="overflow-x-auto rounded-md border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-3 py-2">{{ $t('fees.fields.class') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.roll') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.student') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.fields.phone') }}</th>
                            <th class="px-3 py-2">{{ $t('fees.reports.dues.since') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('fees.reports.dues.invoices') }}</th>
                            <th class="px-3 py-2 text-right">{{ $t('fees.fields.due') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.enrollment_id" class="border-b last:border-0">
                            <td class="px-3 py-2 whitespace-nowrap">{{ localized({ name_en: row.class_name_en, name_bn: row.class_name_bn }) }} — {{ row.section }}</td>
                            <td class="px-3 py-2 tabular-nums">{{ formatNumber(row.roll, locale) }}</td>
                            <td class="px-3 py-2">
                                <Link :href="route('fees.collect', { enrollment_id: row.enrollment_id })" class="hover:underline">
                                    {{ localized({ name_en: row.name_en, name_bn: row.name_bn ?? row.name_en }) }}
                                </Link>
                            </td>
                            <td class="px-3 py-2 tabular-nums">
                                <a v-if="row.guardian_phone" :href="`tel:${row.guardian_phone}`" class="hover:underline">{{ formatNumber(row.guardian_phone, locale) }}</a>
                            </td>
                            <td class="px-3 py-2">{{ formatMonth(row.since, locale) }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ formatNumber(row.invoice_count, locale) }}</td>
                            <td class="px-3 py-2 text-right font-medium tabular-nums">{{ formatMoney(row.due, locale) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="confirming">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('fees.reports.dues.sendSmsTitle') }}</DialogTitle>
                    <DialogDescription>
                        {{ $t('fees.reports.dues.sendSmsBody', { count: formatNumber(withPhone, locale), parts: formatNumber(withPhone * 2, locale) }) }}
                    </DialogDescription>
                </DialogHeader>
                <p class="text-sm">{{ $t('fees.reports.dues.balance', { balance: formatNumber(smsBalance, locale) }) }}</p>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="confirming = false">{{ $t('common.cancel') }}</Button>
                    <Button :disabled="sending" @click="sendSms">{{ $t('fees.reports.dues.send') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
