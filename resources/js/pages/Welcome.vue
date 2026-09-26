<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { Head } from '@inertiajs/vue3';

// The bare domain has no school; each school logs in on its own subdomain.
const example = `demo.${window.location.host}`;
</script>

<template>
    <Head :title="$t('app.name')" />

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6">
        <div class="absolute right-4 top-4">
            <LocaleSwitcher />
        </div>
        <AppLogoIcon class="size-12 fill-current text-foreground" />
        <h1 class="text-2xl font-semibold">{{ $t('app.name') }}</h1>
        <p class="max-w-md text-center text-muted-foreground">{{ $t('welcome.description', { example }) }}</p>
    </div>
</template>
