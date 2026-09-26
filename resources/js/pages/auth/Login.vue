<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

defineProps<{
    status?: string;
}>();

const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <AuthBase :title="$t('auth.loginTitle')" :description="$t('auth.loginDescription')">
        <Head :title="$t('auth.submit')" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="login">{{ $t('auth.login') }}</Label>
                    <Input
                        id="login"
                        type="text"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="username"
                        v-model="form.login"
                        :placeholder="$t('auth.loginPlaceholder')"
                    />
                    <InputError :message="form.errors.login" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ $t('auth.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        :placeholder="$t('auth.password')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" :tabindex="3" />
                        <span>{{ $t('auth.remember') }}</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" :tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ $t('auth.submit') }}
                </Button>
            </div>
        </form>
    </AuthBase>
</template>
