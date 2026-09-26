<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import StudentForm from '@/components/students/StudentForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { StudentFormOptions } from '@/types/students';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<StudentFormOptions & { defaultYearId: number | null }>();

const options = computed<StudentFormOptions>(() => props);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'students.title', href: '/students' },
    { title: 'students.admission.title', href: '/students/create' },
];
</script>

<template>
    <Head :title="$t('students.admission.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('students.admission.title')" />
            <StudentForm mode="create" :options="options" :default-year-id="defaultYearId" />
        </div>
    </AppLayout>
</template>
