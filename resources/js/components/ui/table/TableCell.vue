<script setup lang="ts">
import { cn } from '@/lib/utils';
import type { HTMLAttributes } from 'vue';

const props = defineProps<{ class?: HTMLAttributes['class'] }>();
</script>

<template>
    <td :class="cn('p-3 align-middle', props.class)">
        <slot />
    </td>
</template>
