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
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { ClassOption, NamedRecord, Section } from '@/types/setup';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';

const props = defineProps<{
    rows: Paginated<Section>;
    filters: Record<string, string>;
    classes: ClassOption[];
    shifts: NamedRecord[];
    versions: NamedRecord[];
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: 'setup.sections.title', href: '/setup/sections' },
];

const columns: DataTableColumn[] = [
    { key: 'class', label: 'setup.sections.class' },
    { key: 'name', label: 'setup.sections.name' },
    { key: 'shift', label: 'setup.sections.shift' },
    { key: 'version', label: 'setup.sections.version' },
    { key: 'capacity', label: 'setup.sections.capacity' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

// With a single shift or version (common), preselect it.
const only = (options: NamedRecord[]) => (options.length === 1 ? options[0].id : '');

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/setup/sections',
    () => ({
        school_class_id: '' as number | string,
        shift_id: only(props.shifts) as number | string,
        version_id: only(props.versions) as number | string,
        name: '',
        capacity: '' as number | string,
    }),
    (row: Section) => ({
        school_class_id: row.school_class_id,
        shift_id: row.shift_id,
        version_id: row.version_id,
        name: row.name,
        capacity: row.capacity ?? '',
    }),
);
</script>

<template>
    <Head :title="$t('setup.sections.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.sections.title')" :description="$t('setup.sections.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('setup.sections.add') }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <NativeSelect v-model="state.school_class_id" class="sm:w-44" :aria-label="$t('setup.sections.class')">
                        <option value="">{{ $t('setup.sections.class') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in classes" :key="option.id" :value="String(option.id)">{{ localized(option) }}</option>
                    </NativeSelect>
                    <NativeSelect v-if="shifts.length > 1" v-model="state.shift_id" class="sm:w-40" :aria-label="$t('setup.sections.shift')">
                        <option value="">{{ $t('setup.sections.shift') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in shifts" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
                    </NativeSelect>
                    <NativeSelect v-if="versions.length > 1" v-model="state.version_id" class="sm:w-40" :aria-label="$t('setup.sections.version')">
                        <option value="">{{ $t('setup.sections.version') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in versions" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
                    </NativeSelect>
                </template>
                <template #cell-class="{ row }">{{ localized(row.school_class) }}</template>
                <template #cell-shift="{ row }">{{ row.shift.name }}</template>
                <template #cell-version="{ row }">{{ row.version.name }}</template>
                <template #cell-capacity="{ row }">{{ formatNumber(row.capacity, locale) }}</template>
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row" />
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'setup.sections.edit' : 'setup.sections.add') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="school_class_id" :label="$t('setup.sections.class')" :error="form.errors.school_class_id" required v-slot="{ id }">
                        <NativeSelect :id="id" v-model="form.school_class_id" required>
                            <option value="" disabled>—</option>
                            <option v-for="option in classes" :key="option.id" :value="option.id">{{ localized(option) }}</option>
                        </NativeSelect>
                    </FormField>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="shift_id" :label="$t('setup.sections.shift')" :error="form.errors.shift_id" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.shift_id" required>
                                <option value="" disabled>—</option>
                                <option v-for="option in shifts" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField id="version_id" :label="$t('setup.sections.version')" :error="form.errors.version_id" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.version_id" required>
                                <option value="" disabled>—</option>
                                <option v-for="option in versions" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="name" :label="$t('setup.sections.name')" :error="form.errors.name" required v-slot="{ id }">
                            <Input :id="id" v-model="form.name" required placeholder="A" />
                        </FormField>
                        <FormField id="capacity" :label="$t('setup.sections.capacity')" :error="form.errors.capacity" v-slot="{ id }">
                            <Input :id="id" v-model="form.capacity" type="number" min="1" max="500" />
                        </FormField>
                    </div>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete
            :name="deleting ? `${localized(deleting.school_class)} — ${deleting.name}` : null"
            @confirm="destroy"
            @cancel="deleting = null"
        />
    </AppLayout>
</template>
