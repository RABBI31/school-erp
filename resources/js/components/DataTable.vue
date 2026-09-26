<script setup lang="ts" generic="T extends { id: number | string }">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { toBnDigits } from '@/lib/bn-digits';
import type { DataTableColumn, Paginated } from '@/types';
import { router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ChevronLeft, ChevronRight, Search } from 'lucide-vue-next';
import { computed, reactive } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Server-side table: the controller paginates and filters, this component
 * only sends the query string. Pass back the request's current filters so
 * the inputs show them, e.g. `'filters' => $request->only('search', 'status')`.
 *
 * Cells: `#cell-<key>="{ row }"`; filters: `#filters="{ filters }"` (bind with v-model).
 */
const props = withDefaults(
    defineProps<{
        rows: Paginated<T>;
        columns: DataTableColumn[];
        filters?: Record<string, string | number | null | undefined>;
        searchable?: boolean;
    }>(),
    {
        filters: () => ({}),
        searchable: true,
    },
);

const { t, locale } = useI18n();

const state = reactive<Record<string, string | number | null | undefined>>({ search: '', ...props.filters });

function visit(page?: number) {
    const values: Record<string, string | number | null | undefined> = { ...state, page };
    const query: Record<string, string | number> = {};

    for (const [key, value] of Object.entries(values)) {
        if (value !== '' && value !== null && value !== undefined) {
            query[key] = value;
        }
    }

    router.get(window.location.pathname, query, { preserveState: true, preserveScroll: true, replace: true });
}

watchDebounced(state, () => visit(), { debounce: 300, deep: true });

const digits = (value: number | null) => (locale.value === 'bn' ? toBnDigits(value ?? 0) : String(value ?? 0));

const summary = computed(() =>
    t('common.showing', { from: digits(props.rows.from), to: digits(props.rows.to), total: digits(props.rows.total) }),
);
</script>

<template>
    <div class="space-y-4">
        <div v-if="searchable || $slots.filters" class="flex flex-col gap-2 sm:flex-row sm:items-center">
            <div v-if="searchable" class="relative sm:max-w-xs sm:flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input v-model="state.search as string" type="search" :placeholder="$t('common.search')" class="pl-9" />
            </div>
            <slot name="filters" :filters="state" />
        </div>

        <div class="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead v-for="column in columns" :key="column.key" :class="column.class">
                            {{ $t(column.label) }}
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow v-for="row in rows.data" :key="row.id">
                        <TableCell v-for="column in columns" :key="column.key" :class="column.class">
                            <slot :name="`cell-${column.key}`" :row="row">
                                {{ (row as Record<string, unknown>)[column.key] }}
                            </slot>
                        </TableCell>
                    </TableRow>
                    <TableRow v-if="rows.data.length === 0">
                        <TableCell :colspan="columns.length" class="h-24 text-center text-muted-foreground">
                            {{ $t('common.noResults') }}
                        </TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>

        <div v-if="rows.total > 0" class="flex flex-col items-center justify-between gap-2 text-sm text-muted-foreground sm:flex-row">
            <span>{{ summary }}</span>
            <div class="flex gap-2">
                <Button variant="outline" size="sm" :disabled="rows.current_page <= 1" @click="visit(rows.current_page - 1)">
                    <ChevronLeft class="size-4" />
                    {{ $t('common.previous') }}
                </Button>
                <Button variant="outline" size="sm" :disabled="rows.current_page >= rows.last_page" @click="visit(rows.current_page + 1)">
                    {{ $t('common.next') }}
                    <ChevronRight class="size-4" />
                </Button>
            </div>
        </div>
    </div>
</template>
