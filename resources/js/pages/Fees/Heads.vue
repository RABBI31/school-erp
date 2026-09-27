<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useCrudForm } from '@/composables/useCrudForm';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { FeeHead, FeeHeadType } from '@/types/fees';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

defineProps<{
    rows: Paginated<FeeHead>;
    filters: Record<string, string>;
}>();

const { localized } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'fees.title', href: '/fees' },
    { title: 'fees.heads.title', href: '/fees/heads' },
];

const columns: DataTableColumn[] = [
    { key: 'name', label: 'setup.name' },
    { key: 'type', label: 'fees.heads.type' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

const types: FeeHeadType[] = ['monthly', 'yearly', 'one_time'];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/fees/heads',
    () => ({ name_en: '', name_bn: '', type: 'monthly' as FeeHeadType }),
    (row: FeeHead) => ({ name_en: row.name_en, name_bn: row.name_bn, type: row.type }),
);
</script>

<template>
    <Head :title="$t('fees.heads.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('fees.heads.title')" :description="$t('fees.heads.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('fees.heads.add') }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #cell-name="{ row }">{{ localized(row) }}</template>
                <template #cell-type="{ row }">{{ $t(`enums.feeHeadType.${row.type}`) }}</template>
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row" />
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'fees.heads.edit' : 'fees.heads.add') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="name_en" :label="$t('fees.heads.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_en" required autofocus />
                    </FormField>
                    <FormField id="name_bn" :label="$t('fees.heads.nameBn')" :error="form.errors.name_bn" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_bn" required />
                    </FormField>
                    <FormField id="type" :label="$t('fees.heads.type')" :error="form.errors.type" required v-slot="{ id }">
                        <NativeSelect :id="id" v-model="form.type">
                            <option v-for="type in types" :key="type" :value="type">{{ $t(`enums.feeHeadType.${type}`) }}</option>
                        </NativeSelect>
                    </FormField>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="deleting ? localized(deleting) : null" @confirm="destroy" @cancel="deleting = null" />
    </AppLayout>
</template>
