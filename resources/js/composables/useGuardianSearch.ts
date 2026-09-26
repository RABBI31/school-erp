import type { GuardianMatch } from '@/types/students';
import { useDebounceFn } from '@vueuse/core';
import { ref } from 'vue';

/**
 * Existing guardians with a phone number, to link siblings to the same guardian.
 */
export function useGuardianSearch() {
    const matches = ref<GuardianMatch[]>([]);

    const search = useDebounceFn(async (phone: string) => {
        const digits = phone.replace(/\D/g, '');

        if (digits.length < 10) {
            matches.value = [];
            return;
        }

        const response = await fetch(`${route('guardians.search')}?phone=${encodeURIComponent(phone)}`, {
            headers: { Accept: 'application/json' },
        });

        matches.value = response.ok ? await response.json() : [];
    }, 300);

    return { matches, search };
}
