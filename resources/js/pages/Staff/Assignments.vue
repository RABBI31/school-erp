<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { NamedRecord } from '@/types/setup';
import type { StaffOption } from '@/types/staff';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

const props = defineProps<{
    year: { id: number; name: string } | null;
    sections: { id: number; name: string; class_name_en: string; class_name_bn: string }[];
    sectionId: number | null;
    subjects: { id: number; name_en: string; name_bn: string; code: string; group: NamedRecord | null }[];
    staff: StaffOption[];
    assignment: { class_teacher_id: number | null; subjects: Record<string, number> } | null;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'staff.title', href: '/staff' },
    { title: 'staff.assignments', href: '/staff-assignments' },
];

const pickSection = (id: string) => router.get(route('staff.assignments.edit'), id ? { section_id: id } : {}, { preserveScroll: true });

const buildForm = () => ({
    class_teacher_id: props.assignment?.class_teacher_id ?? null,
    subjects: Object.fromEntries(props.subjects.map((subject) => [subject.id, props.assignment?.subjects[subject.id] ?? null])) as Record<
        string,
        number | null
    >,
});

const form = useForm(buildForm());

// A different section arrives as new props; start its form from the saved assignments.
watch(
    () => [props.sectionId, props.assignment],
    () => {
        form.defaults(buildForm());
        form.reset();
        form.clearErrors();
    },
);

const submit = () => form.put(route('staff.assignments.update', { section: props.sectionId! }), { preserveScroll: true });

const error = (key: string) => (form.errors as Record<string, string | undefined>)[key];
const staffName = (member: StaffOption) =>
    `${localized({ name_en: member.name_en, name_bn: member.name_bn ?? member.name_en })}${member.designation ? ` — ${member.designation}` : ''}`;
</script>

<template>
    <Head :title="$t('staff.assignments')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6 p-4 md:p-6">
            <PageHeader
                :title="$t('staff.assignments')"
                :description="year ? $t('staff.assign.description', { year: formatNumber(year.name, locale) }) : $t('staff.assign.noYear')"
            />

            <template v-if="year">
                <div class="max-w-sm">
                    <NativeSelect
                        :model-value="sectionId ?? ''"
                        :aria-label="$t('staff.assign.section')"
                        @update:model-value="(id) => pickSection(String(id ?? ''))"
                    >
                        <option value="">{{ $t('staff.assign.section') }}…</option>
                        <option v-for="section in sections" :key="section.id" :value="section.id">
                            {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                        </option>
                    </NativeSelect>
                </div>

                <p v-if="!sectionId" class="text-sm text-muted-foreground">{{ $t('staff.assign.pickSection') }}</p>

                <form v-else class="space-y-6" @submit.prevent="submit">
                    <div class="max-w-sm space-y-2">
                        <label for="class_teacher_id" class="text-sm font-medium">{{ $t('staff.assign.classTeacher') }}</label>
                        <NativeSelect id="class_teacher_id" v-model="form.class_teacher_id">
                            <option :value="null">{{ $t('staff.assign.unassigned') }}</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ staffName(member) }}</option>
                        </NativeSelect>
                        <InputError :message="form.errors.class_teacher_id" />
                    </div>

                    <InputError :message="error('subjects')" />
                    <p v-if="subjects.length === 0" class="text-sm text-muted-foreground">{{ $t('staff.assign.noSubjects') }}</p>
                    <div v-else class="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>{{ $t('staff.assign.subject') }}</TableHead>
                                    <TableHead class="w-1/2">{{ $t('staff.assign.teacher') }}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="subject in subjects" :key="subject.id">
                                    <TableCell>
                                        <div class="font-medium">{{ localized(subject) }}</div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ subject.code }}<template v-if="subject.group"> · {{ subject.group.name }}</template>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <NativeSelect v-model="form.subjects[subject.id]" :aria-label="localized(subject)">
                                            <option :value="null">{{ $t('staff.assign.unassigned') }}</option>
                                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ staffName(member) }}</option>
                                        </NativeSelect>
                                        <InputError :message="error(`subjects.${subject.id}`)" />
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>

                    <Button type="submit" :disabled="form.processing">{{ $t('staff.assign.save') }}</Button>
                </form>
            </template>
        </div>
    </AppLayout>
</template>
