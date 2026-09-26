<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import StudentForm from '@/components/students/StudentForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import type { Enrollment, Student, StudentFormOptions, StudentStatus } from '@/types/students';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<
    StudentFormOptions & {
        student: Student;
        enrollment: Enrollment | null;
        hasPhoto: boolean;
        statuses: StudentStatus[];
    }
>();

const options = computed<StudentFormOptions>(() => props);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'students.title', href: '/students' },
    { title: 'students.admission.editTitle', href: `/students/${props.student.id}/edit` },
];
</script>

<template>
    <Head :title="$t('students.admission.editTitle')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-4xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('students.admission.editTitle')" :description="`${student.name_en} · ${student.student_uid}`" />
            <StudentForm mode="edit" :options="options" :student="student" :enrollment="enrollment" :statuses="statuses" :has-photo="hasPhoto" />
        </div>
    </AppLayout>
</template>
