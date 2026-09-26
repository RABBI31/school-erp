<script setup lang="ts">
import type { StudentStatus } from '@/types/students';

defineProps<{ status: StudentStatus }>();

const styles: Record<StudentStatus, string> = {
    active: 'bg-green-100 text-green-800',
    left: 'bg-neutral-200 text-neutral-700',
    transferred: 'bg-amber-100 text-amber-800',
    graduated: 'bg-blue-100 text-blue-800',
};
</script>

<template>
    <span :class="['rounded-full px-2 py-0.5 text-xs font-medium', styles[status]]">{{ $t(`enums.studentStatus.${status}`) }}</span>
</template>
