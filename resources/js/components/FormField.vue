<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Label } from '@/components/ui/label';

/**
 * Label + input + hint + validation error. The input goes in the default slot
 * and should use the slot's `id` so the label points at it:
 *
 *   <FormField id="name_en" :label="$t('setup.classes.nameEn')" :error="form.errors.name_en" required v-slot="{ id }">
 *       <Input :id="id" v-model="form.name_en" />
 *   </FormField>
 */
defineProps<{
    id: string;
    label: string;
    error?: string;
    hint?: string;
    required?: boolean;
}>();
</script>

<template>
    <div class="grid gap-2">
        <Label :for="id">
            {{ label }}
            <span v-if="required" class="text-destructive" aria-hidden="true">*</span>
        </Label>
        <slot :id="id" />
        <p v-if="hint && !error" class="text-sm text-muted-foreground">{{ hint }}</p>
        <InputError :message="error" />
    </div>
</template>
