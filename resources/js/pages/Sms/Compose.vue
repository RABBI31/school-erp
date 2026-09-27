<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import { smsParts } from '@/lib/sms';
import type { BreadcrumbItem } from '@/types';
import type { ClassOption, SectionOption } from '@/types/fees';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { History, Send } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    filters: { school_class_id?: string; section_id?: string };
    classes: ClassOption[];
    sections: SectionOption[];
    recipients: number;
    balance: number;
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'sms.log.title', href: '/sms/log' },
    { title: 'sms.title', href: '/sms' },
];

const form = useForm({
    message: '',
    school_class_id: props.filters.school_class_id ?? '',
    section_id: props.filters.section_id ?? '',
});

const visibleSections = computed(() => props.sections.filter((s) => !form.school_class_id || String(s.school_class_id) === String(form.school_class_id)));

// Changing the group reloads only the recipient count.
const reload = () =>
    router.get(
        route('sms.create'),
        Object.fromEntries(Object.entries({ school_class_id: form.school_class_id, section_id: form.section_id }).filter(([, v]) => v !== '')),
        { preserveState: true, preserveScroll: true, replace: true, only: ['recipients', 'filters'] },
    );

const parts = computed(() => smsParts(form.message));
const cost = computed(() => parts.value * props.recipients);
const short = computed(() => cost.value > props.balance);
const n = (value: number) => formatNumber(value, locale.value);

const confirming = ref(false);
const send = () =>
    form
        .transform((data) => ({ ...data, school_class_id: data.school_class_id || null, section_id: data.section_id || null }))
        .post(route('sms.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('message'),
            onFinish: () => (confirming.value = false),
        });
</script>

<template>
    <Head :title="$t('sms.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="max-w-2xl space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('sms.title')" :description="$t('sms.description')">
                <Button variant="outline" as-child>
                    <Link :href="route('sms.log')"><History class="size-4" />{{ $t('sms.log.title') }}</Link>
                </Button>
            </PageHeader>

            <form class="space-y-4" @submit.prevent="confirming = true">
                <div class="grid gap-2 sm:grid-cols-2">
                    <NativeSelect
                        v-model="form.school_class_id"
                        :aria-label="$t('fees.fields.class')"
                        @update:model-value="
                            () => {
                                form.section_id = '';
                                reload();
                            }
                        "
                    >
                        <option value="">{{ $t('sms.allClasses') }}</option>
                        <option v-for="schoolClass in classes" :key="schoolClass.id" :value="String(schoolClass.id)">{{ localized(schoolClass) }}</option>
                    </NativeSelect>
                    <NativeSelect v-model="form.section_id" :aria-label="$t('fees.fields.section')" @update:model-value="reload">
                        <option value="">{{ $t('sms.allSections') }}</option>
                        <option v-for="section in visibleSections" :key="section.id" :value="String(section.id)">
                            {{ localized({ name_en: section.class_name_en, name_bn: section.class_name_bn }) }} — {{ section.name }}
                        </option>
                    </NativeSelect>
                </div>

                <FormField id="message" :label="$t('sms.message')" :error="form.errors.message" required v-slot="{ id }">
                    <textarea :id="id" v-model="form.message" rows="5" maxlength="1000" required class="w-full rounded-md border bg-background px-3 py-2 text-base" />
                    <p class="text-right text-xs text-muted-foreground">{{ $t('sms.characters', { count: n([...form.message].length) }) }}</p>
                </FormField>

                <dl class="grid grid-cols-2 gap-3 rounded-xl border p-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-muted-foreground">{{ $t('sms.recipients') }}</dt>
                        <dd class="text-xl font-semibold tabular-nums">{{ n(recipients) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ $t('sms.parts') }}</dt>
                        <dd class="text-xl font-semibold tabular-nums">{{ n(parts) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ $t('sms.cost') }}</dt>
                        <dd :class="['text-xl font-semibold tabular-nums', short && 'text-red-600']">{{ n(cost) }}</dd>
                    </div>
                    <div>
                        <dt class="text-muted-foreground">{{ $t('sms.balance') }}</dt>
                        <dd class="text-xl font-semibold tabular-nums">{{ n(balance) }}</dd>
                    </div>
                </dl>
                <p v-if="short" class="text-sm text-red-600">{{ $t('sms.short') }}</p>

                <Button type="submit" :disabled="!form.message || recipients === 0 || short || form.processing"><Send class="size-4" />{{ $t('sms.send') }}</Button>
            </form>
        </div>

        <Dialog v-model:open="confirming">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{{ $t('sms.confirmTitle') }}</DialogTitle>
                    <DialogDescription>{{ $t('sms.confirmBody', { recipients: n(recipients), cost: n(cost) }) }}</DialogDescription>
                </DialogHeader>
                <p class="whitespace-pre-wrap rounded-md bg-muted p-3 text-sm">{{ form.message }}</p>
                <DialogFooter class="gap-2">
                    <Button variant="outline" @click="confirming = false">{{ $t('common.cancel') }}</Button>
                    <Button :disabled="form.processing" @click="send">{{ $t('sms.send') }}</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
