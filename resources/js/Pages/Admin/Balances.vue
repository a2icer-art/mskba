<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../../Components/Breadcrumbs.vue';
import MainFooter from '../../Components/MainFooter.vue';
import MainHeader from '../../Components/MainHeader.vue';
import MainSidebar from '../../Components/MainSidebar.vue';
import SystemNoticeStack from '../../Components/SystemNoticeStack.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Разделы', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    users: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    filters: {
        type: Object,
        default: () => ({ q: '', status: '' }),
    },
});

const navigationData = computed(() => props.navigation?.data ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const page = usePage();
const actionNotice = ref('');
const actionError = ref('');

const filterForm = useForm({
    q: props.filters?.q ?? '',
    status: props.filters?.status ?? '',
});

const applyFilters = () => {
    router.get(
        '/admin/balances',
        {
            q: filterForm.q,
            status: filterForm.status,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

watch(
    () => [filterForm.q, filterForm.status],
    () => {
        applyFilters();
    }
);

const formatAmount = (amount, currency) => {
    const value = Number(amount ?? 0);
    const display = Number.isNaN(value) ? 0 : value;
    return `${display.toLocaleString('ru-RU')} ${currency || 'RUB'}`;
};

const formatStatus = (status) => (status === 'blocked' ? 'Заблокирован' : 'Активен');

const topUpOpen = ref(false);
const debitOpen = ref(false);
const blockOpen = ref(false);
const activeUser = ref(null);

const topUpForm = useForm({ amount: '' });
const debitForm = useForm({ amount: '' });
const blockForm = useForm({ reason: '' });
const unblockForm = useForm({});

const openTopUp = (user) => {
    actionNotice.value = '';
    actionError.value = '';
    activeUser.value = user;
    topUpForm.reset();
    topUpOpen.value = true;
};

const openDebit = (user) => {
    actionNotice.value = '';
    actionError.value = '';
    activeUser.value = user;
    debitForm.reset();
    debitOpen.value = true;
};

const openBlock = (user) => {
    actionNotice.value = '';
    actionError.value = '';
    activeUser.value = user;
    blockForm.reset();
    blockOpen.value = true;
};

const closeTopUp = () => {
    topUpOpen.value = false;
};

const closeDebit = () => {
    debitOpen.value = false;
};

const closeBlock = () => {
    blockOpen.value = false;
};

const submitTopUp = () => {
    if (!activeUser.value) {
        return;
    }
    topUpForm.post(`/admin/balances/${activeUser.value.id}/top-up`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Баланс пополнен.';
            closeTopUp();
        },
        onError: (errors) => {
            actionError.value = errors.amount || 'Не удалось пополнить баланс.';
        },
        onFinish: () => {
            if (page.props?.errors?.amount) {
                actionError.value = page.props.errors.amount;
            }
        },
    });
};

const submitDebit = () => {
    if (!activeUser.value) {
        return;
    }
    debitForm.post(`/admin/balances/${activeUser.value.id}/debit`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Списание выполнено.';
            closeDebit();
        },
        onError: (errors) => {
            actionError.value = errors.amount || 'Не удалось списать средства.';
        },
        onFinish: () => {
            if (page.props?.errors?.amount) {
                actionError.value = page.props.errors.amount;
            }
        },
    });
};

const submitBlock = () => {
    if (!activeUser.value) {
        return;
    }
    blockForm.post(`/admin/balances/${activeUser.value.id}/block`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Баланс заблокирован.';
            closeBlock();
        },
        onError: (errors) => {
            actionError.value = errors.reason || 'Не удалось заблокировать баланс.';
        },
        onFinish: () => {
            if (page.props?.errors?.reason) {
                actionError.value = page.props.errors.reason;
            }
        },
    });
};

const submitUnblock = (user) => {
    actionNotice.value = '';
    actionError.value = '';
    unblockForm.post(`/admin/balances/${user.id}/unblock`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Баланс разблокирован.';
        },
        onError: () => {
            actionError.value = 'Не удалось разблокировать баланс.';
        },
    });
};
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
                    <h1 class="text-3xl font-semibold text-slate-900">Балансы пользователей</h1>

                    <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                        <input
                            v-model="filterForm.q"
                            class="w-full rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700 md:w-64"
                            type="text"
                            placeholder="Поиск по логину или ID"
                        />
                        <select
                            v-model="filterForm.status"
                            class="rounded-2xl border border-slate-200 px-4 py-2 text-sm text-slate-700"
                        >
                            <option value="">Все статусы</option>
                            <option value="active">Активные</option>
                            <option value="blocked">Заблокированные</option>
                        </select>
                    </div>

                    <div class="mt-6 overflow-hidden rounded-2xl border border-slate-200 bg-white">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">ID</th>
                                    <th class="px-4 py-3">Пользователь</th>
                                    <th class="px-4 py-3">Доступно</th>
                                    <th class="px-4 py-3">В удержании</th>
                                    <th class="px-4 py-3">Статус</th>
                                    <th class="px-4 py-3 text-right">Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="user in users.data" :key="user.id" class="border-t border-slate-100">
                                    <td class="px-4 py-3 text-slate-600">{{ user.id }}</td>
                                    <td class="px-4 py-3 font-semibold text-slate-800">{{ user.login }}</td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ formatAmount(user.balance.available_amount, user.balance.currency) }}
                                    </td>
                                    <td class="px-4 py-3 text-slate-700">
                                        {{ formatAmount(user.balance.held_amount, user.balance.currency) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-col gap-1">
                                            <span
                                                class="inline-flex w-fit items-center rounded-full border px-3 py-1 text-xs font-semibold"
                                                :class="user.balance.status === 'blocked'
                                                    ? 'border-rose-200 bg-rose-50 text-rose-700'
                                                    : 'border-emerald-200 bg-emerald-50 text-emerald-700'"
                                            >
                                                {{ formatStatus(user.balance.status) }}
                                            </span>
                                            <span v-if="user.balance.block_reason" class="text-xs text-rose-700">
                                                {{ user.balance.block_reason }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap justify-end gap-2">
                                            <button
                                                class="rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 transition hover:-translate-y-0.5 hover:border-emerald-300"
                                                type="button"
                                                @click="openTopUp(user)"
                                            >
                                                Пополнить
                                            </button>
                                            <button
                                                class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700 transition hover:-translate-y-0.5 hover:border-amber-300"
                                                type="button"
                                                @click="openDebit(user)"
                                            >
                                                Списать
                                            </button>
                                            <button
                                                v-if="user.balance.status !== 'blocked'"
                                                class="rounded-full border border-rose-200 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-300"
                                                type="button"
                                                @click="openBlock(user)"
                                            >
                                                Блокировать
                                            </button>
                                            <button
                                                v-else
                                                class="rounded-full border border-slate-200 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                                                type="button"
                                                @click="submitUnblock(user)"
                                            >
                                                Разблокировать
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!users.data.length">
                                    <td class="px-4 py-6 text-center text-sm text-slate-500" colspan="6">
                                        Пользователи не найдены.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div v-if="users.links?.length" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            v-for="link in users.links"
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

        <div v-if="topUpOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeTopUp">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form class="popup-body pt-4" @submit.prevent="submitTopUp">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Пополнение баланса</h2>
                        <button class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500" type="button" @click="closeTopUp">
                            x
                        </button>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-slate-600">
                            Пользователь: <span class="font-semibold text-slate-900">{{ activeUser?.login }}</span>
                        </p>
                        <label class="mt-4 flex flex-col gap-2 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Сумма
                            <input
                                v-model="topUpForm.amount"
                                type="number"
                                min="1"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            />
                        </label>
                        <div v-if="topUpForm.errors.amount" class="mt-2 text-xs text-rose-700">
                            {{ topUpForm.errors.amount }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600" type="button" @click="closeTopUp">
                            Закрыть
                        </button>
                        <button class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white" type="submit">
                            Пополнить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="debitOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeDebit">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form class="popup-body pt-4" @submit.prevent="submitDebit">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Списание средств</h2>
                        <button class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500" type="button" @click="closeDebit">
                            x
                        </button>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-slate-600">
                            Пользователь: <span class="font-semibold text-slate-900">{{ activeUser?.login }}</span>
                        </p>
                        <label class="mt-4 flex flex-col gap-2 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Сумма
                            <input
                                v-model="debitForm.amount"
                                type="number"
                                min="1"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            />
                        </label>
                        <div v-if="debitForm.errors.amount" class="mt-2 text-xs text-rose-700">
                            {{ debitForm.errors.amount }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600" type="button" @click="closeDebit">
                            Закрыть
                        </button>
                        <button class="rounded-full border border-amber-500 bg-amber-500 px-4 py-2 text-sm font-semibold text-white" type="submit">
                            Списать
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="blockOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeBlock">
            <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form class="popup-body pt-4" @submit.prevent="submitBlock">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Блокировка баланса</h2>
                        <button class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500" type="button" @click="closeBlock">
                            x
                        </button>
                    </div>
                    <div class="px-6 py-4">
                        <p class="text-sm text-slate-600">
                            Пользователь: <span class="font-semibold text-slate-900">{{ activeUser?.login }}</span>
                        </p>
                        <label class="mt-4 flex flex-col gap-2 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Причина блокировки
                            <textarea
                                v-model="blockForm.reason"
                                rows="4"
                                class="rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                            ></textarea>
                        </label>
                        <div v-if="blockForm.errors.reason" class="mt-2 text-xs text-rose-700">
                            {{ blockForm.errors.reason }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600" type="button" @click="closeBlock">
                            Закрыть
                        </button>
                        <button class="rounded-full border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white" type="submit">
                            Заблокировать
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
