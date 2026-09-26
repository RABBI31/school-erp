<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useGuardianSearch } from '@/composables/useGuardianSearch';
import { useLocalized } from '@/composables/useLocalized';
import type { NamedRecord } from '@/types/setup';
import type { Enrollment, GuardianMatch, GuardianRelation, Student, StudentFormOptions, StudentStatus } from '@/types/students';
import { useForm } from '@inertiajs/vue3';
import { Plus, Trash2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

/**
 * Admission (create) and editing share this form. Only admission has guardians
 * and the academic year; editing changes the enrollment of the viewed year, if any.
 */
const props = defineProps<{
    mode: 'create' | 'edit';
    options: StudentFormOptions;
    student?: Student;
    enrollment?: Enrollment | null;
    statuses?: StudentStatus[];
    defaultYearId?: number | null;
    hasPhoto?: boolean;
}>();

const { localized } = useLocalized();

interface GuardianEntry {
    id: number | null;
    name_en: string;
    name_bn: string;
    relation: GuardianRelation;
    phone: string;
    alt_phone: string;
    occupation: string;
    is_primary: boolean;
    [key: string]: string | number | boolean | null;
}

const newGuardian = (relation: GuardianRelation, primary: boolean): GuardianEntry => ({
    id: null,
    name_en: '',
    name_bn: '',
    relation,
    phone: '',
    alt_phone: '',
    occupation: '',
    is_primary: primary,
});

const s = props.student;
const e = props.enrollment;

const form = useForm({
    name_en: s?.name_en ?? '',
    name_bn: s?.name_bn ?? '',
    date_of_birth: s?.date_of_birth ?? '',
    gender: (s?.gender ?? '') as string,
    blood_group: s?.blood_group ?? '',
    religion: s?.religion ?? '',
    father_name: s?.father_name ?? '',
    mother_name: s?.mother_name ?? '',
    present_address: s?.present_address ?? '',
    permanent_address: s?.permanent_address ?? '',
    birth_reg_no: s?.birth_reg_no ?? '',
    admission_date: s?.admission_date ?? new Date().toISOString().slice(0, 10),
    status: (s?.status ?? 'active') as string,
    photo: null as File | null,
    academic_year_id: (e?.academic_year_id ?? props.defaultYearId ?? '') as number | string,
    section_id: (e?.section_id ?? '') as number | string,
    group_id: (e?.group_id ?? null) as number | null,
    roll: (e?.roll ?? '') as number | string,
    optional_subject_id: (e?.optional_subject_id ?? null) as number | null,
    guardians: props.mode === 'create' ? [newGuardian('father', true)] : ([] as GuardianEntry[]),
});

const showEnrollment = computed(() => props.mode === 'create' || !!props.enrollment);

// Form errors use dotted keys for guardians: "guardians.0.phone".
const error = (key: string) => (form.errors as Record<string, string | undefined>)[key];

const sectionsByClass = computed(() => {
    const groups = new Map<number, { label: string; sections: StudentFormOptions['sections'] }>();
    for (const section of props.options.sections) {
        const entry = groups.get(section.school_class_id) ?? {
            label: localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }),
            sections: [],
        };
        entry.sections.push(section);
        groups.set(section.school_class_id, entry);
    }
    return [...groups.values()];
});

const selectedSection = computed(() => props.options.sections.find((section) => section.id === Number(form.section_id)));

const optionalSubjects = computed(() =>
    props.options.optionalSubjects.filter(
        (subject) =>
            subject.school_class_id === selectedSection.value?.school_class_id && (subject.group_id === null || subject.group_id === form.group_id),
    ),
);

watch(selectedSection, (section, previous) => {
    if (!section?.has_groups) {
        form.group_id = null;
    }
    if (previous && section?.school_class_id !== previous.school_class_id) {
        form.optional_subject_id = null;
    }
});

watch(optionalSubjects, (subjects) => {
    if (form.optional_subject_id !== null && !subjects.some((subject) => subject.id === form.optional_subject_id)) {
        form.optional_subject_id = null;
    }
});

// Suggest the next free roll whenever section or year changes (admission only),
// unless the user typed their own roll.
let suggestedRoll: number | string = '';
watch(
    () => [form.section_id, form.academic_year_id],
    async ([sectionId, yearId]) => {
        if (props.mode !== 'create' || !sectionId || !yearId) {
            return;
        }
        const response = await fetch(`${route('students.next-roll')}?section_id=${sectionId}&academic_year_id=${yearId}`, {
            headers: { Accept: 'application/json' },
        });
        if (response.ok && (form.roll === '' || form.roll === suggestedRoll)) {
            suggestedRoll = (await response.json()).roll;
            form.roll = suggestedRoll;
        }
    },
);

// Guardian lookup by phone, one search per guardian entry.
const searches = [useGuardianSearch(), useGuardianSearch(), useGuardianSearch()];
const linked = ref<Record<number, GuardianMatch | undefined>>({});

const onPhone = (index: number) => searches[index].search(form.guardians[index].phone);

const useExisting = (index: number, match: GuardianMatch) => {
    linked.value[index] = match;
    form.guardians[index] = { ...newGuardian(match.relation, form.guardians[index].is_primary), id: match.id, phone: match.phone };
};

const unlink = (index: number) => {
    linked.value[index] = undefined;
    form.guardians[index] = { ...form.guardians[index], id: null };
};

const setPrimary = (index: number) => form.guardians.forEach((guardian, i) => (guardian.is_primary = i === index));

const addGuardian = () => form.guardians.push(newGuardian(form.guardians.length === 1 ? 'mother' : 'other', false));

const removeGuardian = (index: number) => {
    const wasPrimary = form.guardians[index].is_primary;
    form.guardians.splice(index, 1);
    linked.value = {};
    if (wasPrimary) {
        setPrimary(0);
    }
};

const pickPhoto = (event: Event) => (form.photo = (event.target as HTMLInputElement).files?.[0] ?? null);

const submit = () => {
    if (props.mode === 'create') {
        form.post(route('students.store'));
    } else {
        // Files need a POST with method spoofing.
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('students.update', { student: props.student!.id }));
    }
};

const yearOptions = computed<NamedRecord[]>(() => props.options.academicYearOptions);
</script>

<template>
    <form class="space-y-8" @submit.prevent="submit">
        <!-- Student -->
        <section class="space-y-4 rounded-xl border p-4 md:p-6">
            <h2 class="font-semibold">{{ $t('students.admission.studentInfo') }}</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField id="name_bn" :label="$t('students.fields.nameBn')" :error="form.errors.name_bn" v-slot="{ id }">
                    <Input :id="id" v-model="form.name_bn" />
                </FormField>
                <FormField id="name_en" :label="$t('students.fields.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                    <Input :id="id" v-model="form.name_en" required />
                </FormField>
                <FormField id="gender" :label="$t('students.gender')" :error="form.errors.gender" required v-slot="{ id }">
                    <NativeSelect :id="id" v-model="form.gender" required>
                        <option value="" disabled>—</option>
                        <option v-for="gender in options.genders" :key="gender" :value="gender">{{ $t(`enums.gender.${gender}`) }}</option>
                    </NativeSelect>
                </FormField>
                <FormField id="date_of_birth" :label="$t('students.fields.dateOfBirth')" :error="form.errors.date_of_birth" v-slot="{ id }">
                    <Input :id="id" v-model="form.date_of_birth" type="date" />
                </FormField>
                <FormField id="religion" :label="$t('students.fields.religion')" :error="form.errors.religion" v-slot="{ id }">
                    <NativeSelect :id="id" v-model="form.religion">
                        <option value="">—</option>
                        <option v-for="religion in options.religions" :key="religion" :value="religion">
                            {{ $t(`enums.religion.${religion}`) }}
                        </option>
                    </NativeSelect>
                </FormField>
                <FormField id="blood_group" :label="$t('students.fields.bloodGroup')" :error="form.errors.blood_group" v-slot="{ id }">
                    <NativeSelect :id="id" v-model="form.blood_group">
                        <option value="">—</option>
                        <option v-for="group in options.bloodGroups" :key="group" :value="group">{{ group }}</option>
                    </NativeSelect>
                </FormField>
                <FormField id="father_name" :label="$t('students.fields.fatherName')" :error="form.errors.father_name" v-slot="{ id }">
                    <Input :id="id" v-model="form.father_name" />
                </FormField>
                <FormField id="mother_name" :label="$t('students.fields.motherName')" :error="form.errors.mother_name" v-slot="{ id }">
                    <Input :id="id" v-model="form.mother_name" />
                </FormField>
                <FormField id="present_address" :label="$t('students.fields.presentAddress')" :error="form.errors.present_address" v-slot="{ id }">
                    <Input :id="id" v-model="form.present_address" />
                </FormField>
                <FormField
                    id="permanent_address"
                    :label="$t('students.fields.permanentAddress')"
                    :error="form.errors.permanent_address"
                    v-slot="{ id }"
                >
                    <Input :id="id" v-model="form.permanent_address" />
                </FormField>
                <FormField id="birth_reg_no" :label="$t('students.fields.birthRegNo')" :error="form.errors.birth_reg_no" v-slot="{ id }">
                    <Input :id="id" v-model="form.birth_reg_no" inputmode="numeric" />
                </FormField>
                <FormField
                    id="admission_date"
                    :label="$t('students.fields.admissionDate')"
                    :error="form.errors.admission_date"
                    required
                    v-slot="{ id }"
                >
                    <Input :id="id" v-model="form.admission_date" type="date" required />
                </FormField>
                <FormField v-if="mode === 'edit'" id="status" :label="$t('students.status')" :error="form.errors.status" required v-slot="{ id }">
                    <NativeSelect :id="id" v-model="form.status">
                        <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.studentStatus.${status}`) }}</option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="photo"
                    :label="$t('students.fields.photo')"
                    :error="form.errors.photo"
                    :hint="$t('students.fields.photoHint')"
                    v-slot="{ id }"
                >
                    <img
                        v-if="hasPhoto && student"
                        :src="route('students.photo', { student: student.id })"
                        alt=""
                        class="h-24 w-20 rounded border object-cover"
                    />
                    <Input :id="id" type="file" accept="image/jpeg,image/png,image/webp" @change="pickPhoto" />
                </FormField>
            </div>
        </section>

        <!-- Guardians (admission only; edited on the profile afterwards) -->
        <section v-if="mode === 'create'" class="space-y-4 rounded-xl border p-4 md:p-6">
            <h2 class="font-semibold">{{ $t('students.admission.guardians') }}</h2>
            <InputError :message="error('guardians')" />

            <div v-for="(guardian, index) in form.guardians" :key="index" class="space-y-4 rounded-lg bg-muted/40 p-4">
                <div class="flex items-center justify-between gap-2">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="radio" name="primary_guardian" :checked="guardian.is_primary" class="size-4" @change="setPrimary(index)" />
                        {{ $t('students.guardian.primary') }}
                    </label>
                    <Button v-if="form.guardians.length > 1" type="button" variant="ghost" size="sm" @click="removeGuardian(index)">
                        <Trash2 class="size-4" />{{ $t('students.guardian.remove') }}
                    </Button>
                </div>

                <FormField
                    :id="`guardian_phone_${index}`"
                    :label="$t('students.guardian.phone')"
                    :error="error(`guardians.${index}.phone`)"
                    required
                    v-slot="{ id }"
                >
                    <Input
                        :id="id"
                        v-model="guardian.phone"
                        type="tel"
                        inputmode="tel"
                        required
                        :disabled="guardian.id !== null"
                        @input="onPhone(index)"
                    />
                </FormField>

                <!-- Linked to an existing guardian (sibling) -->
                <div v-if="guardian.id !== null && linked[index]" class="rounded-md border border-green-200 bg-green-50 p-3 text-sm text-green-900">
                    <div class="font-medium">
                        {{ $t('students.guardian.linked') }}:
                        {{ localized({ name_en: linked[index]!.name_en, name_bn: linked[index]!.name_bn ?? linked[index]!.name_en }) }}
                    </div>
                    <div>{{ $t('students.guardian.childrenOf', { names: linked[index]!.students.map((child) => child.name_en).join(', ') }) }}</div>
                    <Button type="button" variant="link" class="h-auto p-0" @click="unlink(index)">{{ $t('students.guardian.unlink') }}</Button>
                </div>
                <InputError :message="error(`guardians.${index}.id`)" />

                <template v-if="guardian.id === null">
                    <div v-if="searches[index].matches.value.length > 0" class="space-y-2 rounded-md border p-3 text-sm">
                        <div class="font-medium">{{ $t('students.guardian.found') }}</div>
                        <div v-for="match in searches[index].matches.value" :key="match.id" class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <div>{{ match.name_en }} · {{ $t(`enums.relation.${match.relation}`) }}</div>
                                <div class="text-muted-foreground">
                                    {{ $t('students.guardian.childrenOf', { names: match.students.map((child) => child.name_en).join(', ') }) }}
                                </div>
                            </div>
                            <Button type="button" size="sm" variant="outline" @click="useExisting(index, match)">{{
                                $t('students.guardian.use')
                            }}</Button>
                        </div>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField
                            :id="`guardian_name_${index}`"
                            :label="$t('students.guardian.name')"
                            :error="error(`guardians.${index}.name_en`)"
                            required
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="guardian.name_en" required />
                        </FormField>
                        <FormField
                            :id="`guardian_name_bn_${index}`"
                            :label="$t('students.guardian.nameBn')"
                            :error="error(`guardians.${index}.name_bn`)"
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="guardian.name_bn" />
                        </FormField>
                        <FormField
                            :id="`guardian_relation_${index}`"
                            :label="$t('students.guardian.relation')"
                            :error="error(`guardians.${index}.relation`)"
                            required
                            v-slot="{ id }"
                        >
                            <NativeSelect :id="id" v-model="guardian.relation">
                                <option v-for="relation in options.relations" :key="relation" :value="relation">
                                    {{ $t(`enums.relation.${relation}`) }}
                                </option>
                            </NativeSelect>
                        </FormField>
                        <FormField
                            :id="`guardian_occupation_${index}`"
                            :label="$t('students.guardian.occupation')"
                            :error="error(`guardians.${index}.occupation`)"
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="guardian.occupation" />
                        </FormField>
                    </div>
                </template>
            </div>

            <Button v-if="form.guardians.length < 3" type="button" variant="outline" size="sm" @click="addGuardian">
                <Plus class="size-4" />{{ $t('students.guardian.addAnother') }}
            </Button>
        </section>

        <!-- Enrollment -->
        <section class="space-y-4 rounded-xl border p-4 md:p-6">
            <h2 class="font-semibold">{{ $t('students.admission.enrollment') }}</h2>
            <p v-if="!showEnrollment" class="text-sm text-muted-foreground">{{ $t('students.admission.noEnrollment') }}</p>
            <div v-else class="grid gap-4 sm:grid-cols-2">
                <FormField
                    v-if="mode === 'create'"
                    id="academic_year_id"
                    :label="$t('students.fields.academicYear')"
                    :error="form.errors.academic_year_id"
                    required
                    v-slot="{ id }"
                >
                    <NativeSelect :id="id" v-model="form.academic_year_id" required>
                        <option value="" disabled>—</option>
                        <option v-for="year in yearOptions" :key="year.id" :value="year.id">{{ year.name }}</option>
                    </NativeSelect>
                </FormField>
                <FormField id="section_id" :label="$t('students.section')" :error="form.errors.section_id" required v-slot="{ id }">
                    <NativeSelect :id="id" v-model="form.section_id" required>
                        <option value="" disabled>—</option>
                        <optgroup v-for="group in sectionsByClass" :key="group.label" :label="group.label">
                            <option v-for="section in group.sections" :key="section.id" :value="section.id">
                                {{ group.label }} — {{ section.name }} ({{ section.shift }}, {{ section.version }})
                            </option>
                        </optgroup>
                    </NativeSelect>
                </FormField>
                <FormField
                    v-if="selectedSection?.has_groups"
                    id="group_id"
                    :label="$t('students.group')"
                    :error="form.errors.group_id"
                    required
                    v-slot="{ id }"
                >
                    <NativeSelect :id="id" v-model="form.group_id" required>
                        <option :value="null" disabled>—</option>
                        <option v-for="group in options.groups" :key="group.id" :value="group.id">{{ group.name }}</option>
                    </NativeSelect>
                </FormField>
                <FormField
                    id="roll"
                    :label="$t('students.roll')"
                    :error="form.errors.roll"
                    :hint="mode === 'create' ? $t('students.fields.rollHint') : undefined"
                    required
                    v-slot="{ id }"
                >
                    <Input :id="id" v-model="form.roll" type="number" min="1" max="9999" required />
                </FormField>
                <FormField
                    v-if="optionalSubjects.length > 0 || form.optional_subject_id !== null"
                    id="optional_subject_id"
                    :label="$t('students.fields.optionalSubject')"
                    :error="form.errors.optional_subject_id"
                    v-slot="{ id }"
                >
                    <NativeSelect :id="id" v-model="form.optional_subject_id">
                        <option :value="null">{{ $t('students.fields.none') }}</option>
                        <option v-for="subject in optionalSubjects" :key="subject.id" :value="subject.id">{{ localized(subject) }}</option>
                    </NativeSelect>
                </FormField>
            </div>
        </section>

        <Button type="submit" size="lg" :disabled="form.processing">
            {{ $t(mode === 'create' ? 'students.admission.submit' : 'students.admission.save') }}
        </Button>
    </form>
</template>
