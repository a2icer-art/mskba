<script setup>
import { Link } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

defineProps({
    showControlPanel: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const adminModerationBadge = computed(() => {
    const counters = page.props?.adminCounters;
    if (counters && Number.isFinite(Number(counters.moderation_pending))) {
        return Number(counters.moderation_pending);
    }
    const navigation = page.props?.navigation;
    if (!navigation || !Array.isArray(navigation.data)) {
        return '';
    }
    const groups = navigation.data;
    const modGroup = groups.find((g) => g?.title === 'Модерация');
    if (!modGroup) {
        return '';
    }
    if (modGroup.meta && Number.isFinite(Number(modGroup.meta.total_pending))) {
        return Number(modGroup.meta.total_pending);
    }
    const sum = (modGroup.items ?? []).reduce((acc, it) => acc + (Number(it.badge) || 0), 0);
    return sum;
});
</script>

<template>
    <nav class="flex flex-wrap items-center gap-3 text-sm font-medium text-slate-700">
        <Link
            class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300"
            href="/venues"
        >
            Площадки
        </Link>
        <Link
            class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300"
            href="/events"
        >
            События
        </Link>
        <a class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300" href="#">
            Турниры
        </a>
        <a class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300" href="#">
            Сообщество
        </a>
        <Link
            v-if="showControlPanel"
            class="rounded-full border border-blue-200 bg-blue-50 px-4 py-2 text-blue-700 transition hover:-translate-y-0.5 hover:border-blue-300"
            href="/admin"
        >
            <span class="inline-flex items-center gap-2">
                <span>Панель управления</span>
                <span
                    v-if="adminModerationBadge"
                    class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                >
                    {{ adminModerationBadge > 9 ? '…' : adminModerationBadge }}
                </span>
            </span>
        </Link>
    </nav>
</template>
