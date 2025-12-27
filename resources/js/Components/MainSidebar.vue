<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        default: 'Навигация',
    },
    items: {
        type: Array,
        default: () => [],
    },
    activeHref: {
        type: String,
        default: '',
    },
});

const hasItems = computed(() => props.items.length > 0);
</script>

<template>
    <aside v-if="hasItems" class="flex flex-col gap-4 rounded-3xl border border-slate-200/80 bg-white/80 p-5 shadow-sm">
        <p v-if="title" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ title }}</p>
        <ul class="space-y-3 text-sm font-medium">
            <li
                v-for="item in items"
                :key="item.href"
                class="rounded-2xl px-4 py-3 transition"
                :class="
                    item.href === activeHref
                        ? 'bg-slate-900 text-white'
                        : 'bg-slate-100 text-slate-700 hover:bg-amber-100/70'
                "
            >
                <Link class="block" :href="item.href">
                    {{ item.label }}
                </Link>
            </li>
        </ul>
    </aside>
</template>
