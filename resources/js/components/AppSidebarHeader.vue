<script setup lang="ts">
import { Breadcrumb, BreadcrumbItem, BreadcrumbLink, BreadcrumbList, BreadcrumbPage, BreadcrumbSeparator } from '@/components/ui/breadcrumb';
import AcademicYearSwitcher from '@/components/AcademicYearSwitcher.vue';
import LocaleSwitcher from '@/components/LocaleSwitcher.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useSchoolName } from '@/composables/useSchoolName';
import type { BreadcrumbItemType } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItemType[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const schoolName = useSchoolName();
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-[[data-collapsible=icon]]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex min-w-0 flex-1 items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <span v-if="schoolName" class="truncate font-semibold">{{ schoolName }}</span>
            <template v-if="breadcrumbs.length > 0">
                <Breadcrumb class="hidden md:block">
                    <BreadcrumbList>
                        <template v-for="(item, index) in breadcrumbs" :key="index">
                            <BreadcrumbItem>
                                <template v-if="index === breadcrumbs.length - 1">
                                    <BreadcrumbPage>{{ $t(item.title) }}</BreadcrumbPage>
                                </template>
                                <template v-else>
                                    <BreadcrumbLink :href="item.href">
                                        {{ $t(item.title) }}
                                    </BreadcrumbLink>
                                </template>
                            </BreadcrumbItem>
                            <BreadcrumbSeparator v-if="index !== breadcrumbs.length - 1" />
                        </template>
                    </BreadcrumbList>
                </Breadcrumb>
            </template>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <AcademicYearSwitcher />
            <LocaleSwitcher />
        </div>
    </header>
</template>
