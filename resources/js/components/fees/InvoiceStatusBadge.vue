<script setup lang="ts">
import type { InvoiceStatus } from '@/types/fees';

defineProps<{ status: InvoiceStatus }>();

const styles: Record<InvoiceStatus, string> = {
    unpaid: 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
    partial: 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
    paid: 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
    cancelled: 'bg-muted text-muted-foreground line-through',
};
</script>

<template>
    <span :class="['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium', styles[status]]">{{ $t(`enums.invoiceStatus.${status}`) }}</span>
</template>
