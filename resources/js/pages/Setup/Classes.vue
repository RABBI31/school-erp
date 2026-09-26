<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useCrudForm } from '@/composables/useCrudForm';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { SchoolClass } from '@/types/setup';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

defineProps<{
    rows: Paginated<SchoolClass>;
    filters: Record<string, string>;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: 'setup.classes.title', href: '/setup/classes' },
];

const columns: DataTableColumn[] = [
    { key: 'numeric_order', label: 'setup.classes.numericOrder', class: 'w-32' },
    { key: 'name', label: 'setup.name' },
    { key: 'has_groups', label: 'setup.groups.title' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/setup/classes',
    () => ({ name_en: '', name_bn: '', numeric_order: '' as number | string, has_groups: false }),
    (row: SchoolClass) => ({ name_en: row.name_en, name_bn: row.name_bn, numeric_order: row.numeric_order, has_groups: row.has_groups }),
);
</script>

<template>
    <Head :title="$t('setup.classes.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.classes.title')" :description="$t('setup.classes.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('setup.classes.add') }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #cell-numeric_order="{ row }">{{ formatNumber(row.numeric_order, locale) }}</template>
                <template #cell-name="{ row }">{{ localized(row) }}</template>
                <template #cell-has_groups="{ row }">{{ $t(row.has_groups ? 'common.yes' : 'common.no') }}</template>
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row" />
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'setup.classes.edit' : 'setup.classes.add') }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="name_en" :label="$t('setup.classes.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                            <Input :id="id" v-model="form.name_en" required autofocus placeholder="Class Six" />
                        </FormField>
                        <FormField id="name_bn" :label="$t('setup.classes.nameBn')" :error="form.errors.name_bn" required v-slot="{ id }">
                            <Input :id="id" v-model="form.name_bn" required placeholder="ষষ্ঠ শ্রেণি" />
                        </FormField>
                    </div>
                    <FormField id="numeric_order" :label="$t('setup.classes.numericOrder')" :error="form.errors.numeric_order" required v-slot="{ id }">
                        <Input :id="id" v-model="form.numeric_order" type="number" min="1" max="20" required class="sm:w-32" />
                    </FormField>
                    <div class="grid gap-2">
                        <Label for="has_groups" class="flex items-center gap-3">
                            <Checkbox id="has_groups" v-model:checked="form.has_groups" />
                            <span>{{ $t('setup.classes.hasGroups') }}</span>
                        </Label>
                        <InputError :message="form.errors.has_groups" />
                    </div>
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
