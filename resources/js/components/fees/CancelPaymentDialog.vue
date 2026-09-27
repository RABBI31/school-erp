<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { useForm } from '@inertiajs/vue3';
import { watch } from 'vue';

// Cancelling needs a reason; the payment stays on record as cancelled.
const props = defineProps<{ payment: { id: number; receipt_no: string } | null }>();
const emit = defineEmits<{ close: [] }>();

const form = useForm({ reason: '' });

watch(
    () => props.payment,
    () => {
        form.reset();
        form.clearErrors();
    },
);

const submit = () => {
    if (props.payment) {
        form.post(route('fees.payments.cancel', { payment: props.payment.id }), { preserveScroll: true, onSuccess: () => emit('close') });
    }
};
</script>

<template>
    <Dialog :open="payment !== null" @update:open="(open) => !open && emit('close')">
        <DialogContent>
            <form class="space-y-6" @submit.prevent="submit">
                <DialogHeader>
                    <DialogTitle>{{ $t('fees.cancel.title', { receipt: payment?.receipt_no ?? '' }) }}</DialogTitle>
                    <DialogDescription>{{ $t('fees.cancel.description') }}</DialogDescription>
                </DialogHeader>
                <FormField id="reason" :label="$t('fees.cancel.reason')" :error="form.errors.reason" required v-slot="{ id }">
                    <Input :id="id" v-model="form.reason" required maxlength="255" autofocus />
                </FormField>
                <DialogFooter class="gap-2">
                    <Button type="button" variant="outline" @click="emit('close')">{{ $t('fees.cancel.keep') }}</Button>
                    <Button type="submit" variant="destructive" :disabled="form.processing">{{ $t('fees.cancel.confirm') }}</Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
