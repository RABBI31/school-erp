<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { useCrudForm } from '@/composables/useCrudForm';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { ClassOption, NamedRecord, Subject } from '@/types/setup';
import { Head } from '@inertiajs/vue3';
import { Plus } from 'lucide-vue-next';
import { computed, watch } from 'vue';

const props = defineProps<{
    rows: Paginated<Subject>;
    filters: Record<string, string>;
    classes: ClassOption[];
    groups: NamedRecord[];
    combinedKeys: Record<string, string[]>;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: 'setup.subjects.title', href: '/setup/subjects' },
];

const columns: DataTableColumn[] = [
    { key: 'class', label: 'setup.subjects.class' },
    { key: 'display_order', label: 'setup.subjects.displayOrder', class: 'w-20' },
    { key: 'code', label: 'setup.subjects.code', class: 'w-24' },
    { key: 'name', label: 'setup.name' },
    { key: 'group', label: 'setup.subjects.group' },
    { key: 'combined_key', label: 'setup.subjects.combinedKey' },
    { key: 'is_optional_allowed', label: 'setup.subjects.optionalShort' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/setup/subjects',
    () => ({
        school_class_id: '' as number | string,
        group_id: null as number | null,
        name_en: '',
        name_bn: '',
        code: '',
        combined_key: '',
        is_optional_allowed: false,
        display_order: '' as number | string,
    }),
    (row: Subject) => ({
        school_class_id: row.school_class_id,
        group_id: row.group_id,
        name_en: row.name_en,
        name_bn: row.name_bn,
        code: row.code,
        combined_key: row.combined_key ?? '',
        is_optional_allowed: row.is_optional_allowed,
        display_order: row.display_order,
    }),
);

const selectedClass = computed(() => props.classes.find((option) => option.id === Number(form.school_class_id)));

// A group only applies to classes that have groups (9–10); clear it otherwise.
watch(selectedClass, (option) => {
    if (!option?.has_groups) {
        form.group_id = null;
    }
});

const keySuggestions = computed(() => props.combinedKeys[String(form.school_class_id)] ?? []);
</script>

<template>
    <Head :title="$t('setup.subjects.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.subjects.title')" :description="$t('setup.subjects.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('setup.subjects.add') }}</Button>
            </PageHeader>

            <DataTable :rows="rows" :columns="columns" :filters="filters">
                <template #filters="{ filters: state }">
                    <NativeSelect v-model="state.school_class_id" class="sm:w-44" :aria-label="$t('setup.subjects.class')">
                        <option value="">{{ $t('setup.subjects.class') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in classes" :key="option.id" :value="String(option.id)">{{ localized(option) }}</option>
                    </NativeSelect>
                    <NativeSelect v-if="groups.length > 0" v-model="state.group_id" class="sm:w-44" :aria-label="$t('setup.subjects.group')">
                        <option value="">{{ $t('setup.subjects.group') }}: {{ $t('common.all') }}</option>
                        <option v-for="option in groups" :key="option.id" :value="String(option.id)">{{ option.name }}</option>
                    </NativeSelect>
                </template>
                <template #cell-class="{ row }">{{ localized(row.school_class) }}</template>
                <template #cell-display_order="{ row }">{{ formatNumber(row.display_order, locale) }}</template>
                <template #cell-name="{ row }">{{ localized(row) }}</template>
                <template #cell-group="{ row }">{{ row.group?.name ?? $t('setup.subjects.commonGroup') }}</template>
                <template #cell-is_optional_allowed="{ row }">{{ row.is_optional_allowed ? $t('common.yes') : '' }}</template>
                <template #cell-actions="{ row }">
                    <RowActions @edit="edit(row)" @delete="deleting = row" />
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90svh] overflow-y-auto">
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'setup.subjects.edit' : 'setup.subjects.add') }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="school_class_id" :label="$t('setup.subjects.class')" :error="form.errors.school_class_id" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.school_class_id" required>
                                <option value="" disabled>—</option>
                                <option v-for="option in classes" :key="option.id" :value="option.id">{{ localized(option) }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField v-if="selectedClass?.has_groups" id="group_id" :label="$t('setup.subjects.group')" :error="form.errors.group_id" v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.group_id">
                                <option :value="null">{{ $t('setup.subjects.commonGroup') }}</option>
                                <option v-for="option in groups" :key="option.id" :value="option.id">{{ option.name }}</option>
                            </NativeSelect>
                        </FormField>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="name_en" :label="$t('setup.subjects.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                            <Input :id="id" v-model="form.name_en" required placeholder="Mathematics" />
                        </FormField>
                        <FormField id="name_bn" :label="$t('setup.subjects.nameBn')" :error="form.errors.name_bn" required v-slot="{ id }">
                            <Input :id="id" v-model="form.name_bn" required placeholder="গণিত" />
                        </FormField>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <FormField id="code" :label="$t('setup.subjects.code')" :error="form.errors.code" required v-slot="{ id }">
                            <Input :id="id" v-model="form.code" required placeholder="109" />
                        </FormField>
                        <FormField
                            id="display_order"
                            :label="$t('setup.subjects.displayOrder')"
                            :error="form.errors.display_order"
                            :hint="editing ? undefined : $t('setup.subjects.displayOrderHint')"
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="form.display_order" type="number" min="0" max="999" />
                        </FormField>
                        <FormField id="combined_key" :label="$t('setup.subjects.combinedKey')" :error="form.errors.combined_key" v-slot="{ id }">
                            <Input :id="id" v-model="form.combined_key" list="combined-keys" placeholder="bangla" />
                            <datalist id="combined-keys">
                                <option v-for="key in keySuggestions" :key="key" :value="key" />
                            </datalist>
                        </FormField>
                    </div>
                    <p class="-mt-3 text-sm text-muted-foreground">{{ $t('setup.subjects.combinedKeyHint') }}</p>
                    <Label for="is_optional_allowed" class="flex items-center gap-3">
                        <Checkbox id="is_optional_allowed" v-model:checked="form.is_optional_allowed" />
                        <span>{{ $t('setup.subjects.optional') }}</span>
                    </Label>
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
