<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { formatNumber } from '@/lib/format';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { CalendarRange, Check } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

// Changes the year this user is viewing; the school's current year is set in Setup > Academic years.
const page = usePage<SharedData>();
const { locale } = useI18n();

function select(id: number) {
    if (id !== page.props.academicYear?.id) {
        router.post(route('academic-year.switch'), { academic_year_id: id }, { preserveScroll: true });
    }
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="outline" size="sm" class="gap-2" :disabled="page.props.academicYears.length === 0">
                <CalendarRange class="size-4" />
                <span class="hidden sm:inline">{{ $t('shell.academicYear') }}:</span>
                <span>{{ page.props.academicYear ? formatNumber(page.props.academicYear.name, locale) : '—' }}</span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="min-w-44">
            <DropdownMenuLabel>{{ $t('shell.academicYear') }}</DropdownMenuLabel>
            <DropdownMenuItem v-for="year in page.props.academicYears" :key="year.id" @select="select(year.id)">
                <Check :class="['size-4', year.id === page.props.academicYear?.id ? 'opacity-100' : 'opacity-0']" />
                <span class="flex-1">{{ formatNumber(year.name, locale) }}</span>
                <span v-if="year.is_current" class="text-xs text-muted-foreground">{{ $t('setup.years.current') }}</span>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
