<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { ClassOption, FeeHead, Money } from '@/types/fees';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    classes: ClassOption[];
    heads: FeeHead[];
    /** class id -> fee head id -> amount; an empty array when nothing is set yet. */
    amounts: Record<string, Record<string, Money>> | [];
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.structure.title', href: '/fees/structure' },
];

// Show "1200" rather than "1200.00" in the inputs.
const plain = (amount: Money | undefined) => (amount === undefined ? '' : amount.replace(/\.00$/, ''));

const saved = props.amounts as Record<string, Record<string, Money>>;
const form = useForm({
    amounts: Object.fromEntries(
        props.classes.map((schoolClass) => [
            schoolClass.id,
            Object.fromEntries(props.heads.map((head) => [head.id, plain(saved[schoolClass.id]?.[head.id])])),
        ]),
    ) as Record<string, Record<string, string>>,
});

const errorFor = (classId: number, headId: number) => (form.errors as Record<string, string>)[`amounts.${classId}.${headId}`];
const hasErrors = computed(() => Object.keys(form.errors).length > 0);

const save = () => form.put(route('fees.structure.update'), { preserveScroll: true });
</script>

<template>
    <Head :title="$t('fees.structure.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader
                :title="$t('fees.structure.title')"
                :description="$t('fees.structure.description', { year: formatNumber($page.props.academicYear?.name ?? '', locale) })"
            />

            <p v-if="!$page.props.academicYear" class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">{{ $t('fees.noYear') }}</p>
            <p v-else-if="heads.length === 0" class="text-sm text-muted-foreground">
                {{ $t('fees.structure.noHeads') }}
                <Link href="/fees/heads" class="underline">{{ $t('fees.heads.add') }}</Link>
            </p>
            <p v-else-if="classes.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.structure.noClasses') }}</p>

            <form v-else class="space-y-4" @submit.prevent="save">
                <div class="overflow-x-auto rounded-md border">
                    <table class="w-full border-collapse text-sm">
                        <thead>
                            <tr class="border-b bg-muted/50">
                                <th class="sticky left-0 z-10 bg-muted px-3 py-2 text-left">{{ $t('fees.fields.class') }}</th>
                                <th v-for="head in heads" :key="head.id" class="min-w-32 px-3 py-2 text-left font-medium">
                                    {{ localized(head) }}
                                    <div class="text-xs font-normal text-muted-foreground">{{ $t(`enums.feeHeadType.${head.type}`) }}</div>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="schoolClass in classes" :key="schoolClass.id" class="border-b last:border-0">
                                <th class="sticky left-0 z-10 bg-background px-3 py-2 text-left font-medium">{{ localized(schoolClass) }}</th>
                                <td v-for="head in heads" :key="head.id" class="px-3 py-2">
                                    <Input
                                        v-model="form.amounts[schoolClass.id][head.id]"
                                        type="number"
                                        inputmode="decimal"
                                        min="0"
                                        step="0.01"
                                        class="h-9 w-32 text-right tabular-nums"
                                        :aria-label="`${localized(schoolClass)} — ${localized(head)}`"
                                        :aria-invalid="!!errorFor(schoolClass.id, head.id)"
                                    />
                                    <p v-if="errorFor(schoolClass.id, head.id)" class="mt-1 text-xs text-red-600">{{ errorFor(schoolClass.id, head.id) }}</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="flex items-center gap-3">
                    <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    <span v-if="hasErrors" class="text-sm text-red-600">{{ Object.values(form.errors)[0] }}</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
