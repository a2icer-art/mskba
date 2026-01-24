<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
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
    settings: {
        type: Object,
        default: () => ({}),
    },
    paymentOrderOptions: {
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

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const localNotice = ref('');
const successNotice = computed(() => actionNotice.value || localNotice.value);
const actionError = computed(() => page.props?.errors ?? {});
const formErrorNotice = computed(() => {
    if (!actionError.value || !Object.keys(actionError.value).length) {
        return '';
    }
    return 'Не удалось сохранить изменения. Проверьте значения.';
});

const isMinutes = ref(false);
const rentalDurationValue = ref(1);
const initialRentalMinutes = props.settings?.rental_duration_minutes ?? 60;
if (initialRentalMinutes % 60 === 0) {
    isMinutes.value = false;
    rentalDurationValue.value = initialRentalMinutes / 60;
} else {
    isMinutes.value = true;
    rentalDurationValue.value = initialRentalMinutes;
}
const rentalDurationMax = computed(() => (isMinutes.value ? 1440 : 24));
const rentalDurationStep = computed(() => (isMinutes.value ? 1 : 0.25));
const canUseRentalHours = computed(() => {
    const current = Number(rentalDurationValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isMinutes.value ? current : current * 60;
    return minutes >= 60;
});


const isPaymentWaitMinutes = ref(false);
const paymentWaitValue = ref(1);
const initialPaymentWait = props.settings?.payment_wait_minutes ?? 60;
if (initialPaymentWait === 0) {
    isPaymentWaitMinutes.value = false;
    paymentWaitValue.value = 0;
} else if (initialPaymentWait % 60 === 0) {
    isPaymentWaitMinutes.value = false;
    paymentWaitValue.value = initialPaymentWait / 60;
} else {
    isPaymentWaitMinutes.value = true;
    paymentWaitValue.value = initialPaymentWait;
}
const paymentWaitMax = computed(() => (isPaymentWaitMinutes.value ? 10080 : 168));
const paymentWaitStep = computed(() => (isPaymentWaitMinutes.value ? 1 : 0.25));
const canUsePaymentWaitHours = computed(() => {
    const current = Number(paymentWaitValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPaymentWaitMinutes.value ? current : current * 60;
    return minutes >= 60;
});


const isPendingReviewMinutes = ref(false);
const pendingReviewValue = ref(1);
const initialPendingReview = props.settings?.pending_review_minutes ?? 120;
if (initialPendingReview === 0) {
    isPendingReviewMinutes.value = false;
    pendingReviewValue.value = 0;
} else if (initialPendingReview % 60 === 0) {
    isPendingReviewMinutes.value = false;
    pendingReviewValue.value = initialPendingReview / 60;
} else {
    isPendingReviewMinutes.value = true;
    pendingReviewValue.value = initialPendingReview;
}
const pendingReviewMax = computed(() => (isPendingReviewMinutes.value ? 10080 : 168));
const pendingReviewStep = computed(() => (isPendingReviewMinutes.value ? 1 : 0.25));
const canUsePendingReviewHours = computed(() => {
    const current = Number(pendingReviewValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingReviewMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isPendingBeforeStartMinutes = ref(false);
const pendingBeforeStartValue = ref(1);
const initialPendingBeforeStart = props.settings?.pending_before_start_minutes ?? 120;
if (initialPendingBeforeStart === 0) {
    isPendingBeforeStartMinutes.value = false;
    pendingBeforeStartValue.value = 0;
} else if (initialPendingBeforeStart % 60 === 0) {
    isPendingBeforeStartMinutes.value = false;
    pendingBeforeStartValue.value = initialPendingBeforeStart / 60;
} else {
    isPendingBeforeStartMinutes.value = true;
    pendingBeforeStartValue.value = initialPendingBeforeStart;
}
const pendingBeforeStartMax = computed(() => (isPendingBeforeStartMinutes.value ? 10080 : 168));
const pendingBeforeStartStep = computed(() => (isPendingBeforeStartMinutes.value ? 1 : 0.25));
const canUsePendingBeforeStartHours = computed(() => {
    const current = Number(pendingBeforeStartValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingBeforeStartMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isPendingWarningMinutes = ref(false);
const pendingWarningValue = ref(1);
const initialPendingWarning = props.settings?.pending_warning_minutes ?? 30;
if (initialPendingWarning === 0) {
    isPendingWarningMinutes.value = false;
    pendingWarningValue.value = 0;
} else if (initialPendingWarning % 60 === 0) {
    isPendingWarningMinutes.value = false;
    pendingWarningValue.value = initialPendingWarning / 60;
} else {
    isPendingWarningMinutes.value = true;
    pendingWarningValue.value = initialPendingWarning;
}
const pendingWarningMax = computed(() => (isPendingWarningMinutes.value ? 10080 : 168));
const pendingWarningStep = computed(() => (isPendingWarningMinutes.value ? 1 : 0.25));
const canUsePendingWarningHours = computed(() => {
    const current = Number(pendingWarningValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingWarningMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isBookingLeadMinutes = ref(false);
const bookingLeadValue = ref(1);
const initialBookingLead = props.settings?.booking_lead_time_minutes ?? 15;
if (initialBookingLead === 0) {
    isBookingLeadMinutes.value = false;
    bookingLeadValue.value = 0;
} else if (initialBookingLead % 60 === 0) {
    isBookingLeadMinutes.value = false;
    bookingLeadValue.value = initialBookingLead / 60;
} else {
    isBookingLeadMinutes.value = true;
    bookingLeadValue.value = initialBookingLead;
}
const bookingLeadMax = computed(() => (isBookingLeadMinutes.value ? 1440 : 24));
const bookingLeadStep = computed(() => (isBookingLeadMinutes.value ? 1 : 0.25));
const canUseBookingLeadHours = computed(() => {
    const current = Number(bookingLeadValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isBookingLeadMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isBookingMinIntervalMinutes = ref(false);
const bookingMinIntervalValue = ref(1);
const initialBookingMinInterval = props.settings?.booking_min_interval_minutes ?? 30;
if (initialBookingMinInterval === 0) {
    isBookingMinIntervalMinutes.value = false;
    bookingMinIntervalValue.value = 0;
} else if (initialBookingMinInterval % 60 === 0) {
    isBookingMinIntervalMinutes.value = false;
    bookingMinIntervalValue.value = initialBookingMinInterval / 60;
} else {
    isBookingMinIntervalMinutes.value = true;
    bookingMinIntervalValue.value = initialBookingMinInterval;
}
const bookingMinIntervalMax = computed(() => (isBookingMinIntervalMinutes.value ? 1440 : 24));
const bookingMinIntervalStep = computed(() => (isBookingMinIntervalMinutes.value ? 1 : 0.25));
const canUseBookingMinIntervalHours = computed(() => {
    const current = Number(bookingMinIntervalValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isBookingMinIntervalMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const form = useForm({
    booking_lead_time_minutes: props.settings?.booking_lead_time_minutes ?? 15,
    booking_min_interval_minutes: props.settings?.booking_min_interval_minutes ?? 30,
    rental_duration_minutes: props.settings?.rental_duration_minutes ?? 60,
    rental_price_rub: props.settings?.rental_price_rub ?? 0,
    payment_order_id: props.settings?.payment_order_id ?? '',
    booking_mode: props.settings?.booking_mode ?? 'instant',
    payment_wait_minutes: props.settings?.payment_wait_minutes ?? 60,
    pending_review_minutes: props.settings?.pending_review_minutes ?? 120,
    pending_before_start_minutes: props.settings?.pending_before_start_minutes ?? 120,
    pending_warning_minutes: props.settings?.pending_warning_minutes ?? 30,
    booking_lead_time_is_minutes: true,
    booking_min_interval_is_minutes: true,
    rental_duration_is_minutes: true,
    payment_wait_is_minutes: true,
    pending_review_is_minutes: true,
    pending_before_start_is_minutes: true,
    pending_warning_is_minutes: true,
});

const submit = () => {
    localNotice.value = '';
    const leadValue = Number(bookingLeadValue.value);
    form.booking_lead_time_minutes = Number.isFinite(leadValue) ? leadValue : null;
    form.booking_lead_time_is_minutes = isBookingLeadMinutes.value;
    const minIntervalValue = Number(bookingMinIntervalValue.value);
    form.booking_min_interval_minutes = Number.isFinite(minIntervalValue) ? minIntervalValue : null;
    form.booking_min_interval_is_minutes = isBookingMinIntervalMinutes.value;
    const durationValue = Number(rentalDurationValue.value);
    form.rental_duration_minutes = Number.isFinite(durationValue) ? durationValue : null;
    form.rental_duration_is_minutes = isMinutes.value;
    const waitValue = Number(paymentWaitValue.value);
    form.payment_wait_minutes = Number.isFinite(waitValue) ? waitValue : null;
    form.payment_wait_is_minutes = isPaymentWaitMinutes.value;
    const reviewValue = Number(pendingReviewValue.value);
    form.pending_review_minutes = Number.isFinite(reviewValue) ? reviewValue : null;
    form.pending_review_is_minutes = isPendingReviewMinutes.value;
    const beforeStartValue = Number(pendingBeforeStartValue.value);
    form.pending_before_start_minutes = Number.isFinite(beforeStartValue) ? beforeStartValue : null;
    form.pending_before_start_is_minutes = isPendingBeforeStartMinutes.value;
    const warningValue = Number(pendingWarningValue.value);
    form.pending_warning_minutes = Number.isFinite(warningValue) ? warningValue : null;
    form.pending_warning_is_minutes = isPendingWarningMinutes.value;
    form.patch(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/admin/settings`, {
        preserveScroll: true,
        onSuccess: () => {
            localNotice.value = actionNotice.value || 'Настройки сохранены.';
        },
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="successNotice" :error="formErrorNotice" />

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
                    <h1 class="text-3xl font-semibold text-slate-900">Настройки</h1>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <form class="space-y-8" @submit.prevent="submit">
                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Бронирование
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Допустимое время до начала бронирования
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isBookingLeadMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="bookingLeadValue"
                                            type="number"
                                            min="0"
                                            :step="bookingLeadStep"
                                            :max="bookingLeadMax"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.booking_lead_time_minutes" class="text-xs text-rose-700">
                                            {{ actionError.booking_lead_time_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Минимальный интервал бронирования
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isBookingMinIntervalMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="bookingMinIntervalValue"
                                            type="number"
                                            min="1"
                                            :step="bookingMinIntervalStep"
                                            :max="bookingMinIntervalMax"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.booking_min_interval_minutes" class="text-xs text-rose-700">
                                            {{ actionError.booking_min_interval_minutes }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Оплата и режим
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Порядок оплаты
                                        </label>
                                        <select
                                            v-model="form.payment_order_id"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        >
                                            <option v-for="option in paymentOrderOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p v-if="actionError.payment_order_id" class="text-xs text-rose-700">
                                            {{ actionError.payment_order_id }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Режим бронирования
                                        </label>
                                        <select
                                            v-model="form.booking_mode"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        >
                                            <option value="instant">Мгновенное подтверждение</option>
                                            <option value="approval_required">Подтверждение администратора</option>
                                        </select>
                                        <p v-if="actionError.booking_mode" class="text-xs text-rose-700">
                                            {{ actionError.booking_mode }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Срок ожидания оплаты
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPaymentWaitMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="paymentWaitValue"
                                            type="number"
                                            min="0"
                                            :step="paymentWaitStep"
                                            :max="paymentWaitMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">0 — бессрочно.</p>
                                        <p v-if="actionError.payment_wait_minutes" class="text-xs text-rose-700">
                                            {{ actionError.payment_wait_minutes }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Автоотмена pending
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Время на рассмотрение заявки
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingReviewMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingReviewValue"
                                            type="number"
                                            min="0"
                                            :step="pendingReviewStep"
                                            :max="pendingReviewMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">Считается только в рабочее время площадки.</p>
                                        <p v-if="actionError.pending_review_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_review_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Автоотмена до начала
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingBeforeStartMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingBeforeStartValue"
                                            type="number"
                                            min="0"
                                            :step="pendingBeforeStartStep"
                                            :max="pendingBeforeStartMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">Отсчитывается от времени начала брони.</p>
                                        <p v-if="actionError.pending_before_start_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_before_start_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Предупреждение автоотмены
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingWarningMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingWarningValue"
                                            type="number"
                                            min="0"
                                            :step="pendingWarningStep"
                                            :max="pendingWarningMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">0 — не отправлять предупреждение.</p>
                                        <p v-if="actionError.pending_warning_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_warning_minutes }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Стоимость
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Длительность аренды
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="rentalDurationValue"
                                            type="number"
                                            min="1"
                                            :step="rentalDurationStep"
                                            :max="rentalDurationMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.rental_duration_minutes" class="text-xs text-rose-700">
                                            {{ actionError.rental_duration_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Стоимость аренды (руб.)
                                        </label>
                                        <div class="h-6"></div>
                                        <input
                                            v-model="form.rental_price_rub"
                                            type="number"
                                            min="0"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.rental_price_rub" class="text-xs text-rose-700">
                                            {{ actionError.rental_price_rub }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="submit"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    :disabled="form.processing"
                                >
                                    Сохранить
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
