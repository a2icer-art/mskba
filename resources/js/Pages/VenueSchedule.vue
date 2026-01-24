<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
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
    venue: {
        type: Object,
        default: null,
    },
    schedule: {
        type: Object,
        default: null,
    },
    weeklyIntervals: {
        type: Object,
        default: () => ({}),
    },
    exceptions: {
        type: Array,
        default: () => [],
    },
    canManage: {
        type: Boolean,
        default: false,
    },
    bookingDates: {
        type: Array,
        default: () => [],
    },
    daysOfWeek: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Площадки', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    activeTypeSlug: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.schedule ?? '');
const isVenueUnavailableForBooking = computed(() => props.venue?.status && props.venue?.status !== 'confirmed');

const intervalOpen = ref(false);
const exceptionOpen = ref(false);
const editIntervalTarget = ref(null);
const editExceptionTarget = ref(null);

const intervalForm = useForm({
    day_of_week: '',
    starts_at: '',
    ends_at: '',
});

const exceptionForm = useForm({
    date: '',
    is_closed: false,
    comment: '',
    intervals: [{ starts_at: '', ends_at: '' }],
});

const readOnlyIntervals = ref([]);
const isReadOnlyException = computed(() => !props.canManage);
const hasExceptionData = computed(() => {
    return Boolean(editExceptionTarget.value || exceptionForm.intervals.length || exceptionForm.is_closed || exceptionForm.comment);
});
const now = new Date();
const todayString = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;
const calendarDate = ref(new Date(now.getFullYear(), now.getMonth(), 1));
const monthNames = [
    'Январь',
    'Февраль',
    'Март',
    'Апрель',
    'Май',
    'Июнь',
    'Июль',
    'Август',
    'Сентябрь',
    'Октябрь',
    'Ноябрь',
    'Декабрь',
];
const weekDayLabels = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
const calendarLabel = computed(() => {
    const current = calendarDate.value;
    return `${monthNames[current.getMonth()]} ${current.getFullYear()}`;
});
const exceptionByDate = computed(() => {
    const map = new Map();
    (props.exceptions ?? []).forEach((exception) => {
        if (exception?.date) {
            map.set(exception.date, exception);
        }
    });
    return map;
});
const visibleExceptions = computed(() => {
    const current = calendarDate.value;
    const year = current.getFullYear();
    const month = current.getMonth() + 1;
    return (props.exceptions ?? []).filter((exception) => {
        if (!exception?.date) {
            return false;
        }
        const parts = exception.date.split('-').map((item) => Number(item));
        return parts[0] === year && parts[1] === month;
    });
});
const closedWeekDays = computed(() => {
    const days = new Set();
    (props.daysOfWeek ?? []).forEach((day) => {
        if (!(props.weeklyIntervals?.[day.value] || []).length) {
            days.add(day.value);
        }
    });
    return days;
});
const bookingDatesSet = computed(() => new Set(props.bookingDates ?? []));
const calendarDays = computed(() => {
    const current = calendarDate.value;
    const year = current.getFullYear();
    const month = current.getMonth();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDay = new Date(year, month, 1);
    const startOffset = (firstDay.getDay() + 6) % 7; // Monday-first
    const items = [];
    for (let i = 0; i < startOffset; i += 1) {
        items.push({ date: null, label: '' });
    }
    for (let day = 1; day <= daysInMonth; day += 1) {
        const date = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayOfWeek = ((new Date(year, month, day).getDay() + 6) % 7) + 1;
        items.push({
            date,
            label: String(day),
            exception: exceptionByDate.value.get(date) || null,
            isClosedByWeek: closedWeekDays.value.has(dayOfWeek),
            isOpenByWeek: !closedWeekDays.value.has(dayOfWeek),
            isToday: date === todayString,
            hasBookings: bookingDatesSet.value.has(date),
        });
    }
    return items;
});
const resetIntervalForm = () => {
    intervalForm.reset('day_of_week', 'starts_at', 'ends_at');
    intervalForm.clearErrors();
    editIntervalTarget.value = null;
};

const resetExceptionForm = () => {
    exceptionForm.reset('date', 'is_closed', 'comment', 'intervals');
    exceptionForm.clearErrors();
    editExceptionTarget.value = null;
    readOnlyIntervals.value = [];
};

const openIntervalModal = (interval = null, day = null) => {
    resetIntervalForm();
    if (interval) {
        editIntervalTarget.value = interval;
        intervalForm.day_of_week = interval.day_of_week;
        intervalForm.starts_at = interval.starts_at;
        intervalForm.ends_at = interval.ends_at;
    } else if (day) {
        intervalForm.day_of_week = day;
    }
    intervalOpen.value = true;
};

const selectedIntervalDayLabel = computed(() => {
    if (!intervalForm.day_of_week) {
        return '—';
    }
    const found = props.daysOfWeek.find((item) => item.value === Number(intervalForm.day_of_week));
    return found?.label || '—';
});

const openExceptionModal = (exception = null) => {
    resetExceptionForm();
    if (exception) {
        editExceptionTarget.value = exception;
        exceptionForm.date = exception.date;
        exceptionForm.is_closed = exception.is_closed;
        exceptionForm.comment = exception.comment || '';
        exceptionForm.intervals = exception.is_closed
            ? []
            : exception.intervals.map((interval) => ({
                starts_at: interval.starts_at,
                ends_at: interval.ends_at,
            }));
        readOnlyIntervals.value = exception.is_closed ? [] : exceptionForm.intervals;
    }
    exceptionOpen.value = true;
};

const openExceptionForDate = (date) => {
    if (!date) {
        return;
    }
    const existing = exceptionByDate.value.get(date);
    if (existing) {
        openExceptionModal(existing);
        return;
    }
    resetExceptionForm();
    exceptionForm.date = date;
    const parts = date.split('-').map((item) => Number(item));
    const dateObj = parts.length === 3 ? new Date(parts[0], parts[1] - 1, parts[2]) : null;
    if (dateObj) {
        const dayOfWeek = ((dateObj.getDay() + 6) % 7) + 1;
        exceptionForm.is_closed = closedWeekDays.value.has(dayOfWeek);
        if (!exceptionForm.is_closed) {
            readOnlyIntervals.value = (props.weeklyIntervals?.[dayOfWeek] || []).map((interval) => ({
                starts_at: interval.starts_at,
                ends_at: interval.ends_at,
            }));
        }
    }
    exceptionForm.intervals = [];
    exceptionOpen.value = true;
};

const shiftCalendarMonth = (offset) => {
    const current = calendarDate.value;
    calendarDate.value = new Date(current.getFullYear(), current.getMonth() + offset, 1);
};

const setCalendarToDate = (date) => {
    if (!date) {
        return;
    }
    const parts = date.split('-').map((item) => Number(item));
    if (parts.length < 2 || Number.isNaN(parts[0]) || Number.isNaN(parts[1])) {
        return;
    }
    calendarDate.value = new Date(parts[0], parts[1] - 1, 1);
};

const closeIntervalModal = () => {
    intervalOpen.value = false;
    resetIntervalForm();
};

const closeExceptionModal = () => {
    exceptionOpen.value = false;
    resetExceptionForm();
};

const addExceptionInterval = () => {
    exceptionForm.intervals.push({ starts_at: '', ends_at: '' });
};

const removeExceptionInterval = (index) => {
    exceptionForm.intervals.splice(index, 1);
};

const getWeeklyIntervalsForDate = (date) => {
    if (!date) {
        return [];
    }
    const parts = date.split('-').map((item) => Number(item));
    if (parts.length !== 3) {
        return [];
    }
    const dateObj = new Date(parts[0], parts[1] - 1, parts[2]);
    const dayOfWeek = ((dateObj.getDay() + 6) % 7) + 1;
    return (props.weeklyIntervals?.[dayOfWeek] || []).map((interval) => ({
        starts_at: interval.starts_at,
        ends_at: interval.ends_at,
    }));
};

watch(
    () => exceptionForm.is_closed,
    (value) => {
        if (value) {
            exceptionForm.intervals = [];
            return;
        }
        if (exceptionForm.intervals.length === 0) {
            const fallbackIntervals = getWeeklyIntervalsForDate(exceptionForm.date);
            exceptionForm.intervals = fallbackIntervals.length ? fallbackIntervals : [{ starts_at: '', ends_at: '' }];
        }
    }
);

const submitInterval = () => {
    const baseUrl = `/venues/${props.activeTypeSlug}/${props.venue?.alias}/schedule/intervals`;
    if (editIntervalTarget.value) {
        intervalForm.patch(`${baseUrl}/${editIntervalTarget.value.id}`, {
            preserveScroll: true,
            onSuccess: closeIntervalModal,
        });
        return;
    }
    intervalForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: closeIntervalModal,
    });
};

const submitException = () => {
    const baseUrl = `/venues/${props.activeTypeSlug}/${props.venue?.alias}/schedule/exceptions`;
    if (editExceptionTarget.value) {
        exceptionForm.patch(`${baseUrl}/${editExceptionTarget.value.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setCalendarToDate(exceptionForm.date);
                closeExceptionModal();
            },
        });
        return;
    }
    exceptionForm.post(baseUrl, {
        preserveScroll: true,
        onSuccess: () => {
            setCalendarToDate(exceptionForm.date);
            closeExceptionModal();
        },
    });
};

const intervalDeleteForm = useForm({});
const exceptionDeleteForm = useForm({});

const selectedDay = ref(null);
const showDayBookingForm = ref(false);
const dayLoading = ref(false);
const dayError = ref('');
const createPrefill = ref({});
const embeddedCreateRef = ref(null);
const bookingFormRef = ref(null);
const popupBodyRef = ref(null);

const removeInterval = (intervalId) => {
    intervalDeleteForm.delete(
        `/venues/${props.activeTypeSlug}/${props.venue?.alias}/schedule/intervals/${intervalId}`,
        { preserveScroll: true }
    );
};

const removeException = (exceptionId) => {
    exceptionDeleteForm.delete(
        `/venues/${props.activeTypeSlug}/${props.venue?.alias}/schedule/exceptions/${exceptionId}`,
        { preserveScroll: true }
    );
};

const openDayDetails = (day) => {
    if (!day?.date) {
        return;
    }
    selectedDay.value = {
        date: day.date,
        is_today: day.isToday,
        is_closed: false,
        is_closed_by_exception: false,
        intervals: [],
        bookings: [],
        comment: null,
    };
    dayLoading.value = true;
    dayError.value = '';
    showDayBookingForm.value = false;
    fetchDayDetails(day.date);
};

const closeDayDetails = () => {
    selectedDay.value = null;
    showDayBookingForm.value = false;
    createPrefill.value = {};
    dayLoading.value = false;
    dayError.value = '';
};

const closeDayBookingForm = () => {
    showDayBookingForm.value = false;
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

const refreshDayBookings = async () => {
    if (!selectedDay.value?.date || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    try {
        const params = new URLSearchParams({ date: selectedDay.value.date });
        const response = await fetch(
            `/venues/${props.activeTypeSlug}/${props.venue.alias}/schedule-day-bookings?${params.toString()}`
        );
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
    if (!date || !props.venue?.alias || !props.activeTypeSlug) {
        dayLoading.value = false;
        return;
    }
    try {
        const params = new URLSearchParams({ date });
        const response = await fetch(
            `/venues/${props.activeTypeSlug}/${props.venue.alias}/schedule-day?${params.toString()}`
        );
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
            bookings: data?.bookings ?? [],
            comment: data?.comment ?? null,
        };
    } catch (error) {
        dayError.value = 'Не удалось загрузить данные дня.';
    } finally {
        dayLoading.value = false;
    }
};

const openBookingFromDay = () => {
    if (!selectedDay.value) {
        return;
    }
    createPrefill.value = {
        venue: props.venue?.alias || '',
        date: selectedDay.value.date || '',
    };
    showDayBookingForm.value = true;
    nextTick(() => {
        scrollPopupToBookingForm();
    });
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

const isEmbeddedSubmitDisabled = computed(() => embeddedCreateRef.value?.isDisabled ?? true);
const formatDayLabel = (date) =>
    new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' }).format(new Date(date));

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

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Расписание</h1>
                        </div>
                    </div>

                    

                    <section v-if="canManage" class="mt-6">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Недельное расписание</h2>
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">
                                {{ schedule?.timezone || 'UTC+3' }}
                            </span>
                        </div>

                        <div class="mt-4 grid gap-3">
                            <div
                                v-for="day in daysOfWeek"
                                :key="day.value"
                                class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                            >
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">{{ day.label }}</p>
                                        <div class="mt-2 flex flex-wrap gap-2">
                                            <span
                                                v-for="interval in weeklyIntervals[day.value] || []"
                                                :key="interval.id"
                                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700"
                                            >
                                                {{ interval.starts_at }} – {{ interval.ends_at }}
                                            </span>
                                            <span
                                                v-if="(weeklyIntervals[day.value] || []).length === 0"
                                                class="text-xs text-slate-500"
                                            >
                                                Интервалы не заданы
                                            </span>
                                        </div>
                                    </div>
                                    <div v-if="canManage" class="flex flex-wrap gap-2">
                                        <template v-if="(weeklyIntervals[day.value] || []).length">
                                            <button
                                                v-for="interval in weeklyIntervals[day.value]"
                                                :key="`edit-${interval.id}`"
                                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                                type="button"
                                                @click="openIntervalModal(interval)"
                                            >
                                                Редактировать {{ interval.starts_at }}
                                            </button>
                                            <button
                                                v-for="interval in weeklyIntervals[day.value]"
                                                :key="`delete-${interval.id}`"
                                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5"
                                                type="button"
                                                :disabled="intervalDeleteForm.processing"
                                                @click="removeInterval(interval.id)"
                                            >
                                                Удалить
                                            </button>
                                        </template>
                                        <button
                                            v-else
                                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                            type="button"
                                            @click="openIntervalModal(null, day.value)"
                                        >
                                            Добавить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div class="text-sm font-semibold text-slate-900">{{ calendarLabel }}</div>
                                <div class="flex items-center gap-2">
                                    <button
                                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                        type="button"
                                        @click="shiftCalendarMonth(-1)"
                                    >
                                        Пред
                                    </button>
                                    <button
                                        class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                        type="button"
                                        @click="shiftCalendarMonth(1)"
                                    >
                                        След
                                    </button>
                                </div>
                            </div>
                            <div class="mt-4 grid grid-cols-7 gap-2 text-xs text-slate-500">
                                <div v-for="label in weekDayLabels" :key="label" class="text-center font-semibold">
                                    {{ label }}
                                </div>
                            </div>
                            <div class="mt-2 grid grid-cols-7 gap-2">
                                <button
                                    v-for="(day, index) in calendarDays"
                                    :key="`${day.date || 'empty'}-${index}`"
                                    class="relative h-10 rounded-xl border border-slate-100 text-sm font-semibold text-slate-700 transition"
                                    :class="[
                                        day.date ? 'bg-white hover:border-slate-300' : 'bg-slate-50 text-slate-300',
                                        day.exception?.is_closed ? 'border-rose-200 bg-rose-50 text-rose-700' : '',
                                        day.exception && !day.exception?.is_closed ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : '',
                                        !day.exception && day.isClosedByWeek ? 'border-slate-200 bg-slate-50 text-slate-500' : '',
                                        !day.exception && day.isOpenByWeek ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : '',
                                        day.isToday ? 'border-emerald-500 ring-2 ring-emerald-200' : '',
                                    ]"
                                    type="button"
                                    :disabled="!day.date || (!day.exception && day.isClosedByWeek && !canManage)"
                                    @click="canManage ? openExceptionForDate(day.date) : openDayDetails(day)"
                                >
                                    {{ day.label }}
                                    <span
                                        v-if="day.exception || day.isClosedByWeek || day.isOpenByWeek"
                                        class="absolute right-1.5 top-1.5 h-2 w-2 rounded-full"
                                        :class="day.exception?.is_closed
                                            ? 'bg-rose-500'
                                            : day.exception
                                                ? 'bg-emerald-500'
                                                : day.isOpenByWeek
                                                    ? 'bg-emerald-500'
                                                    : 'bg-slate-400'"
                                    ></span>
                                    <span
                                        v-if="day.hasBookings"
                                        class="absolute right-1.5 top-4 h-2 w-2 rounded-full bg-violet-500"
                                    ></span>
                                </button>
                            </div>
                            <p class="mt-3 text-xs text-slate-500">
                                {{
                                    canManage
                                        ? 'Кликните по дате, чтобы добавить или отредактировать исключение.'
                                        : 'Кликните по дате, чтобы посмотреть детали и забронировать.'
                                }}
                            </p>
                            <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600">
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                    Открыто
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                    Есть бронирования
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                    Нет интервалов
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                    Закрыто
                                </div>
                            </div>
                        </div>

                        <template v-if="canManage && visibleExceptions.length">
                            <div class="mt-4 grid gap-3">
                                <div
                                    v-for="exception in visibleExceptions"
                                    :key="exception.id"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                >
                                    <div class="flex flex-wrap items-start justify-between gap-4">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">{{ exception.date }}</p>
                                            <p class="mt-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                                {{ exception.is_closed ? 'Закрыто' : 'Открыто' }}
                                            </p>
                                            <div v-if="exception.comment" class="mt-2 text-sm text-slate-600">
                                                {{ exception.comment }}
                                            </div>
                                            <div v-if="!exception.is_closed" class="mt-2 flex flex-wrap gap-2">
                                                <span
                                                    v-for="interval in exception.intervals"
                                                    :key="interval.id"
                                                    class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-700"
                                                >
                                                    {{ interval.starts_at }} – {{ interval.ends_at }}
                                                </span>
                                                <span v-if="!exception.intervals.length" class="text-xs text-slate-500">
                                                    Интервалы не заданы
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex flex-wrap gap-2">
                                            <button
                                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5"
                                                type="button"
                                                @click="openExceptionModal(exception)"
                                            >
                                                Редактировать
                                            </button>
                                            <button
                                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5"
                                                type="button"
                                                :disabled="exceptionDeleteForm.processing"
                                                @click="removeException(exception.id)"
                                            >
                                                Удалить
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </section>

                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <div v-if="selectedDay" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeDayDetails">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-slate-900">Расписание на день</h2>
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
                <div v-if="dayError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ dayError }}
                </div>
                <div v-else-if="dayLoading" class="flex min-h-[180px] items-center justify-center rounded-2xl border border-slate-200 bg-slate-50">
                    <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
                </div>
                <template v-else>
                    <div v-if="selectedDay.is_closed" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
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
                        <div v-if="selectedDay.intervals?.length" class="mt-2 space-y-1">
                            <div v-for="(interval, index) in selectedDay.intervals" :key="index">
                                {{ interval.starts_at }}–{{ interval.ends_at }}
                            </div>
                        </div>
                        <p v-else class="mt-2 text-slate-500">Интервалы не заданы.</p>
                    </div>

                    <div v-if="selectedDay.is_closed">
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <p v-if="!selectedDay.is_closed_by_exception" class="text-sm text-slate-600">Интервалы не заданы.</p>
                            <p v-if="selectedDay.comment" class="mt-2 text-sm text-slate-700">{{ selectedDay.comment }}</p>
                        </div>
                    </div>
                    <div v-else-if="!isVenueUnavailableForBooking" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Занятые интервалы</p>
                        <div v-if="selectedDay.bookings?.length" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="(booking, index) in selectedDay.bookings"
                                :key="index"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700"
                            >
                                {{ booking.starts_at }}–{{ booking.ends_at }}
                            </span>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500">Бронирований нет.</p>
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
            </div>
            <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                <button
                    v-if="dayLoading"
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeDayDetails"
                >
                    Закрыть
                </button>
                <button
                    v-if="selectedDay.is_closed"
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeDayDetails"
                >
                    Закрыть
                </button>
                <button
                    v-if="!dayLoading && !selectedDay.is_closed && isVenueUnavailableForBooking"
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeDayDetails"
                >
                    Закрыть
                </button>
                <button
                    v-if="showDayBookingForm && !selectedDay.is_closed"
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeDayBookingForm"
                >
                    Отмена
                </button>
                <button
                    v-if="!selectedDay.is_closed && !dayLoading && !isVenueUnavailableForBooking"
                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                    type="button"
                    :disabled="showDayBookingForm ? isEmbeddedSubmitDisabled : false"
                    @click="showDayBookingForm ? submitEmbeddedBooking() : openBookingFromDay()"
                >
                    {{ showDayBookingForm ? 'Подтвердить' : 'Забронировать' }}
                </button>
            </div>
        </div>
    </div>

    <div v-if="intervalOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: intervalForm.processing }" @submit.prevent="submitInterval">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ editIntervalTarget ? 'Редактировать интервал' : 'Новый интервал' }}
                    </h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeIntervalModal"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">День недели</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ selectedIntervalDayLabel }}</p>
                            <input v-model="intervalForm.day_of_week" type="hidden" />
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Начало
                                <input
                                    v-model="intervalForm.starts_at"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Окончание
                                <input
                                    v-model="intervalForm.ends_at"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                        </div>
                        <div v-if="intervalForm.errors.starts_at || intervalForm.errors.ends_at" class="text-xs text-rose-700">
                            {{ intervalForm.errors.starts_at || intervalForm.errors.ends_at }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="intervalForm.processing"
                        @click="closeIntervalModal"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="intervalForm.processing"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="exceptionOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: exceptionForm.processing }" @submit.prevent="submitException">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Расписание на день
                    </h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeExceptionModal"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div v-if="!canManage" class="grid gap-3">
                        <div>
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата</p>
                            <p class="mt-2 text-sm font-semibold text-slate-900">{{ exceptionForm.date || '—' }}</p>
                        </div>
                        <div v-if="exceptionForm.is_closed">
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Статус</p>
                            <p class="mt-2 text-sm font-semibold text-rose-700">
                                Закрыто
                            </p>
                        </div>
                        <div v-if="exceptionForm.comment">
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</p>
                            <p class="mt-2 text-sm text-slate-700">{{ exceptionForm.comment }}</p>
                        </div>
                        <div v-if="!exceptionForm.is_closed">
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">График работы</p>
                            <div v-if="readOnlyIntervals.length" class="mt-2 flex flex-wrap gap-2">
                                <span
                                    v-for="(interval, index) in readOnlyIntervals"
                                    :key="index"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700"
                                >
                                    {{ interval.starts_at }} – {{ interval.ends_at }}
                                </span>
                            </div>
                            <p v-else class="mt-2 text-sm text-slate-500">График работы не задан.</p>
                        </div>
                    </div>
                    <div v-else class="grid gap-3">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Дата
                            <input
                                v-model="exceptionForm.date"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="date"
                            />
                        </label>
                        <div v-if="exceptionForm.errors.date" class="text-xs text-rose-700">
                            {{ exceptionForm.errors.date }}
                        </div>

                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input
                                v-model="exceptionForm.is_closed"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-slate-900"
                            />
                            Закрыть площадку на эту дату
                        </label>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий (опционально)
                            <textarea
                                v-model="exceptionForm.comment"
                                class="min-h-[80px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            ></textarea>
                        </label>
                        <div v-if="exceptionForm.errors.comment" class="text-xs text-rose-700">
                            {{ exceptionForm.errors.comment }}
                        </div>

                        <div v-if="!exceptionForm.is_closed" class="grid gap-3">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Интервалы</span>
                                <button
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700"
                                    type="button"
                                    @click="addExceptionInterval"
                                >
                                    Добавить интервал
                                </button>
                            </div>
                            <div v-for="(interval, index) in exceptionForm.intervals" :key="index" class="grid gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-3">
                                <div class="grid gap-3 md:grid-cols-2">
                                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                        Начало
                                        <input
                                            v-model="interval.starts_at"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                            type="time"
                                        />
                                    </label>
                                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                        Окончание
                                        <input
                                            v-model="interval.ends_at"
                                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                            type="time"
                                        />
                                    </label>
                                </div>
                                <button
                                    v-if="exceptionForm.intervals.length > 1"
                                    class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700"
                                    type="button"
                                    @click="removeExceptionInterval(index)"
                                >
                                    Удалить интервал
                                </button>
                            </div>
                            <div v-if="exceptionForm.errors.intervals" class="text-xs text-rose-700">
                                {{ exceptionForm.errors.intervals }}
                            </div>
                        </div>
                        <div v-else-if="!hasExceptionData" class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-3 text-sm text-slate-500">
                            Исключение не задано.
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="exceptionForm.processing"
                        @click="closeExceptionModal"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="canManage"
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="exceptionForm.processing"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
