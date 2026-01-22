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
    filters: {
        type: Object,
        default: () => ({ status: '', sort: 'submitted_at_desc' }),
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    sortOptions: {
        type: Array,
        default: () => [],
    },
    requests: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    permissions: {
        type: Array,
        default: () => [],
    },
    supervisorPermissionCodes: {
        type: Array,
        default: () => [],
    },
});

const navigationData = computed(() => props.navigation?.data ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const filterForm = useForm({
    status: props.filters?.status ?? '',
    sort: props.filters?.sort ?? 'submitted_at_desc',
});
const actionNotice = ref('');
const actionError = ref('');
const rejectOpen = ref(false);
const rejectTarget = ref(null);
const rejectForm = useForm({
    reason: '',
});
const clarifyOpen = ref(false);
const clarifyTarget = ref(null);
const clarifyForm = useForm({
    reason: '',
});
const approveForm = useForm({
    permissions: [],
    comment: '',
});
const approveOpen = ref(false);
const approveTarget = ref(null);
const viewOpen = ref(false);
const viewTarget = ref(null);
const page = usePage();

const statusLabelMap = {
    pending: 'На модерации',
    approved: 'Подтверждено',
    clarification: 'Требуются уточнения',
    rejected: 'Отклонено',
};

const contractTypeLabels = {
    owner: 'Владелец',
    supervisor: 'Супервайзер',
};

const formatDate = (value) => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('ru-RU');
};

const applyFilters = () => {
    router.get(
        '/admin/contracts-moderation',
        {
            status: filterForm.status,
            sort: filterForm.sort,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

watch(
    () => [filterForm.status, filterForm.sort],
    () => {
        applyFilters();
    }
);

const openApprove = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';
    approveTarget.value = requestItem;
    approveForm.permissions = [];
    approveForm.comment = '';
    approveForm.clearErrors();
    approveOpen.value = true;
};

const closeApprove = () => {
    approveOpen.value = false;
    approveTarget.value = null;
    approveForm.reset('permissions', 'comment');
    approveForm.clearErrors();
};

const submitApprove = () => {
    if (!approveTarget.value) {
        return;
    }

    approveForm.post(`/admin/contracts-moderation/${approveTarget.value.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Контракт подтвержден.';
            closeApprove();
        },
        onError: (errors) => {
            actionError.value = errors.moderation || approveForm.errors.permissions || 'Не удалось подтвердить заявку.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const openReject = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';
    rejectTarget.value = requestItem;
    rejectForm.reason = '';
    rejectForm.clearErrors();
    rejectOpen.value = true;
};

const closeReject = () => {
    rejectOpen.value = false;
    rejectTarget.value = null;
    rejectForm.reset('reason');
    rejectForm.clearErrors();
};

const openClarify = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';
    clarifyTarget.value = requestItem;
    clarifyForm.reason = '';
    clarifyForm.clearErrors();
    clarifyOpen.value = true;
};

const closeClarify = () => {
    clarifyOpen.value = false;
    clarifyTarget.value = null;
    clarifyForm.reset('reason');
    clarifyForm.clearErrors();
};

const submitClarify = () => {
    if (!clarifyTarget.value) {
        return;
    }

    clarifyForm.post(`/admin/contracts-moderation/${clarifyTarget.value.id}/clarify`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Запрошены уточнения по заявке.';
            closeClarify();
        },
        onError: (errors) => {
            actionError.value = errors.moderation || clarifyForm.errors.reason || 'Не удалось запросить уточнения.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const submitReject = () => {
    if (!rejectTarget.value) {
        return;
    }

    rejectForm.post(`/admin/contracts-moderation/${rejectTarget.value.id}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Заявка отклонена.';
            closeReject();
        },
        onError: (errors) => {
            actionError.value = errors.moderation || rejectForm.errors.reason || 'Не удалось отклонить заявку.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const openView = (requestItem) => {
    viewTarget.value = requestItem;
    viewOpen.value = true;
};

const closeView = () => {
    viewOpen.value = false;
    viewTarget.value = null;
};

const hasRequests = computed(() => (props.requests?.data?.length ?? 0) > 0);
const supervisorPermissionSet = computed(() => new Set(props.supervisorPermissionCodes || []));
const allowedPermissions = computed(() => {
    if (!approveTarget.value) {
        return [];
    }
    if (approveTarget.value.contract_type === 'supervisor') {
        return props.permissions.filter((permission) => supervisorPermissionSet.value.has(permission.code));
    }
    return props.permissions;
});
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
                    <h1 class="text-3xl font-semibold text-slate-900">Модерация контрактов</h1>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex flex-wrap items-center gap-4">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Статус
                                <select v-model="filterForm.status" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Сортировка
                                <select v-model="filterForm.sort" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                    <option v-for="option in sortOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>
                        </div>
                    </div>

                    <div v-if="hasRequests" class="mt-6 w-full overflow-x-auto rounded-2xl border border-slate-200">
                        <div class="min-w-max">
                            <div class="grid grid-cols-[200px_140px_140px_140px_220px_220px_160px_160px_160px] gap-4 bg-slate-50 px-4 py-3 text-xs uppercase tracking-[0.15em] text-slate-500 whitespace-nowrap">
                                <span>Площадка</span>
                                <span>Тип</span>
                                <span>Заявитель</span>
                                <span>Статус</span>
                                <span>Действия</span>
                                <span>Комментарий</span>
                                <span>Отправлено</span>
                                <span>Решение</span>
                                <span>Кто решил</span>
                            </div>

                            <div
                                v-for="requestItem in requests.data"
                                :key="requestItem.id"
                                class="grid grid-cols-[200px_140px_140px_140px_220px_220px_160px_160px_160px] gap-4 border-t border-slate-100 px-4 py-4 text-sm text-slate-700 whitespace-nowrap"
                            >
                                <div>
                                    <button
                                        class="text-left text-sm font-medium text-slate-800 transition hover:text-slate-700"
                                        type="button"
                                        @click="openView(requestItem)"
                                    >
                                        {{ requestItem.venue?.name || '—' }}
                                    </button>
                                </div>
                                <div>{{ contractTypeLabels[requestItem.contract_type] || '—' }}</div>
                                <div>{{ requestItem.submitter?.login || '—' }}</div>
                                <div>
                                    <span
                                        class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600"
                                        :class="{
                                            'border-amber-200 bg-amber-50 text-amber-800': requestItem.status === 'pending',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700': requestItem.status === 'approved',
                                            'border-sky-200 bg-sky-50 text-sky-700': requestItem.status === 'clarification',
                                            'border-rose-200 bg-rose-50 text-rose-700': requestItem.status === 'rejected',
                                        }"
                                        :title="requestItem.submitted_at ? formatDate(requestItem.submitted_at) : undefined"
                                    >
                                        {{ statusLabelMap[requestItem.status] || '—' }}
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <button
                                        v-if="requestItem.status === 'pending' || requestItem.status === 'clarification'"
                                        class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 transition hover:-translate-y-0.5 hover:border-emerald-400"
                                        type="button"
                                        :disabled="approveForm.processing"
                                        @click="openApprove(requestItem)"
                                    >
                                        Подтвердить
                                    </button>
                                    <button
                                        v-if="requestItem.status === 'pending' || requestItem.status === 'clarification'"
                                        class="rounded-full border border-sky-300 bg-sky-50 px-3 py-1 text-xs font-semibold text-sky-700 transition hover:-translate-y-0.5 hover:border-sky-400"
                                        type="button"
                                        :disabled="clarifyForm.processing"
                                        @click="openClarify(requestItem)"
                                    >
                                        Требуются уточнения
                                    </button>
                                    <button
                                        v-if="requestItem.status === 'pending' || requestItem.status === 'clarification'"
                                        class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                        type="button"
                                        :disabled="rejectForm.processing"
                                        @click="openReject(requestItem)"
                                    >
                                        Отклонить
                                    </button>
                                </div>
                                <div class="truncate" :title="requestItem.comment || ''">
                                    {{ requestItem.comment || '—' }}
                                </div>
                                <div>{{ requestItem.submitted_at ? formatDate(requestItem.submitted_at) : '—' }}</div>
                                <div>{{ requestItem.reviewed_at ? formatDate(requestItem.reviewed_at) : '—' }}</div>
                                <div>{{ requestItem.reviewer?.login || '—' }}</div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Заявки не найдены.
                    </div>

                    <div v-if="requests.links?.length" class="mt-6 flex flex-wrap items-center gap-2 text-sm">
                        <Link
                            v-for="link in requests.links"
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

        <div v-if="rejectOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: rejectForm.processing }" @submit.prevent="submitReject">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Отклонить заявку</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeReject"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Укажите причину отклонения. Она будет показана заявителю.
                        </p>
                        <textarea
                            v-model="rejectForm.reason"
                            class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            placeholder="Причина отклонения (необязательно)"
                        ></textarea>
                        <div v-if="rejectForm.errors.reason" class="mt-2 text-xs text-rose-700">
                            {{ rejectForm.errors.reason }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="rejectForm.processing"
                            @click="closeReject"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-700"
                            type="submit"
                            :disabled="rejectForm.processing"
                        >
                            Отклонить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="clarifyOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: clarifyForm.processing }" @submit.prevent="submitClarify">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Запросить уточнения</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeClarify"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Укажите, какие уточнения нужны. Комментарий будет показан заявителю.
                        </p>
                        <textarea
                            v-model="clarifyForm.reason"
                            class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            placeholder="Комментарий (необязательно)"
                        ></textarea>
                        <div v-if="clarifyForm.errors.reason" class="mt-2 text-xs text-rose-700">
                            {{ clarifyForm.errors.reason }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="clarifyForm.processing"
                            @click="closeClarify"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-sky-600 bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-sky-700"
                            type="submit"
                            :disabled="clarifyForm.processing"
                        >
                            Запросить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="approveOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-xl rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: approveForm.processing }" @submit.prevent="submitApprove">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Подтвердить контракт</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeApprove"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[520px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Заявка будет подтверждена, а пользователю назначится контракт.
                        </p>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Площадка</span>
                                <span class="font-semibold">{{ approveTarget?.venue?.name || '—' }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Тип</span>
                                    <span>{{ contractTypeLabels[approveTarget?.contract_type] || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Заявитель</span>
                                    <span>{{ approveTarget?.submitter?.login || '—' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 grid gap-3">
                            <div class="text-xs uppercase tracking-[0.15em] text-slate-500">Права</div>
                            <div class="max-h-[260px] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3">
                                <div v-if="allowedPermissions.length" class="grid gap-2 text-sm text-slate-700">
                                    <label
                                        v-for="permission in allowedPermissions"
                                        :key="permission.code"
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            v-model="approveForm.permissions"
                                            :value="permission.code"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        />
                                        <span>{{ permission.label }}</span>
                                    </label>
                                </div>
                                <p v-else class="text-sm text-slate-500">Нет доступных прав для назначения.</p>
                            </div>
                            <div v-if="approveForm.errors.permissions" class="text-xs text-rose-700">
                                {{ approveForm.errors.permissions }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Комментарий
                                <textarea
                                    v-model="approveForm.comment"
                                    class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                ></textarea>
                            </label>
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="approveForm.processing"
                            @click="closeApprove"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700"
                            type="submit"
                            :disabled="approveForm.processing"
                        >
                            Подтвердить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="viewOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Данные заявки</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeView"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Площадка</span>
                            <span class="font-semibold">{{ viewTarget?.venue?.name || '—' }}</span>
                        </div>
                        <div class="mt-3 grid gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Тип контракта</span>
                                <span>{{ contractTypeLabels[viewTarget?.contract_type] || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Заявитель</span>
                                <span>{{ viewTarget?.submitter?.login || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                                <span>{{ viewTarget?.comment || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Решение</span>
                                <span>{{ viewTarget?.review_comment || viewTarget?.reject_reason || '—' }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeView"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
