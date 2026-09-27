<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    profile: {
        name_en: string;
        name_bn: string;
        eiin: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        default_locale: 'bn' | 'en';
        absent_sms_enabled: boolean;
    };
    smsBalance: number;
    logoUrl: string | null;
    hasSignature: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'setup.title', href: '/setup' },
    { title: 'setup.profile.title', href: '/setup/profile' },
];

const form = useForm({
    name_en: props.profile.name_en,
    name_bn: props.profile.name_bn,
    eiin: props.profile.eiin ?? '',
    address: props.profile.address ?? '',
    phone: props.profile.phone ?? '',
    email: props.profile.email ?? '',
    default_locale: props.profile.default_locale,
    absent_sms_enabled: props.profile.absent_sms_enabled,
    logo: null as File | null,
    signature: null as File | null,
});

const { locale } = useI18n();

// Bust the browser cache after a new signature is uploaded.
const signatureVersion = ref(Date.now());

const pick = (field: 'logo' | 'signature', event: Event) => {
    form[field] = (event.target as HTMLInputElement).files?.[0] ?? null;
};

const submit = () => {
    form.post('/setup/profile', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('logo', 'signature');
            signatureVersion.value = Date.now();
        },
    });
};
</script>

<template>
    <Head :title="$t('setup.profile.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.profile.title')" :description="$t('setup.profile.description')" />

            <form class="space-y-6" @submit.prevent="submit">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField id="name_bn" :label="$t('setup.profile.nameBn')" :error="form.errors.name_bn" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_bn" required />
                    </FormField>
                    <FormField id="name_en" :label="$t('setup.profile.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
                        <Input :id="id" v-model="form.name_en" required />
                    </FormField>
                    <FormField id="eiin" :label="$t('setup.profile.eiin')" :error="form.errors.eiin" v-slot="{ id }">
                        <Input :id="id" v-model="form.eiin" inputmode="numeric" />
                    </FormField>
                    <FormField
                        id="default_locale"
                        :label="$t('setup.profile.defaultLocale')"
                        :error="form.errors.default_locale"
                        required
                        v-slot="{ id }"
                    >
                        <NativeSelect :id="id" v-model="form.default_locale">
                            <option value="bn">বাংলা</option>
                            <option value="en">English</option>
                        </NativeSelect>
                    </FormField>
                    <FormField id="phone" :label="$t('setup.profile.phone')" :error="form.errors.phone" v-slot="{ id }">
                        <Input :id="id" v-model="form.phone" type="tel" />
                    </FormField>
                    <FormField id="email" :label="$t('setup.profile.email')" :error="form.errors.email" v-slot="{ id }">
                        <Input :id="id" v-model="form.email" type="email" />
                    </FormField>
                </div>
                <FormField id="address" :label="$t('setup.profile.address')" :error="form.errors.address" v-slot="{ id }">
                    <Input :id="id" v-model="form.address" />
                </FormField>

                <div class="space-y-1 rounded-md border p-3">
                    <label class="flex items-center gap-3 text-sm">
                        <input v-model="form.absent_sms_enabled" type="checkbox" class="size-4" />
                        {{ $t('setup.profile.absentSms') }}
                    </label>
                    <p class="text-sm text-muted-foreground">{{ $t('setup.profile.smsBalance', { count: formatNumber(smsBalance, locale) }) }}</p>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <FormField
                        id="logo"
                        :label="$t('setup.profile.logo')"
                        :error="form.errors.logo"
                        :hint="$t('setup.profile.uploadHint')"
                        v-slot="{ id }"
                    >
                        <img v-if="logoUrl" :src="logoUrl" alt="" class="h-20 w-20 rounded border object-contain" />
                        <Input :id="id" type="file" accept="image/png,image/jpeg,image/webp" @change="pick('logo', $event)" />
                    </FormField>
                    <FormField
                        id="signature"
                        :label="$t('setup.profile.signature')"
                        :error="form.errors.signature"
                        :hint="$t('setup.profile.uploadHint')"
                        v-slot="{ id }"
                    >
                        <img
                            v-if="hasSignature"
                            :src="`/setup/profile/signature?v=${signatureVersion}`"
                            alt=""
                            class="h-20 w-48 rounded border bg-white object-contain"
                        />
                        <p v-else class="text-sm text-muted-foreground">{{ $t('setup.profile.noSignature') }}</p>
                        <Input :id="id" type="file" accept="image/png,image/jpeg,image/webp" @change="pick('signature', $event)" />
                    </FormField>
                </div>

                <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
            </form>
        </div>
    </AppLayout>
</template>
