<script setup lang="ts">
import type { ExamStatus } from '@/types/exams';

defineProps<{ status: ExamStatus }>();

const styles: Record<ExamStatus, string> = {
    draft: 'bg-muted text-muted-foreground',
    marks_entry: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    published: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
};
</script>

<template>
    <span :class="['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', styles[status]]">{{ $t(`enums.examStatus.${status}`) }}</span>
</template>
