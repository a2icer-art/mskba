<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        default: '?????????',
    },
    items: {
        type: Array,
        default: () => [],
    },
    emptyText: {
        type: String,
        default: '???? ??? ??????? ??? ?????????.',
    },
});

const hasItems = computed(() => props.items.length > 0);
</script>

<template>
    <aside class="flex flex-col gap-4 rounded-3xl border border-slate-200/80 bg-white/80 p-5 shadow-sm">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ title }}</p>
        <ul v-if="hasItems" class="space-y-3 text-sm font-medium text-slate-700">
            <li v-for="item in items" :key="item.href" class="rounded-2xl bg-slate-100 px-4 py-3 transition hover:bg-amber-100/70">
                <Link class="block" :href="item.href">
                    {{ item.label }}
                </Link>
            </li>
        </ul>
        <div v-else class="rounded-2xl border border-dashed border-slate-200 p-4 text-xs text-slate-500">
            {{ emptyText }}
        </div>
    </aside>
</template>
