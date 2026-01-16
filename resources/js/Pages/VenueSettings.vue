<script setup>
import { computed } from 'vue';
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

const form = useForm({
    booking_lead_time_minutes: props.settings?.booking_lead_time_minutes ?? 15,
    booking_min_interval_minutes: props.settings?.booking_min_interval_minutes ?? 30,
    payment_order_id: props.settings?.payment_order_id ?? '',
});

const submit = () => {
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
