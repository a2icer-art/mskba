<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Calendar from 'primevue/calendar';
import AuthModal from '../Components/AuthModal.vue';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import EventCreateModal from '../Components/EventCreateModal.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
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
    navigation: {
        type: Object,
        default: () => ({ title: 'Навигация', data: [] }),
    },
    activeTypeCode: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
const isUserConfirmed = computed(() => page.props.auth?.user?.status === 'confirmed');
const showAuthModal = ref(false);
const authMode = ref('login');
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.event ?? '');
const eventsList = computed(() => (Array.isArray(props.events) ? props.events : []));
const hasEvents = computed(() => eventsList.value.length > 0);
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const activeSidebarHref = computed(() => (props.activeTypeCode ? `/events?type=${props.activeTypeCode}` : '/events'));
const createOpen = ref(false);
const createPrefill = ref({});
const titleFilter = ref('');
const titleSuggestions = ref([]);
const titleSuggestOpen = ref(false);
const typeFilter = ref('');
const myEventsOnly = ref(false);
const participantOnly = ref(false);
const dateRange = ref(null);
const venueQuery = ref('');
const venueFilterId = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;
const pageIndex = ref(1);
const perPage = 6;

const openCreate = (prefill = {}) => {
    createPrefill.value = prefill;
    createOpen.value = true;
};

const closeCreate = () => {
    createOpen.value = false;
};

const openAuthModal = () => {
    authMode.value = 'login';
    showAuthModal.value = true;
};

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const venue = params.get('venue');
    const date = params.get('date');
    const type = params.get('type');
    if (venue || date) {
        openCreate({
            venue: venue || '',
            date: date || '',
        });
    }
    if (type) {
        typeFilter.value = type;
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

const normalized = (value) => (value ?? '').toString().toLowerCase();

const formatDateValue = (value) => {
    const date = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const dateFromValue = computed(() => {
    if (!Array.isArray(dateRange.value) || !dateRange.value[0]) {
        return '';
    }
    return formatDateValue(dateRange.value[0]);
});

const dateToValue = computed(() => {
    if (!Array.isArray(dateRange.value) || !dateRange.value[1]) {
        return '';
    }
    return formatDateValue(dateRange.value[1]);
});

const buildTitleSuggestions = (query) => {
    const needle = normalized(query);
    if (!needle || needle.length < 2) {
        return [];
    }
    const suggestions = new Set();
    eventsList.value.forEach((event) => {
        if (!event?.title) {
            return;
        }
        const title = event.title.toString();
        if (normalized(title).includes(needle)) {
            suggestions.add(title);
        }
    });
    return Array.from(suggestions).slice(0, 8);
};

const updateTitleSuggestions = (value) => {
    titleSuggestions.value = buildTitleSuggestions(value);
    titleSuggestOpen.value = titleSuggestions.value.length > 0;
};

const applyTitleSuggestion = (value) => {
    titleFilter.value = value;
    titleSuggestions.value = [];
    titleSuggestOpen.value = false;
};

const scheduleVenueSuggestions = (value) => {
    venueFilterId.value = '';
    venueSuggestError.value = '';
    if (venueSuggestTimer) {
        clearTimeout(venueSuggestTimer);
    }
    if (!value || value.trim().length < 2) {
        venueSuggestLoading.value = false;
        venueSuggestions.value = [];
        return;
    }
    venueSuggestTimer = setTimeout(() => {
        fetchVenueSuggestions(value.trim());
    }, 250);
};

const fetchVenueSuggestions = async (query) => {
    const requestId = ++venueSuggestRequestId;
    venueSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/venue-suggest?query=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('suggest_failed');
        }
        const data = await response.json();
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = data?.suggestions ?? [];
        venueSuggestError.value = venueSuggestions.value.length ? '' : 'Варианты не найдены.';
    } catch (error) {
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = [];
        venueSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId === venueSuggestRequestId) {
            venueSuggestLoading.value = false;
        }
    }
};

const applyVenueSuggestion = (suggestion) => {
    venueFilterId.value = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};

const clearVenueSelection = () => {
    venueFilterId.value = '';
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};

const filtered = computed(() => {
    const needle = normalized(titleFilter.value);
    const fromValue = dateFromValue.value ? new Date(`${dateFromValue.value}T00:00:00`) : null;
    const toValue = dateToValue.value ? new Date(`${dateToValue.value}T23:59:59`) : null;
    return eventsList.value.filter((event) => {
        if (!event) {
            return false;
        }
        if (myEventsOnly.value && event.organizer?.id !== page.props.auth?.user?.id) {
            return false;
        }
        if (participantOnly.value && !event.is_participant) {
            return false;
        }
        if (venueFilterId.value && Number(event.approved_venue?.id) !== Number(venueFilterId.value)) {
            return false;
        }
        if (needle && !normalized(event.title).includes(needle)) {
            return false;
        }
        if (fromValue || toValue) {
            const startsAt = event.starts_at ? new Date(event.starts_at) : null;
            if (!startsAt || Number.isNaN(startsAt.getTime())) {
                return false;
            }
            if (fromValue && startsAt < fromValue) {
                return false;
            }
            if (toValue && startsAt > toValue) {
                return false;
            }
        }
        return true;
    });
});

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / perPage)));

const paged = computed(() => {
    const start = (pageIndex.value - 1) * perPage;
    return filtered.value.slice(start, start + perPage);
});

watch([titleFilter, myEventsOnly, participantOnly, dateRange], () => {
    pageIndex.value = 1;
});
watch(venueFilterId, () => {
    pageIndex.value = 1;
});

watch(isAuthenticated, (value) => {
    if (!value) {
        myEventsOnly.value = false;
        participantOnly.value = false;
    }
});

watch(totalPages, (value) => {
    if (pageIndex.value > value) {
        pageIndex.value = value;
    }
});

watch(
    () => props.activeTypeCode,
    (value) => {
        if (!value) {
            typeFilter.value = '';
            return;
        }
        typeFilter.value = value;
    },
    { immediate: true }
);
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" :error="actionError" />

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
                @open-login="openAuthModal"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="props.navigation.title"
                    :data="navigationData"
                    :active-href="activeSidebarHref"
                >
                    <details class="event-filters rounded-2xl border border-slate-200/80 bg-white/80 p-4">
                        <summary class="event-filters__summary cursor-pointer text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Фильтры
                        </summary>
                        <div class="event-filters__body mt-4 flex flex-col gap-3 text-sm text-slate-700 w-full">
                            <Calendar
                                v-model="dateRange"
                                selection-mode="range"
                                date-format="dd.mm.yy"
                                show-icon
                                show-button-bar
                                class="prime-date-range w-full"
                                input-class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 w-full"
                                panel-class="rounded-2xl border border-slate-200"
                                placeholder="Выберите диапазон"
                            />
                            <div class="relative w-full">
                                <input
                                    v-model="titleFilter"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 w-full"
                                    type="text"
                                    placeholder="Название события"
                                    @input="updateTitleSuggestions($event.target.value)"
                                    @focus="updateTitleSuggestions(titleFilter)"
                                />
                                <div
                                    v-if="titleSuggestOpen && titleSuggestions.length"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in titleSuggestions"
                                        :key="`${suggestion}-${index}`"
                                        class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                        type="button"
                                        @click="applyTitleSuggestion(suggestion)"
                                    >
                                        {{ suggestion }}
                                    </button>
                                </div>
                            </div>
                            <div class="relative w-full">
                                <input
                                    v-model="venueQuery"
                                    class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 w-full"
                                    :class="{ 'is-loading': venueSuggestLoading }"
                                    type="text"
                                    placeholder="Название площадки"
                                    @input="scheduleVenueSuggestions($event.target.value)"
                                />
                                <div
                                    v-if="venueSuggestError"
                                    class="text-xs text-rose-700"
                                >
                                    {{ venueSuggestError }}
                                </div>
                                <div
                                    v-else-if="!venueSuggestLoading && venueSuggestions.length"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in venueSuggestions"
                                        :key="`${suggestion.id}-${index}`"
                                        class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                        type="button"
                                        @click="applyVenueSuggestion(suggestion)"
                                    >
                                        {{ suggestion.label || suggestion.name }}
                                    </button>
                                </div>
                                <div v-if="venueFilterId" class="flex items-center justify-between text-xs text-slate-500">
                                    <span>Площадка выбрана.</span>
                                    <button
                                        class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                        type="button"
                                        @click="clearVenueSelection"
                                    >
                                        Очистить
                                    </button>
                                </div>
                            </div>
                            <label class="flex items-center gap-3 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <input
                                    v-model="myEventsOnly"
                                    class="input-switch"
                                    type="checkbox"
                                    :disabled="!isAuthenticated"
                                />
                                Я организатор
                            </label>
                            <label class="flex items-center gap-3 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <input
                                    v-model="participantOnly"
                                    class="input-switch"
                                    type="checkbox"
                                    :disabled="!isAuthenticated"
                                />
                                Я участник
                            </label>
                        </div>
                    </details>
                </MainSidebar>

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">События</h1>
                            <p v-if="!isAuthenticated" class="mt-2 text-sm text-slate-600">
                                Для просмотра событий необходимо
                                <button
                                    class="font-semibold text-slate-900 transition hover:text-slate-700"
                                    type="button"
                                    @click="openAuthModal"
                                >
                                    авторизоваться
                                </button>
                                .
                            </p>
                            <p v-else-if="!isUserConfirmed" class="mt-2 text-sm text-slate-600">
                                Для просмотра событий необходимо
                                <Link class="font-semibold text-slate-900 transition hover:text-slate-700" href="/account">
                                    подтвердить свой аккаунт
                                </Link>
                                .
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

                    <div v-if="!paged.length" class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                        События не найдены.
                    </div>
                    <div v-else class="mt-6 grid gap-4">
                        <article
                            v-for="event in paged"
                            :key="event.id"
                            class="rounded-2xl border border-slate-200 bg-white px-5 py-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">
                                        <Link
                                            v-if="isAuthenticated"
                                            class="transition hover:text-slate-700"
                                            :href="`/events/${event.id}`"
                                        >
                                            {{ event.title }}
                                        </Link>
                                        <span v-else>{{ event.title }}</span>
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
                                    <div
                                        v-if="event.has_approved_booking && event.approved_venue?.alias && event.approved_venue?.type_slug"
                                        class="mt-2 text-sm text-slate-700"
                                    >
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Площадка</span>
                                        <div class="mt-1">
                                            <a
                                                class="font-semibold text-slate-900 transition hover:text-slate-700"
                                                :href="`/venues/${event.approved_venue.type_slug}/${event.approved_venue.alias}`"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            >
                                                {{ event.approved_venue.name || 'Площадка' }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                <Link
                                    v-if="isAuthenticated"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                    :href="`/events/${event.id}`"
                                >
                                    Открыть
                                </Link>
                            </div>
                            <div class="mt-3 text-sm text-slate-700">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата</span>
                                <div class="mt-1">{{ formatDateRange(event.starts_at, event.ends_at) }}</div>
                            </div>
                        </article>
                    </div>
                    <div v-if="paged.length" class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                        <div>Страница {{ pageIndex }} из {{ totalPages }}</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                                type="button"
                                :disabled="pageIndex === 1"
                                @click="pageIndex = Math.max(1, pageIndex - 1)"
                            >
                                Назад
                            </button>
                            <button
                                v-for="pageNumber in totalPages"
                                :key="pageNumber"
                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold transition hover:-translate-y-0.5"
                                :class="pageNumber === pageIndex ? 'border-slate-900 bg-slate-900 text-white' : 'text-slate-600 hover:border-slate-300'"
                                type="button"
                                @click="pageIndex = pageNumber"
                            >
                                {{ pageNumber }}
                            </button>
                            <button
                                class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                                type="button"
                                :disabled="pageIndex === totalPages"
                                @click="pageIndex = Math.min(totalPages, pageIndex + 1)"
                            >
                                Вперед
                            </button>
                        </div>
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

    <AuthModal
        :app-name="appName"
        :is-open="showAuthModal"
        :participant-roles="page.props.participantRoles || []"
        :initial-mode="authMode"
        @close="showAuthModal = false"
    />
</template>
