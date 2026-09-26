<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CircleAlert, CircleCheck, X } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();

const message = ref<{ type: 'success' | 'error'; text: string } | null>(null);
let timer: ReturnType<typeof setTimeout> | undefined;

watch(
    () => page.props.flash,
    (flash) => {
        clearTimeout(timer);
        message.value = flash.error ? { type: 'error', text: flash.error } : flash.success ? { type: 'success', text: flash.success } : null;

        // Errors stay until dismissed; success messages fade out.
        if (message.value?.type === 'success') {
            timer = setTimeout(() => (message.value = null), 4000);
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex justify-center px-4 sm:justify-end" aria-live="polite">
        <div
            v-if="message"
            role="status"
            :class="[
                'pointer-events-auto flex max-w-md items-start gap-3 rounded-lg border px-4 py-3 text-sm shadow-lg',
                message.type === 'error' ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800',
            ]"
        >
            <CircleAlert v-if="message.type === 'error'" class="mt-0.5 size-4 shrink-0" />
            <CircleCheck v-else class="mt-0.5 size-4 shrink-0" />
            <span class="flex-1">{{ message.text }}</span>
            <button type="button" class="shrink-0 opacity-70 hover:opacity-100" :aria-label="$t('common.close')" @click="message = null">
                <X class="size-4" />
            </button>
        </div>
    </div>
</template>
