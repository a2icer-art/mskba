<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
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
    bookings: {
        type: Array,
        default: () => [],
    },
    canBook: {
        type: Boolean,
        default: false,
    },
    bookingDeadlinePassed: {
        type: Boolean,
        default: false,
    },
    canDelete: {
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
const actionError = computed(() => page.props?.errors?.booking ?? '');
const hasBookings = computed(() => props.bookings.length > 0);
const hasApprovedBooking = computed(() => props.bookings.some((booking) => booking.status === 'approved'));
const hasCancelledBooking = computed(() => props.bookings.some((booking) => booking.status === 'cancelled'));
const bookingStatusLabel = (status) => {
    if (status === 'approved') {
        return 'Подтверждено';
    }
    if (status === 'cancelled') {
        return 'Отменено';
    }
    return 'Ожидает';
};

const bookingOpen = ref(false);
const deleteOpen = ref(false);
const bookingForm = useForm({
    venue_id: '',
    date: '',
    starts_time: '',
    ends_time: '',
    starts_at: '',
    ends_at: '',
});
const venueQuery = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;

const openBooking = () => {
    bookingForm.clearErrors();
    if (!bookingForm.date && props.event?.starts_at) {
        bookingForm.date = toLocalDate(props.event.starts_at);
    }
    if (!bookingForm.starts_time && props.event?.starts_at) {
        bookingForm.starts_time = toLocalTime(props.event.starts_at);
    }
    if (!bookingForm.ends_time && props.event?.ends_at) {
        bookingForm.ends_time = toLocalTime(props.event.ends_at);
    }
    bookingOpen.value = true;
};

const closeBooking = () => {
    bookingForm.reset('venue_id', 'date', 'starts_time', 'ends_time', 'starts_at', 'ends_at');
    bookingForm.clearErrors();
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    bookingOpen.value = false;
};

const submitBooking = () => {
    bookingForm.starts_at = combineDateTime(bookingForm.date, bookingForm.starts_time);
    bookingForm.ends_at = combineDateTime(bookingForm.date, bookingForm.ends_time);
    bookingForm.post(`/events/${props.event?.id}/bookings`, {
        preserveScroll: true,
        onSuccess: closeBooking,
    });
};

const openDelete = () => {
    deleteOpen.value = true;
};

const closeDelete = () => {
    deleteOpen.value = false;
};

const deleteForm = useForm({});

const submitDelete = () => {
    deleteForm.delete(`/events/${props.event?.id}`, {
        preserveScroll: true,
        onSuccess: closeDelete,
    });
};

const isBookingDisabled = computed(() => {
    if (bookingForm.processing) {
        return true;
    }
    return !bookingForm.venue_id || !bookingForm.date || !bookingForm.starts_time || !bookingForm.ends_time;
});

const scheduleVenueSuggestions = (value) => {
    bookingForm.venue_id = '';
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
    bookingForm.venue_id = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
};

const clearVenueSelection = () => {
    bookingForm.venue_id = '';
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

const toLocalDate = (value) => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const toLocalTime = (value) => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (number) => String(number).padStart(2, '0');
    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const combineDateTime = (date, time) => {
    if (!date || !time) {
        return '';
    }
    return `${date}T${time}`;
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
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1">
                            <h1 class="text-3xl font-semibold text-slate-900">
                                {{ event?.title || 'Событие' }}
                            </h1>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ event?.type?.label || 'Тип не задан' }}
                            </p>
                        </div>
                        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                            <button
                                v-if="canDelete"
                                class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                type="button"
                                @click="openDelete"
                            >
                                Удалить событие
                            </button>
                            <button
                                v-if="canBook && !hasApprovedBooking && !bookingDeadlinePassed"
                                class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700"
                                type="button"
                                @click="openBooking"
                            >
                                Забронировать площадку
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Время</span>
                        <div class="mt-1">{{ formatDateRange(event?.starts_at, event?.ends_at) }}</div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.15em]">
                        <span
                            v-if="hasApprovedBooking"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700"
                        >
                            Есть подтвержденное бронирование
                        </span>
                        <span
                            v-if="hasCancelledBooking"
                            class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700"
                        >
                            Есть отмененные бронирования
                        </span>
                    </div>

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                    <div v-if="actionError" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ actionError }}
                    </div>

                    <section class="mt-8">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Бронирования</h2>
                        </div>

                        <div v-if="!hasBookings" class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                            Бронирования пока не созданы.
                        </div>
                        <div v-else class="mt-4 grid gap-3">
                            <div
                                v-for="booking in bookings"
                                :key="booking.id"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ booking.venue?.name || 'Площадка' }}
                                        </p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                            {{ bookingStatusLabel(booking.status) }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-slate-700">
                                        {{ formatDateRange(booking.starts_at, booking.ends_at) }}
                                    </div>
                                </div>
                                <div v-if="booking.moderation_comment" class="mt-3 text-sm text-slate-700">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                                    <p class="mt-1">{{ booking.moderation_comment }}</p>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <div v-if="bookingOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: bookingForm.processing }" @submit.prevent="submitBooking">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Новое бронирование</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeBooking"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <div class="relative">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Площадка
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
                            <div v-if="bookingForm.venue_id" class="flex items-center justify-between text-xs text-slate-500">
                                <span>Площадка выбрана.</span>
                                <button
                                    class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                    type="button"
                                    @click="clearVenueSelection"
                                >
                                    Очистить
                                </button>
                            </div>
                            <div v-if="bookingForm.errors.venue_id" class="text-xs text-rose-700">
                                {{ bookingForm.errors.venue_id }}
                            </div>
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Дата
                            <input
                                v-model="bookingForm.date"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="date"
                            />
                        </label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Начало
                                <input
                                    v-model="bookingForm.starts_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Окончание
                                <input
                                    v-model="bookingForm.ends_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                        </div>
                        <div v-if="bookingForm.errors.starts_at || bookingForm.errors.ends_at" class="text-xs text-rose-700">
                            {{ bookingForm.errors.starts_at || bookingForm.errors.ends_at }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="bookingForm.processing"
                        @click="closeBooking"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="isBookingDisabled"
                    >
                        Создать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: deleteForm.processing }" @submit.prevent="submitDelete">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Удалить событие</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeDelete"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        Вы уверены, что хотите удалить событие «{{ event?.title || 'Событие' }}»?
                    </p>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="deleteForm.processing"
                        @click="closeDelete"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-rose-500 bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-600 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="deleteForm.processing"
                    >
                        Удалить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
