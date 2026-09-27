<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StaffForm from '@/components/staff/StaffForm.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Staff, StaffRole, StaffStatus } from '@/types/staff';
import { Head, router, useForm } from '@inertiajs/vue3';
import { KeyRound, Trash2 } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    staff: Staff;
    hasPhoto: boolean;
    login: { role: StaffRole | null } | null;
    isSelf: boolean;
    roles: StaffRole[];
    statuses: StaffStatus[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'staff.title', href: '/staff' },
    { title: 'staff.editTitle', href: `/staff/${props.staff.id}/edit` },
];

const loginUrl = route('staff.login.store', { staff: props.staff.id });

const loginForm = useForm({
    role: (props.login?.role ?? 'teacher') as StaffRole,
    password: '',
});

const saveLogin = () => {
    const options = { preserveScroll: true, onSuccess: () => loginForm.reset('password') };
    if (props.login) {
        loginForm.put(loginUrl, options);
    } else {
        loginForm.post(loginUrl, options);
    }
};

const contactError = computed(() => {
    const errors = loginForm.errors as Record<string, string | undefined>;
    return errors.phone ?? errors.email;
});

const removingLogin = ref(false);
const removeLogin = () => router.delete(loginUrl, { preserveScroll: true, onFinish: () => (removingLogin.value = false) });

const deleting = ref(false);
const destroy = () => router.delete(route('staff.destroy', { staff: props.staff.id }));
</script>

<template>
    <Head :title="$t('staff.editTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('staff.editTitle')" :description="`${staff.name_en} · ${staff.employee_code}`">
                <Button v-if="!isSelf" variant="outline" @click="deleting = true"><Trash2 class="size-4" />{{ $t('common.delete') }}</Button>
            </PageHeader>

            <StaffForm :staff="staff" :statuses="statuses" :has-photo="hasPhoto" />

            <!-- Login -->
            <section class="space-y-4 rounded-xl border p-4 md:p-6">
                <h2 class="flex items-center gap-2 font-semibold"><KeyRound class="size-4" />{{ $t('staff.login.title') }}</h2>

                <p v-if="isSelf" class="text-sm text-muted-foreground">{{ $t('staff.login.self') }}</p>
                <template v-else-if="staff.status === 'active'">
                    <p class="text-sm text-muted-foreground">{{ login ? $t('staff.login.has') : $t('staff.login.none') }}</p>
                    <form class="grid gap-4 sm:grid-cols-2" @submit.prevent="saveLogin">
                        <FormField id="role" :label="$t('staff.login.role')" :error="loginForm.errors.role" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="loginForm.role">
                                <option v-for="role in roles" :key="role" :value="role">{{ $t(`enums.role.${role}`) }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField
                            id="password"
                            :label="$t(login ? 'staff.login.newPassword' : 'staff.login.password')"
                            :error="loginForm.errors.password"
                            :hint="$t(login ? 'staff.login.keepPassword' : 'staff.login.passwordHint')"
                            :required="!login"
                            v-slot="{ id }"
                        >
                            <Input :id="id" v-model="loginForm.password" type="text" autocomplete="new-password" :required="!login" minlength="8" />
                        </FormField>
                        <!-- Phone/email clashes come back on the staff fields' keys. -->
                        <p v-if="contactError" class="text-sm text-red-600 sm:col-span-2">{{ contactError }}</p>
                        <div class="flex flex-wrap gap-2 sm:col-span-2">
                            <Button type="submit" :disabled="loginForm.processing">{{
                                $t(login ? 'staff.login.save' : 'staff.login.create')
                            }}</Button>
                            <Button v-if="login" type="button" variant="outline" @click="removingLogin = true">{{ $t('staff.login.remove') }}</Button>
                        </div>
                    </form>
                </template>
                <p v-else class="text-sm text-muted-foreground">{{ $t('staff.login.none') }}</p>
            </section>
        </div>

        <ConfirmDelete
            :name="removingLogin ? $t('staff.login.removeConfirm', { name: staff.name_en }) : null"
            @confirm="removeLogin"
            @cancel="removingLogin = false"
        />
        <ConfirmDelete :name="deleting ? staff.name_en : null" @confirm="destroy" @cancel="deleting = false" />
    </AppLayout>
</template>
