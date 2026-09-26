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
import { formatDate, formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { AcademicYear } from '@/types/setup';
import { Head, router } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    rows: Paginated<AcademicYear>;
    filters: Record<string, string>;
}>();

const { locale } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: 'setup.years.title', href: '/setup/academic-years' },
];

const columns: DataTableColumn[] = [
    { key: 'name', label: 'setup.years.name' },
    { key: 'starts_on', label: 'setup.years.startsOn' },
    { key: 'ends_on', label: 'setup.years.endsOn' },
    { key: 'is_current', label: 'setup.years.current' },
    { key: 'actions', label: 'common.actions', class: 'w-48 text-right' },
];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/setup/academic-years',
    () => ({ name: '', starts_on: '', ends_on: '' }),
    (row: AcademicYear) => ({ name: row.name, starts_on: row.starts_on, ends_on: row.ends_on }),
);

const makeCurrent = (row: AcademicYear) => router.post(`/setup/academic-years/${row.id}/current`, {}, { preserveScroll: true });
</script>

<template>
    <Head :title="$t('setup.years.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.years.title')" :description="$t('setup.years.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('setup.years.add') }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #cell-name="{ row }">{{ formatNumber(row.name, locale) }}</template>
                <template #cell-starts_on="{ row }">{{ formatDate(row.starts_on, locale) }}</template>
                <template #cell-ends_on="{ row }">{{ formatDate(row.ends_on, locale) }}</template>
                <template #cell-is_current="{ row }">
                    <span v-if="row.is_current" class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800">
                        {{ $t('setup.years.current') }}
                    </span>
                </template>
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row">
                        <Button v-if="!row.is_current" variant="outline" size="sm" @click="makeCurrent(row)">{{ $t('setup.years.makeCurrent') }}</Button>
                    </RowActions>
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'setup.years.edit' : 'setup.years.add') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="name" :label="$t('setup.years.name')" :error="form.errors.name" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name" required autofocus placeholder="2026" />
                    </FormField>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="starts_on" :label="$t('setup.years.startsOn')" :error="form.errors.starts_on" required v-slot="{ id }">
                            <Input :id="id" v-model="form.starts_on" type="date" required />
                        </FormField>
                        <FormField id="ends_on" :label="$t('setup.years.endsOn')" :error="form.errors.ends_on" required v-slot="{ id }">
                            <Input :id="id" v-model="form.ends_on" type="date" required />
                        </FormField>
                    </div>
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
