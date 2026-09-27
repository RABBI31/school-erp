<script setup lang="ts">
import ConfirmDelete from '@/components/ConfirmDelete.vue';
import FormField from '@/components/FormField.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { useCrudForm } from '@/composables/useCrudForm';
import { useLocalized } from '@/composables/useLocalized';
import { usePermissions } from '@/composables/usePermissions';
import { formatMoney, formatNumber } from '@/lib/format';
import type { FeeHead, FeeWaiver, WaiverType } from '@/types/fees';
import { Link } from '@inertiajs/vue3';
import { BadgeDollarSign, Pencil, Plus, Trash2 } from 'lucide-vue-next';

// The student's fee waivers for the viewed year, on the student profile.
const props = defineProps<{
    enrollmentId: number | null;
    waivers: FeeWaiver[];
    feeHeads: FeeHead[];
}>();

const { localized, locale } = useLocalized();
const { can } = usePermissions();

const types: WaiverType[] = ['percent', 'fixed'];

const { open, editing, deleting, form, create, edit, submit, destroy } = useCrudForm(
    '/fees/waivers',
    () => ({ enrollment_id: props.enrollmentId ?? 0, fee_head_id: props.feeHeads[0]?.id ?? 0, type: 'percent' as WaiverType, value: '', reason: '' }),
    (row: FeeWaiver) => ({ enrollment_id: row.enrollment_id, fee_head_id: row.fee_head_id, type: row.type, value: row.value.replace(/\.00$/, ''), reason: row.reason ?? '' }),
);

const describe = (waiver: FeeWaiver) =>
    waiver.type === 'percent' ? `${formatNumber(waiver.value.replace(/\.00$/, ''), locale.value)}%` : `৳ ${formatMoney(waiver.value, locale.value)}`;
</script>

<template>
    <section class="rounded-xl border p-4 md:p-6">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
            <h2 class="font-semibold">{{ $t('fees.waivers.title') }}</h2>
            <div v-if="enrollmentId" class="flex gap-2">
                <Button v-if="can('fees.collect')" size="sm" variant="outline" as-child>
                    <Link :href="route('fees.collect', { enrollment_id: enrollmentId })"><BadgeDollarSign class="size-4" />{{ $t('fees.cards.collect.title') }}</Link>
                </Button>
                <Button v-if="can('fees.manage') && feeHeads.length" size="sm" variant="outline" @click="create">
                    <Plus class="size-4" />{{ $t('fees.waivers.add') }}
                </Button>
            </div>
        </div>

        <p v-if="!enrollmentId" class="text-sm text-muted-foreground">{{ $t('fees.waivers.noEnrollment') }}</p>
        <p v-else-if="waivers.length === 0" class="text-sm text-muted-foreground">{{ $t('fees.waivers.none') }}</p>
        <ul v-else class="divide-y">
            <li v-for="waiver in waivers" :key="waiver.id" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-2 text-sm">
                <span class="font-medium">{{ localized(waiver.fee_head) }}</span>
                <span class="tabular-nums">{{ describe(waiver) }}</span>
                <span class="flex-1 text-muted-foreground">{{ waiver.reason }}</span>
                <span v-if="can('fees.manage')" class="flex gap-1">
                    <Button size="icon" variant="ghost" :aria-label="$t('common.edit')" @click="edit(waiver)"><Pencil class="size-4" /></Button>
                    <Button size="icon" variant="ghost" :aria-label="$t('common.delete')" @click="deleting = waiver"><Trash2 class="size-4" /></Button>
                </span>
            </li>
        </ul>

        <Dialog v-model:open="open">
            <DialogContent>
                <form class="space-y-6" @submit.prevent="submit">
                    <DialogHeader>
                        <DialogTitle>{{ $t(editing ? 'fees.waivers.edit' : 'fees.waivers.add') }}</DialogTitle>
                        <DialogDescription>{{ $t('fees.waivers.help') }}</DialogDescription>
                    </DialogHeader>
                    <FormField id="fee_head_id" :label="$t('fees.waivers.head')" :error="form.errors.fee_head_id" required v-slot="{ id }">
                        <NativeSelect :id="id" v-model="form.fee_head_id">
                            <option v-for="head in feeHeads" :key="head.id" :value="head.id">{{ localized(head) }}</option>
                        </NativeSelect>
                    </FormField>
                    <div class="grid grid-cols-2 gap-4">
                        <FormField id="type" :label="$t('fees.waivers.type')" :error="form.errors.type" required v-slot="{ id }">
                            <NativeSelect :id="id" v-model="form.type">
                                <option v-for="type in types" :key="type" :value="type">{{ $t(`enums.waiverType.${type}`) }}</option>
                            </NativeSelect>
                        </FormField>
                        <FormField id="value" :label="$t('fees.waivers.value')" :error="form.errors.value" required v-slot="{ id }">
                            <Input :id="id" v-model="form.value" type="number" inputmode="decimal" min="0.01" step="0.01" :max="form.type === 'percent' ? 100 : undefined" required />
                        </FormField>
                    </div>
                    <FormField id="reason" :label="$t('fees.waivers.reason')" :error="form.errors.reason" v-slot="{ id }">
                        <Input :id="id" v-model="form.reason" maxlength="255" />
                    </FormField>
                    <DialogFooter class="gap-2">
                        <Button type="button" variant="outline" @click="open = false">{{ $t('common.cancel') }}</Button>
                        <Button type="submit" :disabled="form.processing">{{ $t('common.save') }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDelete :name="deleting ? localized(deleting.fee_head) : null" @confirm="destroy" @cancel="deleting = null" />
    </section>
</template>
