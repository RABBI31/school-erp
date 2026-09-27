<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import type { Staff, StaffStatus } from '@/types/staff';
import { useForm } from '@inertiajs/vue3';

const props = defineProps<{
    staff?: Staff;
    statuses: StaffStatus[];
    hasPhoto?: boolean;
}>();

const form = useForm({
    employee_code: props.staff?.employee_code ?? '',
    name_en: props.staff?.name_en ?? '',
    name_bn: props.staff?.name_bn ?? '',
    designation: props.staff?.designation ?? '',
    phone: props.staff?.phone ?? '',
    email: props.staff?.email ?? '',
    joining_date: props.staff?.joining_date ?? '',
    status: (props.staff?.status ?? 'active') as string,
    photo: null as File | null,
});

const pickPhoto = (event: Event) => (form.photo = (event.target as HTMLInputElement).files?.[0] ?? null);

const submit = () => {
    if (props.staff) {
        // Files need a POST with method spoofing.
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('staff.update', { staff: props.staff.id }), { preserveScroll: true });
    } else {
        form.post(route('staff.store'));
    }
};
</script>

<template>
    <form class="space-y-6 rounded-xl border p-4 md:p-6" @submit.prevent="submit">
        <div class="grid gap-4 sm:grid-cols-2">
            <FormField id="name_bn" :label="$t('staff.nameBn')" :error="form.errors.name_bn" v-slot="{ id }">
                <Input :id="id" v-model="form.name_bn" />
            </FormField>
            <FormField id="name_en" :label="$t('staff.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                <Input :id="id" v-model="form.name_en" required />
            </FormField>
            <FormField id="designation" :label="$t('staff.designation')" :error="form.errors.designation" v-slot="{ id }">
                <Input :id="id" v-model="form.designation" placeholder="Assistant Teacher" />
            </FormField>
            <FormField
                id="employee_code"
                :label="$t('staff.code')"
                :error="form.errors.employee_code"
                :hint="staff ? undefined : $t('staff.codeHint')"
                v-slot="{ id }"
            >
                <Input :id="id" v-model="form.employee_code" />
            </FormField>
            <FormField id="phone" :label="$t('staff.phone')" :error="form.errors.phone" v-slot="{ id }">
                <Input :id="id" v-model="form.phone" type="tel" inputmode="tel" />
            </FormField>
            <FormField id="email" :label="$t('staff.email')" :error="form.errors.email" v-slot="{ id }">
                <Input :id="id" v-model="form.email" type="email" />
            </FormField>
            <FormField id="joining_date" :label="$t('staff.joiningDate')" :error="form.errors.joining_date" v-slot="{ id }">
                <Input :id="id" v-model="form.joining_date" type="date" />
            </FormField>
            <FormField
                id="status"
                :label="$t('staff.status')"
                :error="form.errors.status"
                :hint="staff ? $t('staff.leftHint') : undefined"
                required
                v-slot="{ id }"
            >
                <NativeSelect :id="id" v-model="form.status">
                    <option v-for="status in statuses" :key="status" :value="status">{{ $t(`enums.staffStatus.${status}`) }}</option>
                </NativeSelect>
            </FormField>
            <FormField id="photo" :label="$t('staff.photo')" :error="form.errors.photo" v-slot="{ id }">
                <img v-if="hasPhoto && staff" :src="route('staff.photo', { staff: staff.id })" alt="" class="h-24 w-20 rounded border object-cover" />
                <Input :id="id" type="file" accept="image/jpeg,image/png,image/webp" @change="pickPhoto" />
            </FormField>
        </div>
        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
    </form>
</template>
