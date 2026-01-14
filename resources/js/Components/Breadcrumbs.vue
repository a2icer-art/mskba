<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
});

const normalizedItems = computed(() => {
    if (!Array.isArray(props.items)) {
        return [];
    }

    return props.items
        .filter((item) => item && item.label)
        .map((item) => ({
            label: item.label,
            href: item.href || '',
        }));
});

const visibleItems = computed(() => {
    const items = normalizedItems.value;
    if (items.length === 0) {
        return [];
    }

    const last = items[items.length - 1];
    if (!last.href) {
        return items.slice(0, -1);
    }

    return items;
});
const hasItems = computed(() => visibleItems.value.length > 0);
</script>

<template>
    <nav v-if="hasItems" class="mb-4 text-xs uppercase tracking-[0.2em] text-slate-400" aria-label="Хлебные крошки">
        <ol class="flex flex-wrap items-center gap-2">
            <li v-for="(item, index) in visibleItems" :key="`${item.label}-${index}`" class="flex items-center gap-2">
                <Link
                    v-if="item.href"
                    class="transition hover:text-slate-600"
                    :href="item.href"
                >
                    {{ item.label }}
                </Link>
                <span v-else class="text-slate-600">{{ item.label }}</span>
                <span v-if="index < visibleItems.length - 1" class="text-slate-300">/</span>
            </li>
        </ol>
    </nav>
</template>
