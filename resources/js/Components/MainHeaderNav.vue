<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    showControlPanel: {
        type: Boolean,
        default: false,
    },
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
            Панель управления
        </Link>
    </nav>
</template>
