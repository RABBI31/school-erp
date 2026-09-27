<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import DataTable from '@/components/DataTable.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RowActions from '@/components/RowActions.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useLocalized } from '@/composables/useLocalized';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatDate, formatNumber } from '@/lib/format';
import { smsParts } from '@/lib/sms';
import type { BreadcrumbItem, DataTableColumn, Paginated } from '@/types';
import type { ClassOption } from '@/types/fees';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Paperclip, Plus } from 'lucide-vue-next';
import { computed, ref } from 'vue';

type Audience = 'all' | 'guardians' | 'staff' | 'class';

interface NoticeRow {
    id: number;
    title_en: string;
    title_bn: string;
    body_en: string | null;
    body_bn: string | null;
    audience: Audience;
    school_class_id: number | null;
    published_at: string;
    is_published: boolean;
    has_attachment: boolean;
    school_class: ClassOption | null;
}

const props = defineProps<{
    notices: Paginated<NoticeRow>;
    filters: Record<string, string>;
    classes: ClassOption[];
    audiences: Audience[];
    recipients: { all: number; classes: Record<string, number> };
    smsBalance: number;
    schoolLocale: string;
    schoolName: { name_en: string; name_bn: string };
}>();

const { localized, locale } = useLocalized();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'notices.title', href: '/notices' }];

const columns: DataTableColumn[] = [
    { key: 'title', label: 'notices.heading' },
    { key: 'audience', label: 'notices.audience' },
    { key: 'published_at', label: 'notices.publishedAt' },
    { key: 'actions', label: 'common.actions', class: 'w-28 text-right' },
];

const now = () => {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
};

const open = ref(false);
const editing = ref<NoticeRow | null>(null);
const form = useForm({
    title_en: '',
    title_bn: '',
    body_en: '',
    body_bn: '',
    audience: 'all' as Audience,
    school_class_id: null as number | null,
    published_at: '',
    attachment: null as File | null,
    send_sms: false,
});

const create = () => {
    editing.value = null;
    form.defaults({ title_en: '', title_bn: '', body_en: '', body_bn: '', audience: 'all', school_class_id: null, published_at: now(), attachment: null, send_sms: false });
    form.reset();
    form.clearErrors();
    open.value = true;
};
const edit = (row: NoticeRow) => {
    editing.value = row;
    form.defaults({
        title_en: row.title_en, title_bn: row.title_bn, body_en: row.body_en ?? '', body_bn: row.body_bn ?? '', audience: row.audience,
        school_class_id: row.school_class_id, published_at: row.published_at, attachment: null, send_sms: false,
    });
    form.reset();
    form.clearErrors();
    open.value = true;
};
const submit = () => {
    const options = { preserveScroll: true, forceFormData: true, onSuccess: () => (open.value = false) };
    if (editing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('notices.update', { notice: editing.value.id }), options);
    } else {
        form.transform((data) => data).post(route('notices.store'), options);
    }
};

const deleting = ref<NoticeRow | null>(null);
const destroy = () => deleting.value && router.delete(route('notices.destroy', { notice: deleting.value.id }), { preserveScroll: true, onFinish: () => (deleting.value = null) });

// SMS cost preview for a new notice, as the server will write it.
const smsText = computed(() => {
    const bn = props.schoolLocale === 'bn';
    const title = bn ? form.title_bn : form.title_en;
    const body = (bn ? form.body_bn || form.body_en : form.body_en || form.body_bn).trim();
    const school = bn ? props.schoolName.name_bn : props.schoolName.name_en;
    return body ? `${title}: ${body} — ${school}` : `${title} — ${school}`;
});
const smsRecipients = computed(() =>
    form.audience === 'staff' ? 0 : form.audience === 'class' ? (props.recipients.classes[String(form.school_class_id)] ?? 0) : props.recipients.all,
);
const smsCost = computed(() => smsRecipients.value * smsParts(smsText.value));
</script>

<template>
    <Head :title="$t('notices.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('notices.title')" :description="$t('notices.description')">
                <Button @click="create"><Plus class="size-4" />{{ $t('notices.add') }}</Button>
            </PageHeader>

            <DataTable :rows="notices" :columns="columns" :filters="filters">
                <template #cell-title="{ row }">
                    <div class="font-medium">{{ localized({ name_en: row.title_en, name_bn: row.title_bn }) }}</div>
                    <div class="line-clamp-1 text-xs text-muted-foreground">{{ locale === 'bn' ? row.body_bn || row.body_en : row.body_en || row.body_bn }}</div>
                </template>
                <template #cell-audience="{ row }">
                    {{ $t(`enums.noticeAudience.${row.audience}`) }}<template v-if="row.school_class">: {{ localized(row.school_class) }}</template>
                </template>
                <template #cell-published_at="{ row }">
                    <span class="whitespace-nowrap">{{ formatDate(row.published_at, locale) }}</span>
                    <span v-if="!row.is_published" class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-800">{{ $t('notices.scheduled') }}</span>
                </template>
                <template #cell-actions="{ row }">
                    <div class="flex items-center justify-end">
                        <Button v-if="row.has_attachment" size="icon" variant="ghost" as-child>
                            <a :href="route('notices.attachment', { notice: row.id })" target="_blank" :aria-label="$t('notices.open')"><Paperclip class="size-4" /></a>
                        </Button>
                        <RowActions @edit="edit(row)" @delete="deleting = row" />
                    </div>
                </template>
            </DataTable>
        </div>

        <Dialog v-model:open="open">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <form class="space-y-4" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'notices.edit' : 'notices.add') }}</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <FormField id="title_bn" :label="$t('notices.titleBn')" :error="form.errors.title_bn" required v-slot="{ id }">
                            <Input :id="id" v-model="form.title_bn" required autofocus />
                        </FormField>
                        <FormField id="title_en" :label="$t('notices.titleEn')" :error="form.errors.title_en" required v-slot="{ id }">
                            <Input :id="id" v-model="form.title_en" required />
                        </FormField>
                        <FormField id="body_bn" :label="$t('notices.bodyBn')" :error="form.errors.body_bn" v-slot="{ id }">
                            <textarea :id="id" v-model="form.body_bn" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        </FormField>
                        <FormField id="body_en" :label="$t('notices.bodyEn')" :error="form.errors.body_en" v-slot="{ id }">
                            <textarea :id="id" v-model="form.body_en" rows="4" class="w-full rounded-md border bg-background px-3 py-2 text-sm" />
                        </FormField>
                        <FormField id="audience" :label="$t('notices.audience')" :error="form.errors.audience" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.audience">
                                <option v-for="audience in audiences" :key="audience" :value="audience">{{ $t(`enums.noticeAudience.${audience}`) }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField v-if="form.audience === 'class'" id="school_class_id" :label="$t('notices.class')" :error="form.errors.school_class_id" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.school_class_id">
                                <option v-for="schoolClass in classes" :key="schoolClass.id" :value="schoolClass.id">{{ localized(schoolClass) }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField id="published_at" :label="$t('notices.publishedAt')" :error="form.errors.published_at" v-slot="{ id }">
                            <Input :id="id" v-model="form.published_at" type="datetime-local" />
                        </FormField>
                        <FormField id="attachment" :label="$t('notices.attachment')" :error="form.errors.attachment" v-slot="{ id }">
                            <Input :id="id" type="file" accept=".pdf,.jpg,.jpeg,.png" @input="(e: Event) => (form.attachment = (e.target as HTMLInputElement).files?.[0] ?? null)" />
                            <p v-if="editing?.has_attachment" class="text-xs text-muted-foreground">{{ $t('notices.attachmentKept') }}</p>
                        </FormField>
                    </div>

                    <div v-if="!editing && form.audience !== 'staff'" class="space-y-1 rounded-md border p-3 text-sm">
                        <label class="flex items-center gap-2 font-medium">
                            <input v-model="form.send_sms" type="checkbox" class="size-4 accent-primary" />
                            {{ $t('notices.sendSms') }}
                        </label>
                        <template v-if="form.send_sms">
                            <p class="text-muted-foreground">{{ $t('notices.smsHelp') }}</p>
                            <p :class="smsCost > smsBalance ? 'text-red-600' : ''">
                                {{
                                    $t('notices.smsPreview', {
                                        recipients: formatNumber(smsRecipients, locale),
                                        parts: formatNumber(smsParts(smsText), locale),
                                        cost: formatNumber(smsCost, locale),
                                        balance: formatNumber(smsBalance, locale),
                                    })
                                }}
                            </p>
                            <p v-if="smsCost > smsBalance" class="text-red-600">{{ $t('notices.smsShort') }}</p>
                        </template>
                    </div>

                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="deleting ? localized({ name_en: deleting.title_en, name_bn: deleting.title_bn }) : null" @confirm="destroy" @cancel="deleting = null" />
    </AppLayout>
</template>
