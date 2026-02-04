<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
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
    bookings: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    paymentOrderOptions: {
        type: Array,
        default: () => [],
    },
    paymentDefaults: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({ status: '' }),
    },
    canConfirm: {
        type: Boolean,
        default: false,
    },
    canCancel: {
        type: Boolean,
        default: false,
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
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.booking ?? '');
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const adminNavigationData = computed(() => props.navigation?.admin ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasAdminSidebar = computed(() => (adminNavigationData.value?.length ?? 0) > 0);
const hasAnySidebar = computed(() => hasSidebar.value || hasAdminSidebar.value);
const statusFilter = ref(props.filters?.status || '');
const statuses = [
    { value: '', label: 'Все' },
    { value: 'pending', label: 'Ожидает' },
    { value: 'awaiting_payment', label: 'Ожидает оплату' },
    { value: 'paid', label: 'Оплачено' },
    { value: 'approved', label: 'Подтверждено' },
    { value: 'cancelled', label: 'Отменено' },
];

const filteredBookings = computed(() => props.bookings?.data ?? []);
const paymentOrders = computed(() => props.paymentOrderOptions ?? []);
const paymentDefaults = computed(() => props.paymentDefaults ?? {});

watch(statusFilter, (value) => {
    if (!props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    router.get(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/bookings`,
        { status: value || undefined },
        { preserveScroll: true, preserveState: true, replace: true }
    );
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

const statusLabel = (status) => {
    if (status === 'awaiting_payment') {
        return 'Ожидает оплату';
    }
    if (status === 'paid') {
        return 'Оплачено';
    }
    if (status === 'approved') {
        return 'Подтверждено';
    }
    if (status === 'cancelled') {
        return 'Отменено';
    }
    return 'Ожидает';
};
const paymentConfirmStatusLabel = (status) => {
    if (status === 'user_paid_pending') {
        return 'Ожидает подтверждения';
    }
    if (status === 'user_paid_rejected') {
        return 'Оплата отклонена';
    }
    if (status === 'admin_confirmed') {
        return 'Оплата подтверждена';
    }
    return 'Не запрошено';
};
const isPaymentConfirmationRequested = (booking) => {
    if (booking?.payment_confirmation) {
        return true;
    }
    const status = booking?.payment_confirm_status;
    return Boolean(status && status !== 'none');
};
const canShowPaymentDetails = (booking) => ['awaiting_payment', 'paid', 'approved'].includes(booking?.status);
const moderationSourceLabel = (source) => {
    if (source === 'auto') {
        return 'Автоматически';
    }
    if (source === 'manual') {
        return 'Модератор';
    }
    return '';
};

const isPaymentOverdue = (booking) => {
    if (!booking?.payment_due_at) {
        return false;
    }
    const due = new Date(booking.payment_due_at);
    if (Number.isNaN(due.getTime())) {
        return false;
    }
    return due.getTime() <= Date.now();
};

const resolvePaymentOrder = (id) => paymentOrders.value.find((order) => order.value === id);
const selectedPaymentOrder = computed(() => resolvePaymentOrder(awaitPaymentForm.payment_order_id));
const isPostpayment = computed(() => selectedPaymentOrder.value?.code === 'postpayment');
const isPartialPrepayment = computed(() => selectedPaymentOrder.value?.code === 'partial_prepayment');
const isPrepayment = computed(() => selectedPaymentOrder.value?.code === 'prepayment');

const isPaymentWaitMinutes = ref(false);
const paymentWaitStep = computed(() => (isPaymentWaitMinutes.value ? 1 : 0.25));
const paymentWaitMax = computed(() => (isPaymentWaitMinutes.value ? 10080 : 168));
const highlightPartialSwap = ref(false);
const highlightTimer = ref(null);
const originalTotalAmount = ref(0);
const bookingTotalAmount = ref(0);
const venueDefaultAmount = ref(0);

const formatAmount = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${Number(value)} ₽`;
};

const resolvePaymentAmount = (booking) => {
    if (!booking) {
        return null;
    }
    if (booking.payment_partial_amount_minor) {
        return booking.payment_partial_amount_minor;
    }
    if (booking.payment_amount_minor) {
        return booking.payment_amount_minor;
    }
    if (booking.payment_total_amount_minor) {
        return booking.payment_total_amount_minor;
    }
    return null;
};

const resolveBookingTotalAmount = (booking) => {
    if (!booking) {
        return 0;
    }
    if (booking.payment_total_amount_minor) {
        return booking.payment_total_amount_minor;
    }
    if (booking.payment_amount_minor) {
        return booking.payment_amount_minor;
    }
    return 0;
};

const calcDurationMinutes = (booking) => {
    if (!booking?.starts_at || !booking?.ends_at) {
        return 0;
    }
    const start = new Date(booking.starts_at);
    const end = new Date(booking.ends_at);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return 0;
    }
    const diff = Math.ceil((end.getTime() - start.getTime()) / 60000);
    return diff > 0 ? diff : 0;
};

const calcBaseAmount = (booking) => {
    const durationMinutes = calcDurationMinutes(booking);
    const unitMinutes = Math.max(1, Number(paymentDefaults.value.rental_duration_minutes || 0));
    const unitPrice = Number(paymentDefaults.value.rental_price_rub || 0);
    if (!durationMinutes || !unitPrice) {
        return 0;
    }
    const units = durationMinutes / unitMinutes;
    return Math.round(units * unitPrice);
};

const calcSupervisorFeePercent = () => {
    if (!paymentDefaults.value.supervisor_active) {
        return 0;
    }
    return Number(paymentDefaults.value.supervisor_fee_percent || 0);
};

const calcSupervisorFeeFixed = () => {
    if (!paymentDefaults.value.supervisor_active) {
        return 0;
    }
    return Number(paymentDefaults.value.supervisor_fee_amount_rub || 0);
};

const isSupervisorFeeFixed = computed(() => Boolean(paymentDefaults.value.supervisor_fee_is_fixed));

const resolveBookingBaseAmount = (booking) => {
    const base = Number(booking?.payment_base_amount_minor);
    if (Number.isFinite(base) && base > 0) {
        return base;
    }
    return calcBaseAmount(booking);
};

const resolveBookingSupervisorFeePercent = (booking) => {
    const percent = Number(booking?.payment_supervisor_fee_percent);
    if (Number.isFinite(percent) && percent >= 0) {
        return percent;
    }
    return calcSupervisorFeePercent();
};

const resolveBookingSupervisorFeeFixed = (booking) => {
    const amount = Number(booking?.payment_supervisor_fee_amount_minor);
    if (Number.isFinite(amount) && amount >= 0) {
        return amount;
    }
    return Math.max(0, calcSupervisorFeeFixed());
};

const resolveBookingSupervisorFeeIsFixed = (booking) => {
    if (booking?.payment_supervisor_fee_is_fixed !== undefined && booking?.payment_supervisor_fee_is_fixed !== null) {
        return Boolean(booking.payment_supervisor_fee_is_fixed);
    }
    return isSupervisorFeeFixed.value;
};

const resolveBookingSupervisorFeeAmount = (booking) => {
    if (resolveBookingSupervisorFeeIsFixed(booking)) {
        return resolveBookingSupervisorFeeFixed(booking);
    }
    const baseAmount = resolveBookingBaseAmount(booking);
    const percent = resolveBookingSupervisorFeePercent(booking);
    return percent > 0 ? Math.round(baseAmount * percent / 100) : 0;
};

const calcTotalAmount = (booking) => {
    const baseAmount = calcBaseAmount(booking);
    if (!baseAmount) {
        return 0;
    }
    if (isSupervisorFeeFixed.value) {
        return baseAmount + Math.max(0, calcSupervisorFeeFixed());
    }
    const percent = calcSupervisorFeePercent();
    const feeAmount = percent > 0 ? Math.round(baseAmount * percent / 100) : 0;
    return baseAmount + feeAmount;
};

const resolveTotalAmount = () => {
    const fallback = calcTotalAmount(activeBooking.value);
    return originalTotalAmount.value > 0 ? originalTotalAmount.value : fallback;
};

const isDefaultAmountMismatch = computed(() => {
    if (bookingTotalAmount.value <= 0 || venueDefaultAmount.value <= 0) {
        return false;
    }
    return bookingTotalAmount.value !== venueDefaultAmount.value;
});
const priceLabel = computed(() => (isDefaultAmountMismatch.value ? 'Стоимость (!)' : 'Стоимость'));
const priceLabelTitle = computed(() => {
    if (!isDefaultAmountMismatch.value) {
        return '';
    }
    const baseAmount = calcBaseAmount(activeBooking.value);
    const feeIsFixed = isSupervisorFeeFixed.value;
    const feeAmount = feeIsFixed
        ? Math.max(0, calcSupervisorFeeFixed())
        : Math.round(baseAmount * calcSupervisorFeePercent() / 100);
    const feeLabel = feeIsFixed ? 'фикс' : `${calcSupervisorFeePercent()}%`;
    return `стоимость аренды площадки ${formatAmount(venueDefaultAmount.value)}: стоимость аренды самой площадки ${formatAmount(baseAmount)} + комиссия супервайзера ${formatAmount(feeAmount)} (${feeLabel})`;
});

const supervisorFeePercent = computed(() => resolveBookingSupervisorFeePercent(activeBooking.value));
const supervisorFeeFixed = computed(() => resolveBookingSupervisorFeeFixed(activeBooking.value));
const supervisorFeeIsFixed = computed(() => resolveBookingSupervisorFeeIsFixed(activeBooking.value));
const supervisorBaseAmount = computed(() => resolveBookingBaseAmount(activeBooking.value));
const supervisorFeeAmount = computed(() => resolveBookingSupervisorFeeAmount(activeBooking.value));
const supervisorTotalAmount = computed(() => {
    if (!supervisorBaseAmount.value) {
        return 0;
    }
    return supervisorBaseAmount.value + supervisorFeeAmount.value;
});

const isPartialAmountOverTotal = computed(() => {
    if (!isPartialPrepayment.value) {
        return false;
    }
    const current = Number(awaitPaymentForm.partial_amount_minor ?? 0);
    const total = resolveTotalAmount();
    return total > 0 && current >= total;
});

const initPaymentWaitValue = (minutesValue) => {
    const minutes = Number(minutesValue ?? 0);
    if (minutes === 0) {
        isPaymentWaitMinutes.value = false;
        awaitPaymentForm.payment_wait_minutes = 0;
        return;
    }
    if (minutes % 60 === 0) {
        isPaymentWaitMinutes.value = false;
        awaitPaymentForm.payment_wait_minutes = minutes / 60;
        return;
    }
    isPaymentWaitMinutes.value = true;
    awaitPaymentForm.payment_wait_minutes = minutes;
};

const triggerPartialSwapHighlight = () => {
    highlightPartialSwap.value = true;
    if (highlightTimer.value) {
        clearTimeout(highlightTimer.value);
    }
    highlightTimer.value = setTimeout(() => {
        highlightPartialSwap.value = false;
    }, 2000);
};

const confirmOpen = ref(false);
const cancelOpen = ref(false);
const awaitPaymentOpen = ref(false);
const infoOpen = ref(false);
const activeBooking = ref(null);
const hasModalOpen = computed(() => confirmOpen.value || cancelOpen.value || awaitPaymentOpen.value || infoOpen.value);
const confirmForm = useForm({ comment: '' });
const cancelForm = useForm({ comment: '' });
const awaitPaymentForm = useForm({
    comment: '',
    payment_order_id: null,
    payment_wait_minutes: null,
    payment_wait_is_minutes: null,
    partial_amount_minor: null,
});
const paymentDecisionOpen = ref(false);
const paymentDecisionBooking = ref(null);
const paymentDecisionMode = ref('approve');
const paymentDecisionForm = useForm({ comment: '' });

const openConfirm = (booking) => {
    activeBooking.value = booking;
    confirmForm.reset('comment');
    confirmForm.clearErrors();
    confirmOpen.value = true;
};

const closeConfirm = () => {
    confirmOpen.value = false;
    activeBooking.value = null;
    confirmForm.reset('comment');
    confirmForm.clearErrors();
};

const openCancel = (booking) => {
    activeBooking.value = booking;
    cancelForm.reset('comment');
    cancelForm.clearErrors();
    cancelOpen.value = true;
};

const closeCancel = () => {
    cancelOpen.value = false;
    activeBooking.value = null;
    cancelForm.reset('comment');
    cancelForm.clearErrors();
};

const openAwaitPayment = (booking) => {
    activeBooking.value = booking;
    const defaultPaymentOrderId =
        booking?.payment_order_id
        ?? paymentDefaults.value.payment_order_id
        ?? paymentOrders.value[0]?.value
        ?? null;
    awaitPaymentForm.reset('comment', 'payment_wait_minutes', 'partial_amount_minor');
    awaitPaymentForm.payment_order_id = defaultPaymentOrderId;
    initPaymentWaitValue(paymentDefaults.value.payment_wait_minutes ?? null);
    bookingTotalAmount.value = resolveBookingTotalAmount(booking);
    venueDefaultAmount.value = calcTotalAmount(booking);
    originalTotalAmount.value = bookingTotalAmount.value > 0 ? bookingTotalAmount.value : venueDefaultAmount.value;
    if (selectedPaymentOrder.value?.code === 'partial_prepayment') {
        const totalAmount = resolveTotalAmount();
        awaitPaymentForm.partial_amount_minor = booking?.payment_partial_amount_minor
            ?? (totalAmount ? Math.ceil(totalAmount * 0.5) : null);
    } else {
        awaitPaymentForm.partial_amount_minor = resolveTotalAmount() || null;
    }
    awaitPaymentForm.clearErrors();
    awaitPaymentOpen.value = true;
};

const closeAwaitPayment = () => {
    awaitPaymentOpen.value = false;
    activeBooking.value = null;
    awaitPaymentForm.reset('comment', 'payment_wait_minutes', 'partial_amount_minor');
    isPaymentWaitMinutes.value = false;
    originalTotalAmount.value = 0;
    bookingTotalAmount.value = 0;
    venueDefaultAmount.value = 0;
    awaitPaymentForm.clearErrors();
};

const openInfo = (booking) => {
    activeBooking.value = booking;
    infoOpen.value = true;
};

const closeInfo = () => {
    infoOpen.value = false;
    activeBooking.value = null;
};

const openPaymentDecision = (booking, mode) => {
    if (!booking?.payment_confirmation) {
        return;
    }
    paymentDecisionBooking.value = booking;
    paymentDecisionMode.value = mode;
    paymentDecisionForm.reset('comment');
    paymentDecisionForm.clearErrors();
    paymentDecisionOpen.value = true;
};

const closePaymentDecision = () => {
    paymentDecisionOpen.value = false;
    paymentDecisionBooking.value = null;
    paymentDecisionMode.value = 'approve';
    paymentDecisionForm.reset('comment');
    paymentDecisionForm.clearErrors();
};

const submitConfirm = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    confirmForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/bookings/${activeBooking.value.id}/confirm`,
        { preserveScroll: true, onSuccess: closeConfirm }
    );
};

const submitCancel = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    cancelForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/bookings/${activeBooking.value.id}/cancel`,
        { preserveScroll: true, onSuccess: closeCancel }
    );
};

const submitAwaitPayment = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    if (isPartialAmountOverTotal.value) {
        const confirmed = window.confirm('Введенная стоимость равна или больше полной. Продолжить?');
        if (!confirmed) {
            return;
        }
    }
    awaitPaymentForm.payment_wait_is_minutes = isPostpayment.value ? null : isPaymentWaitMinutes.value;
    awaitPaymentForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/bookings/${activeBooking.value.id}/await-payment`,
        { preserveScroll: true, onSuccess: closeAwaitPayment }
    );
};

const openBookingView = (booking) => {
    if (booking?.can_await_payment && !canShowPaymentDetails(booking)) {
        openAwaitPayment(booking);
        return;
    }
    openInfo(booking);
};

const submitPaymentDecision = () => {
    const booking = paymentDecisionBooking.value;
    if (!booking?.payment_confirmation?.id || !booking?.event?.id) {
        return;
    }
    const action = paymentDecisionMode.value === 'approve' ? 'approve' : 'reject';
    paymentDecisionForm.post(
        `/events/${booking.event.id}/bookings/${booking.id}/payment-confirmations/${booking.payment_confirmation.id}/${action}`,
        { preserveScroll: true, onSuccess: closePaymentDecision }
    );
};

watch(
    () => awaitPaymentForm.payment_order_id,
    (value) => {
        const order = resolvePaymentOrder(value);
        if (!order || order.code === 'postpayment') {
            awaitPaymentForm.payment_wait_minutes = null;
            awaitPaymentForm.payment_wait_is_minutes = null;
            awaitPaymentForm.partial_amount_minor = null;
            return;
        }
        if (awaitPaymentForm.payment_wait_minutes === null) {
            initPaymentWaitValue(paymentDefaults.value.payment_wait_minutes ?? null);
        }
        if (order.code === 'partial_prepayment' && awaitPaymentForm.partial_amount_minor === null) {
            const totalAmount = resolveTotalAmount();
            awaitPaymentForm.partial_amount_minor = totalAmount ? Math.ceil(totalAmount * 0.5) : null;
        }
        if (order.code !== 'partial_prepayment') {
            awaitPaymentForm.partial_amount_minor = resolveTotalAmount() || null;
        } else if (awaitPaymentForm.partial_amount_minor === null && activeBooking.value) {
            awaitPaymentForm.partial_amount_minor = activeBooking.value.payment_partial_amount_minor ?? null;
        } else if (isPartialAmountOverTotal.value) {
            triggerPartialSwapHighlight();
        }
    }
);

watch(
    () => awaitPaymentForm.partial_amount_minor,
    (value) => {
        if (!isPartialPrepayment.value || value === null || value === undefined) {
            return;
        }
        const totalAmount = resolveTotalAmount();
        if (totalAmount && Number(value) >= totalAmount) {
            triggerPartialSwapHighlight();
        }
    }
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
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasAnySidebar }">
                <div v-if="hasAnySidebar" class="flex flex-col gap-4">
                    <MainSidebar
                        v-if="hasSidebar"
                        :data="navigationData"
                        :active-href="activeHref"
                    />
                    <MainSidebar
                        v-if="hasAdminSidebar"
                        :data="adminNavigationData"
                        :active-href="activeHref"
                    />
                </div>

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <h1 class="text-3xl font-semibold text-slate-900">Бронирования</h1>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Статус
                            <select v-model="statusFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <option v-for="status in statuses" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                        </label>
                    </div>

                    

                    <div v-if="!filteredBookings.length" class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                        Бронирования не найдены.
                    </div>
                    <div v-else class="mt-6 grid gap-3">
                        <article
                            v-for="booking in filteredBookings"
                            :key="booking.id"
                            class="rounded-2xl border border-slate-200 bg-white px-5 py-4"
                        >
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">
                                        {{ booking.event?.title || 'Событие' }}
                                    </h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ booking.event?.type?.label || 'Тип не задан' }}
                                    </p>
                                    <p class="mt-2 text-sm text-slate-700">
                                        {{ formatDateRange(booking.starts_at, booking.ends_at) }}
                                    </p>
                                    <p v-if="booking.creator?.login" class="mt-1 text-xs text-slate-500">
                                        Создатель: {{ booking.creator.login }}
                                    </p>
                                    <p v-if="booking.moderator?.login" class="mt-1 text-xs text-slate-500">
                                        Модератор: {{ booking.moderator.login }}
                                    </p>
                                    <p v-if="booking.moderated_at" class="mt-1 text-xs text-slate-500">
                                        Модерация: {{ formatDateTime(booking.moderated_at) }}
                                    </p>
                                    <p v-if="booking.moderation_source && booking.moderated_at" class="mt-1 text-xs text-slate-500">
                                        Изменено: {{ moderationSourceLabel(booking.moderation_source) }}
                                    </p>
                                    <p v-if="booking.moderation_comment" class="mt-1 text-xs text-slate-500">
                                        Комментарий: {{ booking.moderation_comment }}
                                    </p>
                                </div>
                                <div class="flex flex-col items-end gap-2">
                                    <span
                                        class="rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.15em]"
                                        :class="booking.status === 'approved'
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                            : booking.status === 'cancelled'
                                                ? 'border-rose-200 bg-rose-50 text-rose-700'
                                                : booking.status === 'awaiting_payment'
                                                    ? 'border-indigo-200 bg-indigo-50 text-indigo-700'
                                                    : booking.status === 'paid'
                                                        ? 'border-sky-200 bg-sky-50 text-sky-700'
                                                        : 'border-amber-200 bg-amber-50 text-amber-800'"
                                    >
                                        {{ statusLabel(booking.status) }}
                                    </span>
                                    <span v-if="canShowPaymentDetails(booking) && booking.payment_order" class="text-xs text-slate-500">
                                        Порядок оплаты: {{ booking.payment_order }}
                                    </span>
                                    <span v-if="canShowPaymentDetails(booking) && booking.payment_code" class="text-xs text-slate-500">
                                        Платеж № {{ booking.payment_code }}
                                    </span>
                                    <span v-if="canShowPaymentDetails(booking) && resolvePaymentAmount(booking)" class="text-xs text-slate-500">
                                        К оплате: {{ formatAmount(resolvePaymentAmount(booking)) }}
                                    </span>
                                    <span v-if="canShowPaymentDetails(booking) && booking.status === 'awaiting_payment'" class="text-xs text-slate-500">
                                        Оплатить до:
                                        {{ booking.payment_due_at ? formatDateTime(booking.payment_due_at) : 'бессрочно' }}
                                    </span>
                                    <div
                                        v-if="canShowPaymentDetails(booking) && isPaymentConfirmationRequested(booking)"
                                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
                                    >
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">
                                                Подтверждение оплаты
                                            </span>
                                            <span class="font-semibold text-slate-700">
                                                {{ paymentConfirmStatusLabel(booking.payment_confirm_status) }}
                                            </span>
                                        </div>
                                        <div v-if="booking.payment_confirmation" class="mt-2 text-xs text-slate-500">
                                            <p v-if="booking.payment_confirmation.payment_method_snapshot?.label" class="mt-1">
                                                Метод: {{ booking.payment_confirmation.payment_method_snapshot.label }}
                                            </p>
                                            <p v-if="booking.payment_confirmation.evidence_comment" class="mt-1">
                                                Комментарий: {{ booking.payment_confirmation.evidence_comment }}
                                            </p>
                                            <a
                                                v-if="booking.payment_confirmation.evidence_media_url"
                                                class="mt-1 inline-flex items-center gap-1 font-semibold text-slate-700 underline decoration-dotted hover:text-slate-900"
                                                :href="booking.payment_confirmation.evidence_media_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Смотреть скриншот
                                            </a>
                                            <p v-if="booking.payment_confirmation.decision_comment" class="mt-1">
                                                Комментарий администратора: {{ booking.payment_confirmation.decision_comment }}
                                            </p>
                                        </div>
                                        <div
                                            v-if="booking.payment_confirmation?.status === 'pending' && canConfirm"
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <p v-if="isPaymentOverdue(booking)" class="text-xs text-rose-700">
                                                Срок оплаты истёк. Подтверждение недоступно.
                                            </p>
                                            <template v-else>
                                                <button
                                                    class="rounded-full border border-emerald-600 bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700"
                                                    type="button"
                                                    @click="openPaymentDecision(booking, 'approve')"
                                                >
                                                    Подтвердить оплату
                                                </button>
                                                <button
                                                    class="rounded-full border border-rose-500 bg-rose-500 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-600"
                                                    type="button"
                                                    @click="openPaymentDecision(booking, 'reject')"
                                                >
                                                    Отклонить оплату
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="booking.can_await_payment || canShowPaymentDetails(booking)"
                                            class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 transition hover:-translate-y-0.5 hover:border-indigo-300"
                                            type="button"
                                            :disabled="awaitPaymentForm.processing"
                                            @click="openBookingView(booking)"
                                        >
                                            Просмотр заявки
                                        </button>
                                        <button
                                            v-if="booking.can_confirm"
                                            class="rounded-full border border-emerald-600 bg-emerald-600 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700"
                                            type="button"
                                            :disabled="confirmForm.processing"
                                            @click="openConfirm(booking)"
                                        >
                                            Подтвердить
                                        </button>
                                        <button
                                            v-if="booking.can_cancel"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                            type="button"
                                            :disabled="cancelForm.processing"
                                            @click="openCancel(booking)"
                                        >
                                            Отменить
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </article>
                    </div>

                    <div v-if="bookings.links?.length" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            v-for="link in bookings.links"
                            :key="link.label"
                            class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            :class="{
                                'bg-slate-900 text-white': link.active,
                                'pointer-events-none opacity-50': !link.url,
                            }"
                            :href="link.url || ''"
                            v-html="link.label"
                        />
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="confirmOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: confirmForm.processing }" @submit.prevent="submitConfirm">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Подтвердить бронирование</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeConfirm"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-700">
                            Событие: {{ activeBooking?.event?.title || 'Событие' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ formatDateTime(activeBooking?.starts_at) }} – {{ formatDateTime(activeBooking?.ends_at) }}
                        </p>
                        <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий (опционально)
                            <textarea
                                v-model="confirmForm.comment"
                                class="min-h-[100px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            ></textarea>
                        </label>
                        <div v-if="confirmForm.errors.comment" class="text-xs text-rose-700">
                            {{ confirmForm.errors.comment }}
                        </div>
                        
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="confirmForm.processing"
                            @click="closeConfirm"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="confirmForm.processing"
                        >
                            Подтвердить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="awaitPaymentOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: awaitPaymentForm.processing }" @submit.prevent="submitAwaitPayment">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Просмотр заявки</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeAwaitPayment"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-700">
                            Событие: {{ activeBooking?.event?.title || 'Событие' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ formatDateTime(activeBooking?.starts_at) }} – {{ formatDateTime(activeBooking?.ends_at) }}
                        </p>
                        <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Порядок оплаты
                            <select
                                v-model="awaitPaymentForm.payment_order_id"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                :class="highlightPartialSwap ? 'border-amber-400 bg-amber-50' : ''"
                            >
                                <option v-for="order in paymentOrders" :key="order.value" :value="order.value">
                                    {{ order.label }}
                                </option>
                            </select>
                        </label>
                        <div v-if="awaitPaymentForm.errors.payment_order_id" class="text-xs text-rose-700">
                            {{ awaitPaymentForm.errors.payment_order_id }}
                        </div>
                        <div v-if="!isPostpayment" class="mt-4 space-y-2">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Срок ожидания оплаты</span>
                            <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                <input
                                    v-model="isPaymentWaitMinutes"
                                    type="checkbox"
                                    class="input-switch"
                                />
                                <span>В минутах</span>
                            </label>
                            <input
                                v-model.number="awaitPaymentForm.payment_wait_minutes"
                                type="number"
                                min="0"
                                :step="paymentWaitStep"
                                :max="paymentWaitMax"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </div>
                        <div v-if="awaitPaymentForm.errors.payment_wait_minutes" class="text-xs text-rose-700">
                            {{ awaitPaymentForm.errors.payment_wait_minutes }}
                        </div>
                        <label
                            class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500"
                            :title="priceLabelTitle || null"
                        >
                            {{ priceLabel }}
                            <input
                                v-model.number="awaitPaymentForm.partial_amount_minor"
                                type="number"
                                min="1"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                :class="highlightPartialSwap || isPartialAmountOverTotal ? 'border-amber-400 bg-amber-50' : ''"
                            />
                        </label>
                        <div v-if="supervisorFeePercent > 0 || supervisorFeeFixed > 0" class="mt-2 text-xs text-slate-500">
                            <div>Базовая стоимость: {{ formatAmount(supervisorBaseAmount) }}</div>
                            <div v-if="supervisorFeeIsFixed">
                                Комиссия супервайзера (фиксированная):
                                {{ formatAmount(supervisorFeeFixed) }}
                            </div>
                            <div v-else>
                                Комиссия супервайзера ({{ supervisorFeePercent }}%):
                                {{ formatAmount(supervisorFeeAmount) }}
                            </div>
                            <div class="font-semibold text-slate-700">
                                Итого: {{ formatAmount(supervisorTotalAmount) }}
                            </div>
                        </div>
                        <p v-if="isPartialAmountOverTotal" class="text-xs text-amber-700">
                            Стоимость равна или превышает полную. Проверьте порядок оплаты.
                        </p>
                        <div v-if="awaitPaymentForm.errors.partial_amount_minor" class="text-xs text-rose-700">
                            {{ awaitPaymentForm.errors.partial_amount_minor }}
                        </div>
                        <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий (опционально)
                            <textarea
                                v-model="awaitPaymentForm.comment"
                                class="min-h-[100px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            ></textarea>
                        </label>
                        <div v-if="awaitPaymentForm.errors.comment" class="text-xs text-rose-700">
                            {{ awaitPaymentForm.errors.comment }}
                        </div>
                        
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="awaitPaymentForm.processing"
                            @click="closeAwaitPayment"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-indigo-600 bg-indigo-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-indigo-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="awaitPaymentForm.processing"
                        >
                            В оплату
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <div v-if="infoOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                <h2 class="text-lg font-semibold text-slate-900">Информация о заявке</h2>
                <button
                    class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                    type="button"
                    aria-label="Закрыть"
                    @click="closeInfo"
                >
                    x
                </button>
            </div>
            <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                <p class="text-sm text-slate-700">
                    Событие: {{ activeBooking?.event?.title || 'Событие' }}
                </p>
                <p class="mt-1 text-sm text-slate-600">
                    {{ formatDateTime(activeBooking?.starts_at) }} – {{ formatDateTime(activeBooking?.ends_at) }}
                </p>
                <div class="mt-4 space-y-2 text-sm text-slate-700">
                    <p v-if="activeBooking?.payment_order">
                        Порядок оплаты: {{ activeBooking.payment_order }}
                    </p>
                    <p v-if="activeBooking?.payment_code">
                        Платеж № {{ activeBooking.payment_code }}
                    </p>
                    <p v-if="resolvePaymentAmount(activeBooking)">
                        К оплате: {{ formatAmount(resolvePaymentAmount(activeBooking)) }}
                    </p>
                    <p v-if="activeBooking?.payment_due_at">
                        Оплатить до: {{ formatDateTime(activeBooking.payment_due_at) }}
                    </p>
                    <p v-if="activeBooking?.moderation_comment">
                        Комментарий: {{ activeBooking.moderation_comment }}
                    </p>
                </div>
            </div>
            <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                <button
                    class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                    type="button"
                    @click="closeInfo"
                >
                    Закрыть
                </button>
            </div>
        </div>
    </div>

    <div v-if="paymentDecisionOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: paymentDecisionForm.processing }" @submit.prevent="submitPaymentDecision">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ paymentDecisionMode === 'approve' ? 'Подтвердить оплату' : 'Отклонить оплату' }}
                    </h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closePaymentDecision"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        {{ paymentDecisionMode === 'approve'
                            ? 'Подтвердите, что оплата получена, и при необходимости оставьте комментарий.'
                            : 'Укажите причину отклонения (необязательно).' }}
                    </p>
                    <textarea
                        v-model="paymentDecisionForm.comment"
                        class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        placeholder="Комментарий (необязательно)"
                    ></textarea>
                    <div v-if="paymentDecisionForm.errors.comment" class="mt-2 text-xs text-rose-700">
                        {{ paymentDecisionForm.errors.comment }}
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="paymentDecisionForm.processing"
                        @click="closePaymentDecision"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        :class="paymentDecisionMode === 'approve' ? 'border-emerald-600 bg-emerald-600 hover:bg-emerald-700' : 'border-rose-500 bg-rose-500 hover:bg-rose-600'"
                        type="submit"
                        :disabled="paymentDecisionForm.processing"
                    >
                        {{ paymentDecisionMode === 'approve' ? 'Подтвердить' : 'Отклонить' }}
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="cancelOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: cancelForm.processing }" @submit.prevent="submitCancel">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Отменить бронирование</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeCancel"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-700">
                            Событие: {{ activeBooking?.event?.title || 'Событие' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-600">
                            {{ formatDateTime(activeBooking?.starts_at) }} – {{ formatDateTime(activeBooking?.ends_at) }}
                        </p>
                        <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий (опционально)
                            <textarea
                                v-model="cancelForm.comment"
                                class="min-h-[100px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            ></textarea>
                        </label>
                        <div v-if="cancelForm.errors.comment" class="text-xs text-rose-700">
                            {{ cancelForm.errors.comment }}
                        </div>
                        
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="cancelForm.processing"
                            @click="closeCancel"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-rose-500 bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-600 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="cancelForm.processing"
                        >
                            Отменить
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
