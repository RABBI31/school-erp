<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Pencil, Trash2 } from 'lucide-vue-next';

const emit = defineEmits<{ edit: []; delete: [] }>();
</script>

<template>
    <div class="flex justify-end gap-1">
        <slot />
        <Button variant="ghost" size="icon" :aria-label="$t('common.edit')" :title="$t('common.edit')" @click="emit('edit')">
            <Pencil class="size-4" />
        </Button>
        <Button variant="ghost" size="icon" :aria-label="$t('common.delete')" :title="$t('common.delete')" @click="emit('delete')">
            <Trash2 class="size-4" />
        </Button>
    </div>
</template>
