<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    title: {
        type: String,
        default: 'Навигация',
    },
    data: {
        type: Array,
        default: null,
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

const normalizedItems = computed(() => {
    if (Array.isArray(props.data)) {
        return props.data;
    }

    return props.items;
});

const groups = computed(() => {
    const items = normalizedItems.value;

    if (!Array.isArray(items) || items.length === 0) {
        return [];
    }

    const isGrouped = items.some((item) => Array.isArray(item?.items));

    if (!isGrouped) {
        return [
            {
                title: '',
                items,
            },
        ];
    }

    return items
        .filter((group) => Array.isArray(group?.items) && group.items.length > 0)
        .map((group) => ({
            title: group?.title ?? '',
            items: group.items,
        }));
});

const hasItems = computed(() => groups.value.length > 0);

const formatBadge = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const numeric = Number(value);
    if (Number.isFinite(numeric)) {
        if (numeric <= 0) {
            return '';
        }
        return numeric > 9 ? '…' : String(numeric);
    }
    return String(value);
};
</script>

<template>
    <aside v-if="hasItems" class="flex flex-col gap-4 rounded-3xl border border-slate-200/80 bg-white/80 p-5 shadow-sm">
        <p v-if="title" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ title }}</p>
        <div class="space-y-4">
            <div v-for="group in groups" :key="group.title || group.items[0]?.href" class="space-y-3">
                <p v-if="group.title" class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                    {{ group.title }}
                </p>
                <ul class="space-y-3 text-sm font-medium">
                    <li
                        v-for="item in group.items"
                        :key="item.href"
                        class="rounded-2xl px-4 py-3 transition"
                        :class="
                            item.href === activeHref
                                ? 'bg-slate-900 text-white'
                                : 'bg-slate-100 text-slate-700 hover:bg-amber-100/70'
                        "
                    >
                        <Link class="flex items-center justify-between gap-3" :href="item.href">
                            <span>{{ item.label }}</span>
                            <span
                                v-if="formatBadge(item.badge)"
                                class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ formatBadge(item.badge) }}
                            </span>
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</template>
