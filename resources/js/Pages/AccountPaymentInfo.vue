<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import SystemNoticeStack from '../Components/SystemNoticeStack.vue';
import { useMessageRealtime } from '../Composables/useMessageRealtime';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    user: {
        type: Object,
        default: null,
    },
    paymentMethods: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Аккаунт', data: [] }),
    },
    activeHref: {
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
const actionError = computed(() => page.props?.errors ?? {});
const paymentMethodError = computed(() => page.props?.errors?.payment_method ?? '');

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);

const { unreadCount } = useMessageRealtime({});
const applyBadge = (item) => (item.key === 'messages'
    ? {
        ...item,
        badge: unreadCount.value,
    }
    : item);
const sidebarData = computed(() =>
    navigationData.value.map((item) => {
        if (Array.isArray(item?.items)) {
            return {
                ...item,
                items: item.items.map(applyBadge),
            };
        }
        return applyBadge(item);
    })
);

const paymentMethodTypes = [
    { value: 'sbp', label: 'СБП' },
    { value: 'balance', label: 'Баланс' },
    { value: 'acquiring', label: 'Эквайринг' },
];
const getPaymentMethodTypeLabel = (value) =>
    paymentMethodTypes.find((item) => item.value === value)?.label || value;
const paymentMethods = computed(() => props.paymentMethods ?? []);

const paymentMethodOpen = ref(false);
const paymentMethodTarget = ref(null);
const paymentMethodForm = useForm({
    type: 'sbp',
    label: '',
    phone: '',
    display_name: '',
    is_active: true,
    sort_order: 0,
});
const deletePaymentMethodForm = useForm({});

const openPaymentMethod = (method = null) => {
    paymentMethodTarget.value = method;
    paymentMethodForm.clearErrors();
    if (method) {
        paymentMethodForm.type = method.type || 'sbp';
        paymentMethodForm.label = method.label || '';
        paymentMethodForm.phone = method.phone || '';
        paymentMethodForm.display_name = method.display_name || '';
        paymentMethodForm.is_active = Boolean(method.is_active);
        paymentMethodForm.sort_order = Number(method.sort_order || 0);
    } else {
        paymentMethodForm.reset();
        paymentMethodForm.type = 'sbp';
        paymentMethodForm.is_active = true;
        paymentMethodForm.sort_order = 0;
    }
    paymentMethodOpen.value = true;
};

const closePaymentMethod = () => {
    paymentMethodOpen.value = false;
    paymentMethodTarget.value = null;
    paymentMethodForm.reset();
    paymentMethodForm.clearErrors();
};

const submitPaymentMethod = () => {
    const targetId = paymentMethodTarget.value?.id;
    const method = targetId ? 'patch' : 'post';
    const url = targetId
        ? `/account/settings/payment-info/${targetId}`
        : '/account/settings/payment-info';

    paymentMethodForm[method](url, {
        preserveScroll: true,
        onSuccess: closePaymentMethod,
    });
};

const deletePaymentMethod = (method) => {
    if (!method?.id) {
        return;
    }

    deletePaymentMethodForm.delete(`/account/settings/payment-info/${method.id}`, {
        preserveScroll: true,
    });
};

watch(
    () => paymentMethodForm.type,
    (value) => {
        if (value !== 'sbp') {
            paymentMethodForm.phone = '';
            paymentMethodForm.display_name = '';
        }
    }
);
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" />

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
                :sidebar-title="navigation.title"
                :sidebar-items="sidebarData"
                :sidebar-active-href="activeHref"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation?.title || 'Аккаунт'"
                    :data="sidebarData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Платежная информация</h1>
                            <p class="mt-2 text-sm text-slate-600">Методы оплаты для участия в событиях.</p>
                        </div>
                        <button
                            type="button"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            @click="openPaymentMethod()"
                        >
                            Добавить метод
                        </button>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-if="paymentMethods.length" class="grid gap-3">
                            <div
                                v-for="method in paymentMethods"
                                :key="method.id"
                                class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3"
                            >
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">
                                        {{ method.label }}
                                        <span class="text-xs text-slate-500">({{ getPaymentMethodTypeLabel(method.type) }})</span>
                                    </p>
                                    <p v-if="method.type === 'sbp'" class="text-xs text-slate-500">
                                        {{ method.phone }} · {{ method.display_name }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        Статус: {{ method.is_active ? 'Активен' : 'Неактивен' }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-600 transition hover:border-slate-300"
                                        @click="openPaymentMethod(method)"
                                    >
                                        Редактировать
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-rose-200 px-3 py-1 text-xs text-rose-700 transition hover:border-rose-300"
                                        :disabled="deletePaymentMethodForm.processing"
                                        @click="deletePaymentMethod(method)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-slate-500">Методы оплаты не добавлены.</p>
                        <p v-if="paymentMethodError" class="mt-3 text-xs text-rose-700">
                            {{ paymentMethodError }}
                        </p>
                        <p v-if="actionError?.payment_method" class="mt-2 text-xs text-rose-700">
                            {{ actionError.payment_method }}
                        </p>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <div v-if="paymentMethodOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: paymentMethodForm.processing }" @submit.prevent="submitPaymentMethod">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        {{ paymentMethodTarget ? 'Редактировать метод оплаты' : 'Добавить метод оплаты' }}
                    </h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closePaymentMethod"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[520px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Тип метода
                            <select
                                v-model="paymentMethodForm.type"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            >
                                <option v-for="option in paymentMethodTypes" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                        </label>
                        <div v-if="paymentMethodForm.errors.type" class="text-xs text-rose-700">
                            {{ paymentMethodForm.errors.type }}
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Название
                            <input
                                v-model="paymentMethodForm.label"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="text"
                                placeholder="Например, СБП"
                            />
                        </label>
                        <div v-if="paymentMethodForm.errors.label" class="text-xs text-rose-700">
                            {{ paymentMethodForm.errors.label }}
                        </div>

                        <template v-if="paymentMethodForm.type === 'sbp'">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Телефон
                                <input
                                    v-model="paymentMethodForm.phone"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="text"
                                    placeholder="+7 900 000-00-00"
                                />
                            </label>
                            <div v-if="paymentMethodForm.errors.phone" class="text-xs text-rose-700">
                                {{ paymentMethodForm.errors.phone }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Отображаемое имя
                                <input
                                    v-model="paymentMethodForm.display_name"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="text"
                                    placeholder="Иван Иванов"
                                />
                            </label>
                            <div v-if="paymentMethodForm.errors.display_name" class="text-xs text-rose-700">
                                {{ paymentMethodForm.errors.display_name }}
                            </div>
                        </template>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Порядок сортировки
                            <input
                                v-model.number="paymentMethodForm.sort_order"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="number"
                                min="0"
                            />
                        </label>
                        <div v-if="paymentMethodForm.errors.sort_order" class="text-xs text-rose-700">
                            {{ paymentMethodForm.errors.sort_order }}
                        </div>

                        <label class="flex items-center gap-2 text-xs uppercase tracking-[0.15em] text-slate-500">
                            <input
                                v-model="paymentMethodForm.is_active"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-slate-900"
                            />
                            Активен
                        </label>
                        <div v-if="paymentMethodForm.errors.is_active" class="text-xs text-rose-700">
                            {{ paymentMethodForm.errors.is_active }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="paymentMethodForm.processing"
                        @click="closePaymentMethod"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                        type="submit"
                        :disabled="paymentMethodForm.processing"
                    >
                        Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
