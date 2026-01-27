<script setup>
import { computed, ref } from 'vue';
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
    supervisor: {
        type: Object,
        default: () => ({ is_active: false, user: null }),
    },
    canManage: {
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
const feeIsFixed = ref(Boolean(props.settings?.supervisor_fee_is_fixed ?? false));
const feeValue = ref(feeIsFixed.value
    ? Number(props.settings?.supervisor_fee_amount_rub ?? 0)
    : Number(props.settings?.supervisor_fee_percent ?? 0));
const feeMax = computed(() => (feeIsFixed.value ? null : 100));
const form = useForm({
    supervisor_fee_value: feeValue.value,
    supervisor_fee_is_fixed: feeIsFixed.value,
});
const submit = () => {
    localNotice.value = '';
    const value = Number(feeValue.value);
    form.supervisor_fee_value = Number.isFinite(value) ? value : 0;
    form.supervisor_fee_is_fixed = feeIsFixed.value;
    form.patch(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/admin/supervisor`, {
        preserveScroll: true,
        onSuccess: () => {
            localNotice.value = actionNotice.value || 'Настройки супервайзера сохранены.';
        },
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
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Супервайзер</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Настройки и информация для супервайзера площадки.
                            </p>
                        </div>
                        <span
                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-600"
                        >
                            {{ canManage ? 'Редактирование доступно' : 'Только просмотр' }}
                        </span>
                    </div>

                    <div v-if="successNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ successNotice }}
                    </div>
                    <div v-if="formErrorNotice" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                        {{ formErrorNotice }}
                    </div>

                    <div class="mt-6 grid gap-4 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Активный супервайзер</p>
                                <p class="mt-1 text-base font-semibold text-slate-900">
                                    {{ supervisor?.user?.login || 'Не назначен' }}
                                </p>
                            </div>
                            <span
                                class="rounded-full border px-3 py-1 text-xs font-semibold"
                                :class="supervisor?.is_active ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'"
                            >
                                {{ supervisor?.is_active ? 'Активен' : 'Не активен' }}
                            </span>
                        </div>
                        <p class="text-sm text-slate-600">
                            Комиссия применяется только при наличии активного супервайзера и влияет на итоговую стоимость бронирования.
                        </p>
                    </div>

                    <form
                        v-if="canManage"
                        class="mt-6 rounded-2xl border border-slate-200 bg-white px-5 py-4"
                        :class="{ loading: form.processing }"
                        @submit.prevent="submit"
                    >
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Комиссия супервайзера</p>
                        <label class="mt-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                            <input
                                v-model="feeIsFixed"
                                type="checkbox"
                                class="input-switch"
                            />
                            <span>Фиксированная надбавка</span>
                        </label>
                        <label class="mt-2 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комиссия
                            <input
                                v-model.number="feeValue"
                                type="number"
                                min="0"
                                :max="feeMax || undefined"
                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            />
                        </label>
                        <div v-if="form.errors.supervisor_fee_value" class="mt-2 text-xs text-rose-700">
                            {{ form.errors.supervisor_fee_value }}
                        </div>
                        <button
                            class="mt-4 rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                            type="submit"
                            :disabled="form.processing"
                        >
                            Сохранить
                        </button>
                    </form>

                    <div v-else class="mt-6 rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-700">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Комиссия супервайзера</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">
                            <span v-if="settings?.supervisor_fee_is_fixed">
                                {{ Number(settings?.supervisor_fee_amount_rub ?? 0) }} ₽
                            </span>
                            <span v-else>
                                {{ Number(settings?.supervisor_fee_percent ?? 0) }}%
                            </span>
                        </p>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
