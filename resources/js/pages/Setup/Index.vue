<script setup lang="ts">
import PageHeader from '@/components/PageHeader.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { formatNumber } from '@/lib/format';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { BookOpen, CalendarRange, Clock, Languages, Layers, LayoutList, School, Shapes } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    counts: Record<'academicYears' | 'shifts' | 'versions' | 'classes' | 'groups' | 'sections' | 'subjects', number>;
}>();

const { locale } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'setup.title', href: '/setup' }];

const cards = [
    { href: '/setup/profile', title: 'setup.profile.title', description: 'setup.profile.description', icon: School, count: null },
    { href: '/setup/academic-years', title: 'setup.years.title', description: 'setup.years.description', icon: CalendarRange, count: props.counts.academicYears },
    { href: '/setup/shifts', title: 'setup.shifts.title', description: 'setup.shifts.description', icon: Clock, count: props.counts.shifts },
    { href: '/setup/versions', title: 'setup.versions.title', description: 'setup.versions.description', icon: Languages, count: props.counts.versions },
    { href: '/setup/classes', title: 'setup.classes.title', description: 'setup.classes.description', icon: Layers, count: props.counts.classes },
    { href: '/setup/groups', title: 'setup.groups.title', description: 'setup.groups.description', icon: Shapes, count: props.counts.groups },
    { href: '/setup/sections', title: 'setup.sections.title', description: 'setup.sections.description', icon: LayoutList, count: props.counts.sections },
    { href: '/setup/subjects', title: 'setup.subjects.title', description: 'setup.subjects.description', icon: BookOpen, count: props.counts.subjects },
];
</script>

<template>
    <Head :title="$t('setup.title')" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="space-y-6 p-4 md:p-6">
            <PageHeader :title="$t('setup.title')" :description="$t('setup.description')" />

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-for="card in cards"
                    :key="card.href"
                    :href="card.href"
                    class="flex flex-col gap-3 rounded-xl border p-4 transition-colors hover:bg-muted/50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                >
                    <div class="flex items-center justify-between">
                        <component :is="card.icon" class="size-5 text-muted-foreground" />
                        <span v-if="card.count !== null" class="text-2xl font-semibold">{{ formatNumber(card.count, locale) }}</span>
                    </div>
                    <div>
                        <div class="font-medium">{{ $t(card.title) }}</div>
                        <div class="text-sm text-muted-foreground">{{ $t(card.description) }}</div>
                    </div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
