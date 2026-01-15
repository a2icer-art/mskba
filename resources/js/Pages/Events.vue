<script setup>
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    events: {
        type: Array,
        default: () => [],
    },
    eventTypes: {
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
const createForm = useForm({
    event_type_id: '',
    title: '',
    venue_id: '',
    date: '',
    starts_time: '',
    ends_time: '',
    starts_at: '',
    ends_at: '',
});

const gameTypeCodes = new Set(['game', 'training', 'game_training']);
const selectedType = computed(() => {
    if (!createForm.event_type_id) {
        return null;
    }
    return props.eventTypes.find((type) => String(type.id) === String(createForm.event_type_id)) || null;
});
const isGameTypeSelected = computed(() => selectedType.value && gameTypeCodes.has(selectedType.value.code));
const canSelectVenue = computed(() => props.canBook && isGameTypeSelected.value);
const venueQuery = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;

const openCreate = () => {
    createForm.clearErrors();
    createOpen.value = true;
};

const closeCreate = () => {
    createForm.reset('event_type_id', 'title', 'venue_id', 'date', 'starts_time', 'ends_time', 'starts_at', 'ends_at');
    createForm.clearErrors();
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    createOpen.value = false;
};

const submitCreate = () => {
    createForm.starts_at = combineDateTime(createForm.date, createForm.starts_time);
    createForm.ends_at = combineDateTime(createForm.date, createForm.ends_time);
    createForm.post('/events', {
        preserveScroll: true,
        onSuccess: closeCreate,
    });
};

const isCreateDisabled = computed(() => {
    if (createForm.processing) {
        return true;
    }
    return !createForm.event_type_id || !createForm.date || !createForm.starts_time || !createForm.ends_time;
});

const combineDateTime = (date, time) => {
    if (!date || !time) {
        return '';
    }
    return `${date}T${time}`;
};

const handleTypeChange = () => {
    if (!isGameTypeSelected.value) {
        createForm.venue_id = '';
        venueQuery.value = '';
        venueSuggestions.value = [];
        venueSuggestError.value = '';
    }
};

const scheduleVenueSuggestions = (value) => {
    createForm.venue_id = '';
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
    } catch (error) {
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = [];
        venueSuggestError.value = 'Не удалось получить подсказки площадок.';
    } finally {
        if (requestId === venueSuggestRequestId) {
            venueSuggestLoading.value = false;
        }
    }
};

const applyVenueSuggestion = (suggestion) => {
    createForm.venue_id = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
};

const clearVenueSelection = () => {
    createForm.venue_id = '';
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};

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
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

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

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                    <div v-if="actionError" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ actionError }}
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
                                        {{ event.title }}
                                    </h2>
                                    <div class="mt-1 text-sm text-slate-600">
                                        {{ event.type?.label || 'Тип не задан' }}
                                    </div>
                                </div>
                                <Link
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                    :href="`/events/${event.id}`"
                                >
                                    Открыть
                                </Link>
                            </div>
                            <div class="mt-3 grid gap-2 text-sm text-slate-700 md:grid-cols-2">
                                <div>
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Начало</span>
                                    <div>{{ formatDateTime(event.starts_at) }}</div>
                                </div>
                                <div>
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Окончание</span>
                                    <div>{{ formatDateTime(event.ends_at) }}</div>
                                </div>
                            </div>
                        </article>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <div v-if="createOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: createForm.processing }" @submit.prevent="submitCreate">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Новое событие</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeCreate"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Название (опционально)
                            <input
                                v-model="createForm.title"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="text"
                            />
                        </label>
                        <div v-if="createForm.errors.title" class="text-xs text-rose-700">
                            {{ createForm.errors.title }}
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Тип события
                            <select
                                v-model="createForm.event_type_id"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                @change="handleTypeChange"
                            >
                                <option value="">Выберите тип</option>
                                <option v-for="type in eventTypes" :key="type.id" :value="type.id">
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>
                        <div v-if="createForm.errors.event_type_id" class="text-xs text-rose-700">
                            {{ createForm.errors.event_type_id }}
                        </div>

                        <div v-if="canSelectVenue" class="relative">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Площадка (опционально)
                                <input
                                    v-model="venueQuery"
                                    class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    :class="{ 'is-loading': venueSuggestLoading }"
                                    type="text"
                                    placeholder="Начните вводить название, метро или адрес"
                                    @input="scheduleVenueSuggestions($event.target.value)"
                                />
                            </label>
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
                                    {{ suggestion.label }}
                                </button>
                            </div>
                            <div v-if="createForm.venue_id" class="flex items-center justify-between text-xs text-slate-500">
                                <span>Площадка выбрана.</span>
                                <button
                                    class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                    type="button"
                                    @click="clearVenueSelection"
                                >
                                    Очистить
                                </button>
                            </div>
                            <div v-if="createForm.errors.venue_id" class="text-xs text-rose-700">
                                {{ createForm.errors.venue_id }}
                            </div>
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Дата
                            <input
                                v-model="createForm.date"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="date"
                            />
                        </label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Начало
                                <input
                                    v-model="createForm.starts_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Окончание
                                <input
                                    v-model="createForm.ends_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                        </div>
                        <div v-if="createForm.errors.starts_at || createForm.errors.ends_at" class="text-xs text-rose-700">
                            {{ createForm.errors.starts_at || createForm.errors.ends_at }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="createForm.processing"
                        @click="closeCreate"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="isCreateDisabled"
                    >
                        Создать
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

