import type { FormDataConvertible } from '@inertiajs/core';
import { router, useForm } from '@inertiajs/vue3';
import { ref, type Ref } from 'vue';

type FormData = Record<string, FormDataConvertible>;

/**
 * Create/edit in a dialog and delete with confirmation, for list pages whose
 * routes follow Route::resource: POST {url}, PUT {url}/{id}, DELETE {url}/{id}.
 */
export function useCrudForm<F extends FormData, R extends { id: number }>(url: string, empty: () => F, fromRow: (row: R) => F) {
    const open = ref(false);
    const editing = ref(null) as Ref<R | null>;
    const deleting = ref(null) as Ref<R | null>;
    const form = useForm<F>(empty());

    function fill(data: F) {
        form.defaults(data);
        form.reset();
        form.clearErrors();
        open.value = true;
    }

    const create = () => {
        editing.value = null;
        fill(empty());
    };

    const edit = (row: R) => {
        editing.value = row;
        fill(fromRow(row));
    };

    const submit = () => {
        const options = { preserveScroll: true, onSuccess: () => (open.value = false) };

        if (editing.value) {
            form.put(`${url}/${editing.value.id}`, options);
        } else {
            form.post(url, options);
        }
    };

    const destroy = () => {
        if (deleting.value) {
            router.delete(`${url}/${deleting.value.id}`, { preserveScroll: true, onFinish: () => (deleting.value = null) });
        }
    };

    return { open, editing, deleting, form, create, edit, submit, destroy };
}
