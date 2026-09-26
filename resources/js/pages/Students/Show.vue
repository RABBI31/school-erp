<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StatusBadge from '@/components/students/StatusBadge.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { useGuardianSearch } from '@/composables/useGuardianSearch';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import type { Guardian, GuardianMatch, GuardianRelation, StudentProfile } from '@/types/students';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Phone, Plus, Star, Trash2, UserRound } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    student: StudentProfile;
    hasPhoto: boolean;
    relations: GuardianRelation[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const name = computed(() => localized({ name_en: props.student.name_en, name_bn: props.student.name_bn ?? props.student.name_en }));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'students.title', href: '/students' },
    { title: 'students.profile.info', href: `/students/${props.student.id}` },
];

const details = computed(
    () =>
        [
            ['students.uid', props.student.student_uid],
            ['students.gender', props.student.gender ? `enums.gender.${props.student.gender}` : null, true],
            ['students.fields.dateOfBirth', formatDate(props.student.date_of_birth, locale.value)],
            ['students.fields.religion', props.student.religion ? `enums.religion.${props.student.religion}` : null, true],
            ['students.fields.bloodGroup', props.student.blood_group],
            ['students.fields.fatherName', props.student.father_name],
            ['students.fields.motherName', props.student.mother_name],
            ['students.fields.presentAddress', props.student.present_address],
            ['students.fields.permanentAddress', props.student.permanent_address],
            ['students.fields.birthRegNo', props.student.birth_reg_no],
            ['students.fields.admissionDate', formatDate(props.student.admission_date, locale.value)],
        ] as [string, string | null, boolean?][],
);

const base = `/students/${props.student.id}/guardians`;

// Add (link existing or create) and edit guardian dialogs.
const dialog = ref<'add' | 'edit' | null>(null);
const editingId = ref<number | null>(null);
const guardianForm = useForm({
    id: null as number | null,
    name_en: '',
    name_bn: '',
    relation: 'mother' as GuardianRelation,
    phone: '',
    alt_phone: '',
    occupation: '',
    nid: '',
    is_primary: false,
});
const { matches, search } = useGuardianSearch();

const openAdd = () => {
    guardianForm.reset();
    guardianForm.clearErrors();
    matches.value = [];
    dialog.value = 'add';
};

const openEdit = (guardian: Guardian) => {
    guardianForm.clearErrors();
    Object.assign(guardianForm, {
        id: null,
        name_en: guardian.name_en,
        name_bn: guardian.name_bn ?? '',
        relation: guardian.relation,
        phone: guardian.phone,
        alt_phone: guardian.alt_phone ?? '',
        occupation: guardian.occupation ?? '',
        nid: guardian.nid ?? '',
        is_primary: false,
    });
    editingId.value = guardian.id;
    dialog.value = 'edit';
};

const linkExisting = (match: GuardianMatch) => {
    router.post(base, { id: match.id }, { preserveScroll: true, onSuccess: () => (dialog.value = null) });
};

const saveGuardian = () => {
    const options = { preserveScroll: true, onSuccess: () => (dialog.value = null) };
    if (dialog.value === 'edit') {
        // Editing changes details only; linking and primary have their own actions.
        guardianForm
            .transform((data) => ({
                name_en: data.name_en,
                name_bn: data.name_bn,
                relation: data.relation,
                phone: data.phone,
                alt_phone: data.alt_phone,
                occupation: data.occupation,
                nid: data.nid,
            }))
            .put(`${base}/${editingId.value}`, options);
    } else {
        guardianForm.transform((data) => data).post(base, options);
    }
};

const makePrimary = (guardian: Guardian) => router.post(`${base}/${guardian.id}/primary`, {}, { preserveScroll: true });

const removing = ref<Guardian | null>(null);
const removeGuardian = () => {
    if (removing.value) {
        router.delete(`${base}/${removing.value.id}`, { preserveScroll: true, onFinish: () => (removing.value = null) });
    }
};

const deletingStudent = ref(false);
const deleteStudent = () => router.delete(`/students/${props.student.id}`);

const siblings = (guardian: Guardian) => guardian.students.filter((child) => child.id !== props.student.id);
</script>

<template>
    <Head :title="name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-5xl space-y-6 p-4 md:p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
                <img v-if="hasPhoto" :src="`/students/${student.id}/photo`" alt="" class="h-28 w-24 rounded-lg border object-cover" />
                <div v-else class="flex h-28 w-24 items-center justify-center rounded-lg border bg-muted">
                    <UserRound class="size-10 text-muted-foreground" />
                </div>
                <div class="flex-1">
                    <PageHeader :title="name" :description="student.name_bn && locale === 'en' ? student.name_bn : student.name_en">
                        <template v-if="can('students.manage')">
                            <Button variant="outline" as-child>
                                <Link :href="`/students/${student.id}/edit`"><Pencil class="size-4" />{{ $t('common.edit') }}</Link>
                            </Button>
                            <Button variant="outline" @click="deletingStudent = true"><Trash2 class="size-4" />{{ $t('common.delete') }}</Button>
                        </template>
                    </PageHeader>
                    <div class="mt-2"><StatusBadge :status="student.status" /></div>
                </div>
            </div>

            <!-- Details -->
            <section class="rounded-xl border p-4 md:p-6">
                <h2 class="mb-4 font-semibold">{{ $t('students.profile.info') }}</h2>
                <dl class="grid gap-x-6 gap-y-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="[label, value, translate] in details" :key="label">
                        <dt class="text-xs text-muted-foreground">{{ $t(label) }}</dt>
                        <dd>{{ value ? (translate ? $t(value) : value) : '—' }}</dd>
                    </div>
                </dl>
            </section>

            <!-- Guardians -->
            <section class="rounded-xl border p-4 md:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-semibold">{{ $t('students.profile.guardians') }}</h2>
                    <Button v-if="can('students.manage') && student.guardians.length < 3" size="sm" variant="outline" @click="openAdd">
                        <Plus class="size-4" />{{ $t('students.guardian.add') }}
                    </Button>
                </div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div v-for="guardian in student.guardians" :key="guardian.id" class="space-y-2 rounded-lg border p-4">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="font-medium">
                                    {{ localized({ name_en: guardian.name_en, name_bn: guardian.name_bn ?? guardian.name_en }) }}
                                </div>
                                <div class="text-sm text-muted-foreground">{{ $t(`enums.relation.${guardian.relation}`) }}</div>
                            </div>
                            <span v-if="guardian.pivot?.is_primary" class="rounded-full bg-primary px-2 py-0.5 text-xs text-primary-foreground">
                                {{ $t('students.guardian.primaryShort') }}
                            </span>
                        </div>
                        <a :href="`tel:${guardian.phone}`" class="flex items-center gap-2 text-sm hover:underline">
                            <Phone class="size-4" />{{ formatNumber(guardian.phone, locale) }}
                        </a>
                        <div v-if="siblings(guardian).length" class="text-sm">
                            <span class="text-muted-foreground">{{ $t('students.profile.siblings') }}</span>
                            <Link v-for="child in siblings(guardian)" :key="child.id" :href="`/students/${child.id}`" class="ml-1 hover:underline">
                                {{ localized({ name_en: child.name_en, name_bn: child.name_bn ?? child.name_en }) }}
                            </Link>
                        </div>
                        <div v-if="can('students.manage')" class="flex flex-wrap gap-1 pt-1">
                            <Button v-if="!guardian.pivot?.is_primary" size="sm" variant="ghost" @click="makePrimary(guardian)">
                                <Star class="size-4" />{{ $t('students.guardian.makePrimary') }}
                            </Button>
                            <Button size="sm" variant="ghost" @click="openEdit(guardian)"><Pencil class="size-4" />{{ $t('common.edit') }}</Button>
                            <Button v-if="student.guardians.length > 1" size="sm" variant="ghost" @click="removing = guardian">
                                <Trash2 class="size-4" />{{ $t('students.guardian.remove') }}
                            </Button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Enrollment history -->
            <section class="rounded-xl border p-4 md:p-6">
                <h2 class="mb-4 font-semibold">{{ $t('students.profile.history') }}</h2>
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>{{ $t('students.profile.year') }}</TableHead>
                            <TableHead>{{ $t('students.classSection') }}</TableHead>
                            <TableHead>{{ $t('students.group') }}</TableHead>
                            <TableHead>{{ $t('students.roll') }}</TableHead>
                            <TableHead>{{ $t('students.profile.optional') }}</TableHead>
                            <TableHead>{{ $t('students.status') }}</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="enrollment in student.enrollments" :key="enrollment.id">
                            <TableCell>{{ formatNumber(enrollment.academic_year.name, locale) }}</TableCell>
                            <TableCell>{{ localized(enrollment.section.school_class) }} — {{ enrollment.section.name }}</TableCell>
                            <TableCell>{{ enrollment.group?.name ?? '' }}</TableCell>
                            <TableCell>{{ formatNumber(enrollment.roll, locale) }}</TableCell>
                            <TableCell>{{ enrollment.optional_subject ? localized(enrollment.optional_subject) : '' }}</TableCell>
                            <TableCell>{{ $t(`enums.enrollmentStatus.${enrollment.status}`) }}</TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </section>
        </div>

        <Dialog :open="dialog !== null" @update:open="(open) => !open && (dialog = null)">
            <DialogContent class="max-h-[90svh] overflow-y-auto">
                <form class="space-y-4" @submit.prevent="saveGuardian">
                    <DialogHeader>
                        <DialogTitle>{{ $t(dialog === 'edit' ? 'students.guardian.edit' : 'students.guardian.add') }}</DialogTitle>
                    </DialogHeader>
                    <FormField id="g_phone" :label="$t('students.guardian.phone')" :error="guardianForm.errors.phone" required v-slot="{ id }">
                        <Input :id="id" v-model="guardianForm.phone" type="tel" required @input="dialog === 'add' && search(guardianForm.phone)" />
                    </FormField>
                    <div v-if="dialog === 'add' && matches.length" class="space-y-2 rounded-md border p-3 text-sm">
                        <div class="font-medium">{{ $t('students.guardian.found') }}</div>
                        <div v-for="match in matches" :key="match.id" class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div>{{ match.name_en }} · {{ $t(`enums.relation.${match.relation}`) }}</div>
                                <div class="text-muted-foreground">
                                    {{ $t('students.guardian.childrenOf', { names: match.students.map((child) => child.name_en).join(', ') }) }}
                                </div>
                            </div>
                            <Button type="button" size="sm" variant="outline" @click="linkExisting(match)">{{ $t('students.guardian.use') }}</Button>
                        </div>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="g_name" :label="$t('students.guardian.name')" :error="guardianForm.errors.name_en" required v-slot="{ id }">
                            <Input :id="id" v-model="guardianForm.name_en" required />
                        </FormField>
                        <FormField id="g_name_bn" :label="$t('students.guardian.nameBn')" :error="guardianForm.errors.name_bn" v-slot="{ id }">
                            <Input :id="id" v-model="guardianForm.name_bn" />
                        </FormField>
                        <FormField
                            id="g_relation"
                            :label="$t('students.guardian.relation')"
                            :error="guardianForm.errors.relation"
                            required
                            v-slot="{ id }"
                        >
                            <NativeSelect :id="id" v-model="guardianForm.relation">
                                <option v-for="relation in relations" :key="relation" :value="relation">
                                    {{ $t(`enums.relation.${relation}`) }}
                                </option>
                            </NativeSelect>
                        </FormField>
                        <FormField id="g_alt_phone" :label="$t('students.guardian.altPhone')" :error="guardianForm.errors.alt_phone" v-slot="{ id }">
                            <Input :id="id" v-model="guardianForm.alt_phone" type="tel" />
                        </FormField>
                        <FormField
                            id="g_occupation"
                            :label="$t('students.guardian.occupation')"
                            :error="guardianForm.errors.occupation"
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="guardianForm.occupation" />
                        </FormField>
                        <FormField id="g_nid" :label="$t('students.guardian.nid')" :error="guardianForm.errors.nid" v-slot="{ id }">
                            <Input :id="id" v-model="guardianForm.nid" inputmode="numeric" />
                        </FormField>
                    </div>
                    <label v-if="dialog === 'add'" class="flex items-center gap-2 text-sm">
                        <input v-model="guardianForm.is_primary" type="checkbox" class="size-4" />
                        {{ $t('students.guardian.primary') }}
                    </label>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="dialog = null">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="guardianForm.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="removing ? removing.name_en : null" @confirm="removeGuardian" @cancel="removing = null" />
        <ConfirmDelete :name="deletingStudent ? name : null" @confirm="deleteStudent" @cancel="deletingStudent = false" />
    </AppLayout>
</template>
