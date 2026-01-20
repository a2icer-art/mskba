<script setup>
import { computed, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import EventCreateModal from '../Components/EventCreateModal.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import SystemNoticeStack from '../Components/SystemNoticeStack.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    events: {
        type: Array,
        default: () => [],
    },
    canCreate: {
        type: Boolean,
        default: false,
    },
    canBook: {
        type: Boolean,
        default: false,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.event ?? '');
const hasEvents = computed(() => props.events.length > 0);
const createOpen = ref(false);
const createPrefill = ref({});

const openCreate = (prefill = {}) => {
    createPrefill.value = prefill;
    createOpen.value = true;
};

const closeCreate = () => {
    createOpen.value = false;
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const venue = params.get('venue');
    const date = params.get('date');
    if (venue || date) {
        openCreate({
            venue: venue || '',
            date: date || '',
        });
    }
});

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('ru-RU');
};

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
        <SystemNoticeStack :success="actionNotice" :error="actionError" />

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <main class="grid gap-6">
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">События</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Игры, тренировки и игровые тренировки.
                            </p>
                        </div>
                        <button
                            v-if="canCreate"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openCreate"
                        >
                            Создать событие
                        </button>
                    </div>

                    

                    <div v-if="!hasEvents" class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                        События пока не созданы.
                    </div>
                    <div v-else class="mt-6 grid gap-4">
                        <article
                            v-for="event in events"
                            :key="event.id"
                            class="rounded-2xl border border-slate-200 bg-white px-5 py-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">
                                        <Link class="transition hover:text-slate-700" :href="`/events/${event.id}`">
                                            {{ event.title }}
                                        </Link>
                                    </h2>
                                    <div class="mt-1 text-sm text-slate-600">
                                        {{ event.type?.label || 'Тип не задан' }}
                                    </div>
                                    <div class="mt-2 flex flex-wrap gap-2 text-xs">
                                        <span
                                            v-if="event.has_pending_booking"
                                            class="flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-amber-700"
                                            title="Есть бронирования в ожидании"
                                        >
                                            <span class="text-sm leading-none">•</span>
                                        </span>
                                        <span
                                            v-if="event.has_awaiting_payment_booking"
                                            class="flex items-center rounded-full border border-indigo-200 bg-indigo-50 px-2 py-0.5 text-indigo-700"
                                            title="Есть бронирования в ожидании оплаты"
                                        >
                                            <span class="text-sm leading-none">◇</span>
                                        </span>
                                        <span
                                            v-if="event.has_paid_booking"
                                            class="flex items-center rounded-full border border-sky-200 bg-sky-50 px-2 py-0.5 text-sky-700"
                                            title="Есть оплаченные бронирования"
                                        >
                                            <span class="text-sm leading-none">◆</span>
                                        </span>
                                        <span
                                            v-if="event.has_approved_booking"
                                            class="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700"
                                            title="Есть подтвержденные бронирования"
                                        >
                                            <span class="text-sm leading-none">✓</span>
                                        </span>
                                        <span
                                            v-if="event.has_cancelled_booking"
                                            class="flex items-center rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700"
                                            title="Есть отмененные бронирования"
                                        >
                                            <span class="text-sm leading-none">✕</span>
                                        </span>
                                    </div>
                                </div>
                                <Link
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                    :href="`/events/${event.id}`"
                                >
                                    Открыть
                                </Link>
                            </div>
                            <div class="mt-3 text-sm text-slate-700">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Время</span>
                                <div class="mt-1">{{ formatDateRange(event.starts_at, event.ends_at) }}</div>
                            </div>
                        </article>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <EventCreateModal
        :is-open="createOpen"
        :prefill="createPrefill"
        :can-book-fallback="props.canBook"
        @close="closeCreate"
    />
</template>
