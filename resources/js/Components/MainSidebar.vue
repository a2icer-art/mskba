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

const groups = computed(() => {
    const items = props.items;

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
                        <Link class="block" :href="item.href">
                            {{ item.label }}
                        </Link>
                    </li>
                </ul>
            </div>
        </div>
    </aside>
</template>
