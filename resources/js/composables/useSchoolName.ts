import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

/** The current school's name in the current UI language. */
export function useSchoolName() {
    const page = usePage<SharedData>();

    return computed(() => {
        const school = page.props.school;

        if (!school) {
            return null;
        }

        return page.props.locale === 'bn' ? school.name_bn : school.name_en;
    });
}
