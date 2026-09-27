<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BadgeDollarSign, CalendarDays, FileText, ListChecks, ListTree, Receipt, TableProperties } from 'lucide-vue-next';
import { computed } from 'vue';

const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'fees.title', href: '/fees' }];

const all = [
    { href: '/fees/collect', key: 'collect', icon: BadgeDollarSign, permission: 'fees.collect' },
    { href: '/fees/invoices', key: 'invoices', icon: FileText, permission: 'fees.view' },
    { href: '/fees/reports/daily', key: 'daily', icon: Receipt, permission: 'fees.reports' },
    { href: '/fees/reports/dues', key: 'dues', icon: ListChecks, permission: 'fees.reports' },
    { href: '/fees/reports/monthly', key: 'monthly', icon: CalendarDays, permission: 'fees.reports' },
    { href: '/fees/heads', key: 'heads', icon: ListTree, permission: 'fees.manage' },
    { href: '/fees/structure', key: 'structure', icon: TableProperties, permission: 'fees.manage' },
];

const cards = computed(() => all.filter((card) => can(card.permission)));
</script>

<template>
    <Head :title="$t('fees.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('fees.title')" :description="$t('fees.description')" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in cards"
                    :key="card.href"
                    :href="card.href"
                    class="flex flex-col gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <component :is="card.icon" class="size-5 text-muted-foreground" />
                    <div>
                        <div class="font-medium">{{ $t(`fees.cards.${card.key}.title`) }}</div>
                        <div class="text-sm text-muted-foreground">{{ $t(`fees.cards.${card.key}.description`) }}</div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
