<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import EventCreateModal from './EventCreateModal.vue';

const props = defineProps({
    modelValue: {
        type: Boolean,
        default: false,
    },
    venueAlias: {
        type: String,
        default: '',
    },
    activeTypeSlug: {
        type: String,
        default: '',
    },
    initialDate: {
        type: String,
        default: '',
    },
    allowDatePick: {
        type: Boolean,
        default: false,
    },
    isVenueUnavailableForBooking: {
        type: Boolean,
        default: false,
    },
    emptyVenueMessage: {
        type: String,
        default: 'Выберите площадку для бронирования.',
    },
});

const emit = defineEmits(['update:modelValue']);

const selectedDay = ref(null);
const dayLoading = ref(false);
const dayError = ref('');
const showDayBookingForm = ref(false);
const dayPickerValue = ref('');
const createPrefill = ref({});
const embeddedCreateRef = ref(null);
const bookingFormRef = ref(null);
const popupBodyRef = ref(null);

const selectedVenue = ref(null);
const venueQuery = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;

const hasVenueSelection = computed(() => Boolean(props.venueAlias || selectedVenue.value?.id));
const scheduleDayPath = computed(() => {
    if (props.venueAlias && props.activeTypeSlug) {
        return `/venues/${props.activeTypeSlug}/${props.venueAlias}/schedule-day`;
    }
    if (selectedVenue.value?.id) {
        return `/venues/schedule-day/${selectedVenue.value.id}`;
    }
    return '';
});
const scheduleDayBookingsPath = computed(() => {
    if (props.venueAlias && props.activeTypeSlug) {
        return `/venues/${props.activeTypeSlug}/${props.venueAlias}/schedule-day-bookings`;
    }
    if (selectedVenue.value?.id) {
        return `/venues/schedule-day-bookings/${selectedVenue.value.id}`;
    }
    return '';
});
const canLoadDay = computed(() => Boolean(scheduleDayPath.value));
const shouldShowDatePicker = computed(() => props.allowDatePick && hasVenueSelection.value);
const canShowBookingActions = computed(
    () => canLoadDay.value && !props.isVenueUnavailableForBooking && selectedDay.value?.has_intervals
);
const isEmbeddedSubmitDisabled = computed(() => embeddedCreateRef.value?.isDisabled ?? true);

const formatDayLabel = (date) =>
    new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' }).format(new Date(date));

const resetState = () => {
    selectedDay.value = null;
    dayLoading.value = false;
    dayError.value = '';
    showDayBookingForm.value = false;
    dayPickerValue.value = '';
    createPrefill.value = {};
    selectedVenue.value = null;
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    venueSuggestLoading.value = false;
};

const closeDayDetails = () => {
    emit('update:modelValue', false);
};

const initSelectedDay = (date) => ({
    date,
    is_today: false,
    is_closed: false,
    is_closed_by_exception: false,
    intervals: [],
    has_intervals: false,
    bookings: [],
    comment: null,
});

const refreshDayBookings = async () => {
    if (!selectedDay.value?.date || !canLoadDay.value) {
        return;
    }
    try {
        const params = new URLSearchParams({ date: selectedDay.value.date });
        const response = await fetch(`${scheduleDayBookingsPath.value}?${params.toString()}`);
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        selectedDay.value.bookings = data?.bookings ?? [];
    } catch (error) {
        return;
    }
};

const fetchDayDetails = async (date) => {
    if (!date) {
        dayLoading.value = false;
        return;
    }
    if (!canLoadDay.value) {
        dayLoading.value = false;
        return;
    }
    try {
        const params = new URLSearchParams({ date });
        const response = await fetch(`${scheduleDayPath.value}?${params.toString()}`);
        if (!response.ok) {
            throw new Error('day_failed');
        }
        const data = await response.json();
        selectedDay.value = {
            date,
            is_today: data?.is_today ?? false,
            is_closed: data?.is_closed ?? false,
            is_closed_by_exception: data?.is_closed_by_exception ?? false,
            intervals: data?.intervals ?? [],
            has_intervals: data?.has_intervals ?? (data?.intervals ?? []).length > 0,
            bookings: data?.bookings ?? [],
            comment: data?.comment ?? null,
        };
    } catch (error) {
        dayError.value = 'Не удалось загрузить данные дня.';
    } finally {
        dayLoading.value = false;
    }
};

const scrollPopupToBookingForm = () => {
    const container = popupBodyRef.value;
    const target = bookingFormRef.value;
    if (!container || !target) {
        return;
    }
    const offset = Math.max(target.offsetTop - container.offsetTop - 8, 0);
    container.scrollTo({ top: offset, behavior: 'smooth' });
};

const openBookingFromDay = () => {
    if (!selectedDay.value || !canShowBookingActions.value) {
        return;
    }
    createPrefill.value = {
        venue: props.venueAlias || selectedVenue.value?.alias || '',
        date: selectedDay.value.date || '',
    };
    showDayBookingForm.value = true;
    nextTick(() => {
        scrollPopupToBookingForm();
    });
};

const closeDayBookingForm = () => {
    showDayBookingForm.value = false;
};

const handleBookingFormLoaded = () => {
    nextTick(() => {
        embeddedCreateRef.value?.focusType?.();
    });
};

const submitEmbeddedBooking = () => {
    if (!embeddedCreateRef.value || embeddedCreateRef.value.isDisabled) {
        return;
    }
    embeddedCreateRef.value.submit();
};

const scheduleVenueSuggestions = (value) => {
    selectedVenue.value = null;
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
    selectedVenue.value = suggestion;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    if (selectedDay.value?.date) {
        dayLoading.value = true;
        dayError.value = '';
        fetchDayDetails(selectedDay.value.date);
    }
};

const bootstrapPopup = () => {
    const today = new Date();
    const todayString = today.toISOString().slice(0, 10);
    const date = props.initialDate || (props.allowDatePick ? todayString : '');
    dayError.value = '';
    showDayBookingForm.value = false;
    createPrefill.value = {};
    if (props.allowDatePick && hasVenueSelection.value) {
        dayPickerValue.value = date || '';
    }
    if (!date || (props.allowDatePick && !hasVenueSelection.value)) {
        selectedDay.value = null;
        return;
    }
    selectedDay.value = initSelectedDay(date);
    dayLoading.value = true;
    fetchDayDetails(date);
};

watch(
    () => props.modelValue,
    (value) => {
        if (!value) {
            resetState();
            return;
        }
        if (props.venueAlias) {
            selectedVenue.value = null;
            venueQuery.value = '';
            venueSuggestions.value = [];
            venueSuggestError.value = '';
        }
        bootstrapPopup();
    }
);

watch(
    () => props.initialDate,
    (value) => {
        if (!props.modelValue || props.allowDatePick) {
            return;
        }
        if (!value) {
            return;
        }
        selectedDay.value = initSelectedDay(value);
        dayLoading.value = true;
        dayError.value = '';
        showDayBookingForm.value = false;
        fetchDayDetails(value);
    }
);

watch(
    () => canLoadDay.value,
    (value) => {
        if (!props.modelValue || !value || !selectedDay.value?.date) {
            return;
        }
        dayLoading.value = true;
        dayError.value = '';
        fetchDayDetails(selectedDay.value.date);
    }
);

watch(
    () => dayPickerValue.value,
    (value) => {
        if (!props.modelValue || !props.allowDatePick || !hasVenueSelection.value) {
            return;
        }
        if (!value) {
            return;
        }
        if (selectedDay.value?.date === value) {
            return;
        }
        selectedDay.value = initSelectedDay(value);
        dayLoading.value = true;
        dayError.value = '';
        showDayBookingForm.value = false;
        fetchDayDetails(value);
    }
);

watch(
    () => hasVenueSelection.value,
    (value) => {
        if (!props.modelValue || !props.allowDatePick || !value) {
            return;
        }
        if (!dayPickerValue.value) {
            const today = new Date();
            dayPickerValue.value = today.toISOString().slice(0, 10);
        }
        if (!selectedDay.value && dayPickerValue.value) {
            selectedDay.value = initSelectedDay(dayPickerValue.value);
            dayLoading.value = true;
            dayError.value = '';
            showDayBookingForm.value = false;
            fetchDayDetails(dayPickerValue.value);
        }
    }
);
</script>

<template>
    <div v-if="modelValue" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeDayDetails">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Новое событие</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        {{ selectedDay?.date ? formatDayLabel(selectedDay.date) : '—' }}
                    </p>
                </div>
                <button
                    class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                    type="button"
                    aria-label="Закрыть"
                    @click="closeDayDetails"
                >
                    x
                </button>
            </div>
            <div ref="popupBodyRef" class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4" :class="{ loading: dayLoading }">
                <div v-if="!props.venueAlias" class="mb-4">
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
                    <div v-if="venueSuggestError" class="text-xs text-rose-700">
                        {{ venueSuggestError }}
                    </div>
                    <div
                        v-else-if="!venueSuggestLoading && venueSuggestions.length"
                        class="absolute left-0 right-0 z-10 mt-2 rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
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
                    </div>
                </div>
                <div v-if="shouldShowDatePicker && !showDayBookingForm" class="mb-4">
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Дата
                        <input
                            v-model="dayPickerValue"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="date"
                        />
                    </label>
                </div>
                <div v-if="dayError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ dayError }}
                </div>
                <div v-else-if="dayLoading" class="flex min-h-[180px] items-center justify-center rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
                </div>
                <template v-else>
                    <div
                        v-if="!canLoadDay && !hasVenueSelection"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
                    >
                        {{ emptyVenueMessage }}
                    </div>
                    <div
                        v-else-if="!canLoadDay && hasVenueSelection"
                        class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600"
                    >
                        Загружаем данные площадки...
                    </div>
                    <template v-else>
                    <div v-if="selectedDay?.is_closed" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        Площадка закрыта.
                    </div>
                    <div
                        v-else-if="isVenueUnavailableForBooking"
                        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    >
                        Бронирование недоступно для неподтвержденной площадки.
                    </div>
                    <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-normal text-slate-600">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">График работы</p>
                        <div v-if="selectedDay?.intervals?.length" class="mt-2 space-y-1">
                            <div v-for="(interval, index) in selectedDay.intervals" :key="index">
                                {{ interval.starts_at }}–{{ interval.ends_at }}
                            </div>
                        </div>
                        <p v-else class="mt-2 text-slate-500">Интервалы не заданы.</p>
                    </div>

                    <div v-if="selectedDay?.is_closed && selectedDay.comment" class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                        {{ selectedDay.comment }}
                    </div>
                    <div
                        v-else-if="selectedDay && !isVenueUnavailableForBooking && selectedDay.bookings?.length"
                        class="mt-4"
                    >
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Занятые интервалы</p>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="(booking, index) in selectedDay.bookings"
                                :key="index"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700"
                            >
                                {{ booking.starts_at }}–{{ booking.ends_at }}
                            </span>
                        </div>
                    </div>

                    <div v-if="showDayBookingForm" ref="bookingFormRef" class="mt-6">
                        <hr class="my-4 border-slate-200/80" />
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Забронировать</p>
                        <div class="mt-2"></div>
                        <EventCreateModal
                            ref="embeddedCreateRef"
                            embedded
                            :prefill="createPrefill"
                            :can-book-fallback="false"
                            :hide-submit="true"
                            @close="closeDayDetails"
                            @loaded="handleBookingFormLoaded"
                            @booking-conflict="refreshDayBookings"
                        />
                    </div>
                    </template>
                </template>
            </div>
            <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                <button
                    v-if="showDayBookingForm && !selectedDay?.is_closed"
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeDayBookingForm"
                >
                    Отмена
                </button>
                <button
                    v-if="selectedDay && !dayLoading && canLoadDay"
                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                    type="button"
                    :disabled="showDayBookingForm ? isEmbeddedSubmitDisabled : !canShowBookingActions"
                    @click="showDayBookingForm ? submitEmbeddedBooking() : openBookingFromDay()"
                >
                    {{ showDayBookingForm ? 'Подтвердить' : 'Забронировать' }}
                </button>
            </div>
        </div>
    </div>
</template>
