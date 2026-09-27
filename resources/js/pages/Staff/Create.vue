<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import StaffForm from '@/components/staff/StaffForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { StaffStatus } from '@/types/staff';
import { Head } from '@inertiajs/vue3';

defineProps<{ statuses: StaffStatus[] }>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'staff.title', href: '/staff' },
    { title: 'staff.add', href: '/staff/create' },
];
</script>

<template>
    <Head :title="$t('staff.add')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-3xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('staff.add')" :description="$t('staff.login.saveFirst')" />
            <StaffForm :statuses="statuses" />
        </div>
    </AppLayout>
</template>
