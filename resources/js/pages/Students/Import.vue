<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { NamedRecord } from '@/types/setup';
import { Head, useForm } from '@inertiajs/vue3';
import { Download } from 'lucide-vue-next';

const props = defineProps<{
    academicYearOptions: NamedRecord[];
    defaultYearId: number | null;
    sections: { id: number; name: string; class_name_en: string; class_name_bn: string }[];
    columns: string[];
    report: { imported: number; failed: { row: number; name: string; errors: string[] }[] } | null;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'students.title', href: '/students' },
    { title: 'students.importPage.title', href: '/students/import' },
];

const form = useForm({
    academic_year_id: (props.defaultYearId ?? '') as number | string,
    section_id: '' as number | string,
    file: null as File | null,
});

const pick = (event: Event) => (form.file = (event.target as HTMLInputElement).files?.[0] ?? null);

const submit = () => form.post(route('students.import.store'), { onSuccess: () => form.reset('file') });
</script>

<template>
    <Head :title="$t('students.importPage.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('students.importPage.title')" :description="$t('students.importPage.description')">
                <Button variant="outline" as-child>
                    <a :href="route('students.import.template')"><Download class="size-4" />{{ $t('students.importPage.template') }}</a>
                </Button>
            </PageHeader>

            <!-- Result of the last import -->
            <div v-if="report" class="space-y-4">
                <p class="rounded-md border border-green-200 bg-green-50 p-4 text-sm text-green-900">
                    {{ $t('students.importPage.imported', { count: formatNumber(report.imported, locale) }) }}
                </p>
                <div v-if="report.failed.length" class="space-y-2">
                    <h2 class="font-semibold text-red-800">
                        {{ $t('students.importPage.failedTitle', { count: formatNumber(report.failed.length, locale) }) }}
                    </h2>
                    <div class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-16">{{ $t('students.importPage.row') }}</TableHead>
                                    <TableHead>{{ $t('students.student') }}</TableHead>
                                    <TableHead>{{ $t('students.importPage.errors') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="failure in report.failed" :key="failure.row">
                                    <TableCell>{{ formatNumber(failure.row, locale) }}</TableCell>
                                    <TableCell>{{ failure.name }}</TableCell>
                                    <TableCell>
                                        <ul class="list-disc pl-4 text-sm text-red-800">
                                            <li v-for="message in failure.errors" :key="message">{{ message }}</li>
                                        </ul>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>
            </div>

            <form class="space-y-4 rounded-xl border p-4 md:p-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        id="academic_year_id"
                        :label="$t('students.fields.academicYear')"
                        :error="form.errors.academic_year_id"
                        required
                        v-slot="{ id }"
                    >
                        <NativeSelect :id="id" v-model="form.academic_year_id" required>
                            <option value="" disabled>—</option>
                            <option v-for="year in academicYearOptions" :key="year.id" :value="year.id">{{ year.name }}</option>
                        </NativeSelect>
                    </FormField>
                    <FormField id="section_id" :label="$t('students.section')" :error="form.errors.section_id" required v-slot="{ id }">
                        <NativeSelect :id="id" v-model="form.section_id" required>
                            <option value="" disabled>—</option>
                            <option v-for="section in sections" :key="section.id" :value="section.id">
                                {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                            </option>
                        </NativeSelect>
                    </FormField>
                </div>
                <FormField id="file" :label="$t('students.importPage.file')" :error="form.errors.file" required v-slot="{ id }">
                    <Input :id="id" type="file" accept=".xlsx,.csv" required @change="pick" />
                </FormField>
                <div class="text-sm text-muted-foreground">
                    <p>{{ $t('students.importPage.columns') }}</p>
                    <p class="mt-1 break-words font-mono text-xs">{{ columns.join(', ') }}</p>
                    <p class="mt-2">{{ $t('students.importPage.columnsHint') }}</p>
                </div>
                <Button type="submit" :disabled="form.processing">{{ $t('students.importPage.submit') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
