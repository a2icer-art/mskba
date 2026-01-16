<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

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
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
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

watch(statusFilter, (value) => {
    if (!props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    router.get(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/bookings`,
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

const confirmOpen = ref(false);
const cancelOpen = ref(false);
const awaitPaymentOpen = ref(false);
const paidOpen = ref(false);
const activeBooking = ref(null);
const confirmForm = useForm({ comment: '' });
const cancelForm = useForm({ comment: '' });
const awaitPaymentForm = useForm({ comment: '' });
const paidForm = useForm({ comment: '' });

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
    awaitPaymentForm.reset('comment');
    awaitPaymentForm.clearErrors();
    awaitPaymentOpen.value = true;
};

const closeAwaitPayment = () => {
    awaitPaymentOpen.value = false;
    activeBooking.value = null;
    awaitPaymentForm.reset('comment');
    awaitPaymentForm.clearErrors();
};

const openPaid = (booking) => {
    activeBooking.value = booking;
    paidForm.reset('comment');
    paidForm.clearErrors();
    paidOpen.value = true;
};

const closePaid = () => {
    paidOpen.value = false;
    activeBooking.value = null;
    paidForm.reset('comment');
    paidForm.clearErrors();
};

const submitConfirm = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    confirmForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/bookings/${activeBooking.value.id}/confirm`,
        { preserveScroll: true, onSuccess: closeConfirm }
    );
};

const submitCancel = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    cancelForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/bookings/${activeBooking.value.id}/cancel`,
        { preserveScroll: true, onSuccess: closeCancel }
    );
};

const submitAwaitPayment = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    awaitPaymentForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/bookings/${activeBooking.value.id}/await-payment`,
        { preserveScroll: true, onSuccess: closeAwaitPayment }
    );
};

const submitPaid = () => {
    if (!activeBooking.value?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    paidForm.post(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/bookings/${activeBooking.value.id}/mark-paid`,
        { preserveScroll: true, onSuccess: closePaid }
    );
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

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation?.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

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

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                    <div v-if="actionError" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ actionError }}
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
                                    <span v-if="booking.payment_order" class="text-xs text-slate-500">
                                        Порядок оплаты: {{ booking.payment_order }}
                                    </span>
                                    <span v-if="booking.payment_code" class="text-xs text-slate-500">
                                        Платеж № {{ booking.payment_code }}
                                    </span>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="booking.can_await_payment"
                                            class="rounded-full border border-indigo-200 bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 transition hover:-translate-y-0.5 hover:border-indigo-300"
                                            type="button"
                                            :disabled="awaitPaymentForm.processing"
                                            @click="openAwaitPayment(booking)"
                                        >
                                            Ожидает оплату
                                        </button>
                                        <button
                                            v-if="booking.can_mark_paid"
                                            class="rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 transition hover:-translate-y-0.5 hover:border-sky-300"
                                            type="button"
                                            :disabled="paidForm.processing"
                                            @click="openPaid(booking)"
                                        >
                                            Оплачено
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
                        <h2 class="text-lg font-semibold text-slate-900">Ожидание оплаты</h2>
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
                            Перевести
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="paidOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: paidForm.processing }" @submit.prevent="submitPaid">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Отметить оплату</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closePaid"
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
                                v-model="paidForm.comment"
                                class="min-h-[100px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            ></textarea>
                        </label>
                        <div v-if="paidForm.errors.comment" class="text-xs text-rose-700">
                            {{ paidForm.errors.comment }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="paidForm.processing"
                            @click="closePaid"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-sky-600 bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-sky-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="paidForm.processing"
                        >
                            Отметить
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
