<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useCrudForm } from '@/composables/useCrudForm';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { NamedRecord } from '@/types/setup';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

// One page for the setup lists that only have a name: shifts, versions, groups.
const props = defineProps<{
    resource: 'shifts' | 'versions' | 'groups';
    rows: Paginated<NamedRecord>;
    filters: Record<string, string>;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: `setup.${props.resource}.title`, href: `/setup/${props.resource}` },
];

const columns: DataTableColumn[] = [
    { key: 'name', label: 'setup.name' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    `/setup/${props.resource}`,
    () => ({ name: '' }),
    (row: NamedRecord) => ({ name: row.name }),
);
</script>

<template>
    <Head :title="$t(`setup.${resource}.title`)" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t(`setup.${resource}.title`)" :description="$t(`setup.${resource}.description`)">
                <Button @click="create"><Plus class="size-4" />{{ $t(`setup.${resource}.add`) }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row" />
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(`setup.${resource}.${editing ? 'edit' : 'add'}`) }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="name" :label="$t('setup.name')" :error="form.errors.name" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name" required autofocus />
                    </FormField>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="deleting?.name ?? null" @confirm="destroy" @cancel="deleting = null" />
    </AppLayout>
</template>
