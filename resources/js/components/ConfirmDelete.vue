<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

defineProps<{ name: string | null }>();

const emit = defineEmits<{ confirm: []; cancel: [] }>();
</script>

<template>
    <Dialog :open="name !== null" @update:open="(open) => !open && emit('cancel')">
        <DialogContent>
            <DialogHeader>
                <DialogTitle>{{ $t('common.deleteTitle') }}</DialogTitle>
                <DialogDescription>{{ $t('common.deleteConfirm', { name }) }}</DialogDescription>
            </DialogHeader>
            <DialogFooter class="gap-2">
                <Button variant="outline" @click="emit('cancel')">{{ $t('common.cancel') }}</Button>
                <Button variant="destructive" @click="emit('confirm')">{{ $t('common.delete') }}</Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
