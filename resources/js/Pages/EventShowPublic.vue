<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    event: {
        type: Object,
        default: null,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');

const formatDateRange = (startsAt, endsAt) => {
    if (!startsAt || !endsAt) {
        return '—';
    }
    const start = new Date(startsAt);
    const end = new Date(endsAt);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return `${startsAt} – ${endsAt}`;
    }
    const dateLabel = start.toLocaleDateString('ru-RU');
    const startTime = start.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    const endTime = end.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    return `${dateLabel}, ${startTime} – ${endTime}`;
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader :app-name="appName" :is-authenticated="isAuthenticated" :login-label="loginLabel" />

            <main class="grid gap-6">
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1">
                            <h1 class="text-3xl font-semibold text-slate-900">
                                {{ event?.title || 'Событие' }}
                            </h1>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ event?.type?.label || 'Тип не задан' }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата</span>
                        <div class="mt-1">{{ formatDateRange(event?.starts_at, event?.ends_at) }}</div>
                    </div>

                    <div v-if="event?.organizer?.login" class="mt-3 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Организатор</span>
                        <div class="mt-1">{{ event.organizer.login }}</div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
