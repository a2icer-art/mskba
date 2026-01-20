<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
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
const actionError = computed(() => page.props?.errors ?? {});

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
    return Number.isFinite(current) ? current >= 60 : false;
});

watch(isMinutes, (value) => {
    const current = Number(rentalDurationValue.value);
    if (!Number.isFinite(current)) {
        return;
    }

    if (!value && current < 60) {
        isMinutes.value = true;
        return;
    }

    rentalDurationValue.value = value ? current * 60 : Number((current / 60).toFixed(2));
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
    return Number.isFinite(current) ? current >= 60 : false;
});

watch(isPaymentWaitMinutes, (value) => {
    const current = Number(paymentWaitValue.value);
    if (!Number.isFinite(current) || current === 0) {
        return;
    }

    if (!value && current < 60) {
        isPaymentWaitMinutes.value = true;
        return;
    }

    paymentWaitValue.value = value ? current * 60 : Number((current / 60).toFixed(2));
});

const form = useForm({
    booking_lead_time_minutes: props.settings?.booking_lead_time_minutes ?? 15,
    booking_min_interval_minutes: props.settings?.booking_min_interval_minutes ?? 30,
    rental_duration_minutes: props.settings?.rental_duration_minutes ?? 60,
    rental_price_rub: props.settings?.rental_price_rub ?? 0,
    payment_order_id: props.settings?.payment_order_id ?? '',
    booking_mode: props.settings?.booking_mode ?? 'instant',
    payment_wait_minutes: props.settings?.payment_wait_minutes ?? 60,
});

const submit = () => {
    const durationValue = Number(rentalDurationValue.value);
    form.rental_duration_minutes = Number.isFinite(durationValue)
        ? isMinutes.value
            ? durationValue
            : durationValue * 60
        : null;
    const waitValue = Number(paymentWaitValue.value);
    form.payment_wait_minutes = Number.isFinite(waitValue)
        ? isPaymentWaitMinutes.value
            ? waitValue
            : waitValue * 60
        : null;
    form.patch(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/settings`, {
        preserveScroll: true,
    });
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
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <h1 class="text-3xl font-semibold text-slate-900">Настройки</h1>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Допустимое время до начала бронирования (мин.)
                                </label>
                                <input
                                    v-model="form.booking_lead_time_minutes"
                                    type="number"
                                    min="0"
                                    max="1440"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                />
                                <p v-if="actionError.booking_lead_time_minutes" class="text-xs text-rose-700">
                                    {{ actionError.booking_lead_time_minutes }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Минимальный интервал бронирования (мин.)
                                </label>
                                <input
                                    v-model="form.booking_min_interval_minutes"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                />
                                <p v-if="actionError.booking_min_interval_minutes" class="text-xs text-rose-700">
                                    {{ actionError.booking_min_interval_minutes }}
                                </p>
                            </div>

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

                            <div class="grid gap-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Срок ожидания оплаты
                                </label>
                                <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    <input
                                        v-model="isPaymentWaitMinutes"
                                        type="checkbox"
                                        class="input-switch"
                                        :disabled="!canUsePaymentWaitHours"
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
                                            :disabled="!canUseRentalHours"
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

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
