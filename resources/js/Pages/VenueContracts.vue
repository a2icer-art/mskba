<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
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
    contracts: {
        type: Array,
        default: () => [],
    },
    availablePermissions: {
        type: Array,
        default: () => [],
    },
    contractTypes: {
        type: Array,
        default: () => [],
    },
    contractModeration: {
        type: Object,
        default: () => ({}),
    },
    canAssignContracts: {
        type: Boolean,
        default: false,
    },
    canViewContracts: {
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
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const canViewContracts = computed(() => props.canViewContracts);
const hasContracts = computed(() => canViewContracts.value && (props.contracts?.length > 0));
const assignOpen = ref(false);
const editOpen = ref(false);
const editTarget = ref(null);
const requestOpen = ref(false);
const requestTargetType = ref('');
const getTodayValue = () => new Date().toISOString().slice(0, 10);
const assignForm = useForm({
    login: '',
    user_id: '',
    name: '',
    contract_type: '',
    starts_at: getTodayValue(),
    ends_at: '',
    comment: '',
    permissions: [],
});
const requestForm = useForm({
    contract_type: '',
    comment: '',
});
const editForm = useForm({
    permissions: [],
});
const revokeForm = useForm({});
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.contract ?? '');
const userSuggestLoading = ref(false);
const userSuggestError = ref('');
const userSuggestions = ref([]);
let userSuggestTimer = null;
let userSuggestRequestId = 0;
const isAssignDisabled = computed(() => {
    if (assignForm.processing) {
        return true;
    }
    if (!assignForm.user_id) {
        return true;
    }
    if (!assignForm.contract_type) {
        return true;
    }
    return !assignForm.starts_at;
});
const filterPermissionsByType = (contractType, options = {}) => {
    if (!contractType) {
        return [];
    }
    if (options.ignoreTypeWhenCreator && contractType === 'creator') {
        return props.availablePermissions;
    }
    return props.availablePermissions.filter((permission) => {
        if (!permission.allowed_types) {
            return true;
        }
        return permission.allowed_types.includes(contractType);
    });
};
const allowedPermissions = computed(() => filterPermissionsByType(assignForm.contract_type));
const editAllowedPermissions = computed(() =>
    filterPermissionsByType(editTarget.value?.contract_type, { ignoreTypeWhenCreator: true })
);
const isEditDisabled = computed(() => {
    if (editForm.processing) {
        return true;
    }
    return !editTarget.value;
});
const contractTypeLabels = {
    creator: 'Создатель',
    owner: 'Владелец',
    supervisor: 'Супервайзер',
    employee: 'Сотрудник',
};
const requestStatusLabels = {
    pending: 'На рассмотрении',
    approved: 'Подтверждено',
    clarification: 'Требуются уточнения',
    rejected: 'Отклонено',
};
const contractModeration = computed(() => props.contractModeration ?? {});
const ownerRequestState = computed(() => contractModeration.value.owner ?? {});
const supervisorRequestState = computed(() => contractModeration.value.supervisor ?? {});
const ownerRequest = computed(() => ownerRequestState.value.request ?? null);
const supervisorRequest = computed(() => supervisorRequestState.value.request ?? null);

const formatContractType = (value) => {
    if (!value) {
        return '—';
    }
    return contractTypeLabels[value] || value;
};

const openAssign = () => {
    assignForm.clearErrors();
    if (!assignForm.starts_at) {
        assignForm.starts_at = getTodayValue();
    }
    assignOpen.value = true;
};

const getContractPermissions = (contract) => {
    if (Array.isArray(contract?.permissions)) {
        return contract.permissions;
    }
    if (contract?.permissions && typeof contract.permissions === 'object') {
        return Object.values(contract.permissions);
    }
    return [];
};

const openEdit = (contract) => {
    editForm.clearErrors();
    editTarget.value = contract;
    editForm.permissions = getContractPermissions(contract).map((permission) => permission.code);
    editOpen.value = true;
};

watch(
    () => assignForm.contract_type,
    () => {
        if (!assignForm.permissions.length) {
            return;
        }
        const allowedCodes = new Set(allowedPermissions.value.map((permission) => permission.code));
        assignForm.permissions = assignForm.permissions.filter((code) => allowedCodes.has(code));
    }
);

const closeAssign = () => {
    assignForm.reset('login', 'user_id', 'name', 'contract_type', 'starts_at', 'ends_at', 'comment', 'permissions');
    assignForm.clearErrors();
    userSuggestions.value = [];
    userSuggestError.value = '';
    assignOpen.value = false;
};

const closeEdit = () => {
    editForm.reset('permissions');
    editForm.clearErrors();
    editTarget.value = null;
    editOpen.value = false;
};

const submitAssign = () => {
    assignForm.post(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/contracts`, {
        preserveScroll: true,
        onSuccess: () => {
            assignForm.reset('login', 'user_id', 'name', 'contract_type', 'starts_at', 'ends_at', 'comment', 'permissions');
            userSuggestions.value = [];
            userSuggestError.value = '';
            assignOpen.value = false;
        },
    });
};

const submitEditPermissions = () => {
    if (!editTarget.value || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }

    editForm.patch(
        `/venues/${props.activeTypeSlug}/${props.venue.alias}/contracts/${editTarget.value.id}/permissions`,
        {
            preserveScroll: true,
            onSuccess: () => {
                editTarget.value = null;
                editOpen.value = false;
            },
        }
    );
};

const scheduleUserSuggestions = (value) => {
    assignForm.user_id = '';
    userSuggestError.value = '';
    if (userSuggestTimer) {
        clearTimeout(userSuggestTimer);
    }
    if (!value || value.trim().length < 2) {
        userSuggestLoading.value = false;
        userSuggestions.value = [];
        return;
    }
    userSuggestTimer = setTimeout(() => {
        fetchUserSuggestions(value.trim());
    }, 250);
};

const fetchUserSuggestions = async (query) => {
    const requestId = ++userSuggestRequestId;
    userSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/user-suggest?query=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('failed');
        }
        const data = await response.json();
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestions.value = data?.suggestions ?? [];
        userSuggestError.value = userSuggestions.value.length ? '' : 'Варианты не найдены.';
    } catch (error) {
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestions.value = [];
        userSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestLoading.value = false;
    }
};

const applyUserSuggestion = (suggestion) => {
    assignForm.login = suggestion.login;
    assignForm.user_id = suggestion.id;
    userSuggestions.value = [];
    userSuggestError.value = '';
};

const revokeContract = (contract) => {
    if (!contract?.id) {
        return;
    }

    revokeForm.post(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/contracts/${contract.id}/revoke`, {
        preserveScroll: true,
    });
};

const openRequest = (type) => {
    requestForm.clearErrors();
    requestForm.comment = '';
    requestForm.contract_type = type;
    requestTargetType.value = type;
    requestOpen.value = true;
};

const closeRequest = () => {
    requestOpen.value = false;
    requestTargetType.value = '';
    requestForm.reset('contract_type', 'comment');
    requestForm.clearErrors();
};

const submitRequest = () => {
    if (!requestTargetType.value) {
        return;
    }

    requestForm.post(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/contracts/moderation`, {
        preserveScroll: true,
        onSuccess: () => {
            closeRequest();
        },
    });
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
                            <h1 class="text-3xl font-semibold text-slate-900">Контракты</h1>
                        </div>
                        <button
                            v-if="canAssignContracts && canViewContracts"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openAssign"
                        >
                            Назначить контракт
                        </button>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Запросы на контракты</h2>
                            <p class="text-sm text-slate-500">
                                Подайте заявку на владельца или супервайзера через модерацию.
                            </p>
                        </div>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Владелец</h3>
                                        <p class="text-xs text-slate-500">Юрлицо, подтверждающее право владения.</p>
                                    </div>
                                    <span
                                        v-if="ownerRequestState.has_active"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                    >
                                        Назначен
                                    </span>
                                    <span
                                        v-else-if="ownerRequest"
                                        class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600"
                                        :class="{
                                            'border-amber-200 bg-amber-50 text-amber-800': ownerRequest.status === 'pending',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700': ownerRequest.status === 'approved',
                                            'border-sky-200 bg-sky-50 text-sky-700': ownerRequest.status === 'clarification',
                                            'border-rose-200 bg-rose-50 text-rose-700': ownerRequest.status === 'rejected',
                                        }"
                                    >
                                        {{ requestStatusLabels[ownerRequest.status] || '—' }}
                                    </span>
                                </div>
                                <div class="mt-3 text-sm text-slate-600">
                                    <div v-if="ownerRequest?.comment" class="mb-2">
                                        Комментарий: {{ ownerRequest.comment }}
                                    </div>
                                    <div
                                        v-if="ownerRequest?.reject_reason"
                                        :class="ownerRequest.status === 'clarification' ? 'text-sky-700' : 'text-rose-700'"
                                    >
                                        {{ ownerRequest.status === 'clarification' ? 'Требуются уточнения' : 'Отклонено' }}:
                                        {{ ownerRequest.reject_reason }}
                                    </div>
                                    <div v-else-if="!ownerRequest && !ownerRequestState.can_request" class="text-slate-500">
                                        {{ ownerRequestState.reason }}
                                    </div>
                                </div>
                                <button
                                    v-if="!ownerRequest && ownerRequestState.can_request"
                                    class="mt-4 rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    @click="openRequest('owner')"
                                >
                                    Запрос на владение
                                </button>
                            </div>

                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-slate-900">Супервайзер</h3>
                                        <p class="text-xs text-slate-500">Агент, отвечающий за бронирования.</p>
                                    </div>
                                    <span
                                        v-if="supervisorRequestState.has_active"
                                        class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                    >
                                        Назначен
                                    </span>
                                    <span
                                        v-else-if="supervisorRequest"
                                        class="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600"
                                        :class="{
                                            'border-amber-200 bg-amber-50 text-amber-800': supervisorRequest.status === 'pending',
                                            'border-emerald-200 bg-emerald-50 text-emerald-700': supervisorRequest.status === 'approved',
                                            'border-sky-200 bg-sky-50 text-sky-700': supervisorRequest.status === 'clarification',
                                            'border-rose-200 bg-rose-50 text-rose-700': supervisorRequest.status === 'rejected',
                                        }"
                                    >
                                        {{ requestStatusLabels[supervisorRequest.status] || '—' }}
                                    </span>
                                </div>
                                <div class="mt-3 text-sm text-slate-600">
                                    <div v-if="supervisorRequest?.comment" class="mb-2">
                                        Комментарий: {{ supervisorRequest.comment }}
                                    </div>
                                    <div
                                        v-if="supervisorRequest?.reject_reason"
                                        :class="supervisorRequest.status === 'clarification' ? 'text-sky-700' : 'text-rose-700'"
                                    >
                                        {{ supervisorRequest.status === 'clarification' ? 'Требуются уточнения' : 'Отклонено' }}:
                                        {{ supervisorRequest.reject_reason }}
                                    </div>
                                    <div v-else-if="!supervisorRequest && !supervisorRequestState.can_request" class="text-slate-500">
                                        {{ supervisorRequestState.reason }}
                                    </div>
                                </div>
                                <button
                                    v-if="!supervisorRequest && supervisorRequestState.can_request"
                                    class="mt-4 rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    @click="openRequest('supervisor')"
                                >
                                    Стать супервайзером
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="canViewContracts" class="mt-6">
                        <div v-if="hasContracts" class="grid gap-4">
                            <article
                                v-for="contract in contracts"
                                :key="contract.id"
                                class="rounded-2xl border bg-slate-50 p-4"
                                :class="contract.is_current_user ? 'border-emerald-300' : 'border-slate-200'"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-900">{{ contract.name || 'Контракт' }}</h2>
                                        <p class="mt-1 text-sm text-slate-600">
                                            Пользователь: {{ contract.user?.login || '—' }}
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full border border-slate-200 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.15em]"
                                            :class="contract.status === 'active'
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                                : 'border-slate-200 bg-slate-100 text-slate-600'"
                                        >
                                            {{ contract.status === 'active' ? 'Активен' : 'Неактивен' }}
                                        </span>
                                        <button
                                            v-if="contract.can_update_permissions && contract.status === 'active'"
                                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                                            type="button"
                                            :disabled="editForm.processing"
                                            @click="openEdit(contract)"
                                        >
                                            Редактировать права
                                        </button>
                                        <button
                                            v-if="contract.can_revoke && contract.status === 'active' && contract.contract_type !== 'creator'"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                            type="button"
                                            :disabled="revokeForm.processing"
                                            @click="revokeContract(contract)"
                                        >
                                            Аннулировать
                                        </button>
                                    </div>
                                </div>

                                <div class="mt-4 grid gap-2 text-sm text-slate-700">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Тип</span>
                                        <span>{{ formatContractType(contract.contract_type) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Начало</span>
                                        <span>{{ formatDate(contract.starts_at) }}</span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Окончание</span>
                                        <span>{{ formatDate(contract.ends_at) }}</span>
                                    </div>
                                    <div v-if="contract.comment" class="flex items-start justify-between gap-3">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                                        <span class="text-right">{{ contract.comment }}</span>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Права</p>
                                    <div v-if="getContractPermissions(contract).length" class="mt-2 flex flex-wrap gap-2">
                                        <span
                                            v-for="permission in getContractPermissions(contract)"
                                            :key="permission.code"
                                            class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600"
                                        >
                                            {{ permission.label }}
                                        </span>
                                    </div>
                                    <p v-else class="mt-2 text-sm text-slate-500">Права не назначены.</p>
                                </div>
                            </article>
                        </div>
                        <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                            Контракты не найдены.
                        </div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: editForm.processing }" @submit.prevent="submitEditPermissions">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Редактировать права</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeEdit"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Права для контракта: {{ editTarget?.name || 'Контракт' }}
                        </p>

                        <div class="mt-4 grid gap-3">
                            <div class="text-xs uppercase tracking-[0.15em] text-slate-500">Права</div>
                            <div class="max-h-[300px] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3">
                                <div v-if="editAllowedPermissions.length" class="grid gap-2 text-sm text-slate-700">
                                    <label
                                        v-for="permission in editAllowedPermissions"
                                        :key="permission.code"
                                        class="flex items-center gap-2"
                                    >
                                        <input
                                            v-model="editForm.permissions"
                                            :value="permission.code"
                                            type="checkbox"
                                            class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        />
                                        <span>{{ permission.label }}</span>
                                    </label>
                                </div>
                                <p v-else class="text-sm text-slate-500">Нет доступных прав для редактирования.</p>
                            </div>
                            <div v-if="editForm.errors.permissions" class="text-xs text-rose-700">
                                {{ editForm.errors.permissions }}
                            </div>
                            
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="editForm.processing"
                            @click="closeEdit"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="isEditDisabled"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="assignOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: assignForm.processing }" @submit.prevent="submitAssign">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Назначить контракт</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeAssign"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Укажите пользователя, тип контракта и права для данной площадки.
                        </p>

                        <div class="mt-4 grid gap-3">
                            <div class="relative">
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Логин пользователя
                                    <input
                                        v-model="assignForm.login"
                                        class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        :class="{ 'is-loading': userSuggestLoading }"
                                        type="text"
                                        placeholder="login"
                                        @input="scheduleUserSuggestions($event.target.value)"
                                    />
                                </label>
                                <input v-model="assignForm.user_id" type="hidden" />
                                <div v-if="assignForm.errors.login" class="text-xs text-rose-700">
                                    {{ assignForm.errors.login }}
                                </div>
                                <div v-if="assignForm.errors.user_id" class="text-xs text-rose-700">
                                    {{ assignForm.errors.user_id }}
                                </div>
                                <div v-if="userSuggestError" class="text-xs text-rose-700">
                                    {{ userSuggestError }}
                                </div>
                                <div
                                    v-else-if="!userSuggestLoading && userSuggestions.length"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in userSuggestions"
                                        :key="`${suggestion.id}-${index}`"
                                        class="flex w-full items-start gap-2 border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                        type="button"
                                        @click="applyUserSuggestion(suggestion)"
                                    >
                                        <span class="font-semibold">{{ suggestion.login }}</span>
                                        <span v-if="suggestion.email" class="text-xs text-slate-500">{{ suggestion.email }}</span>
                                    </button>
                                </div>
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Тип контракта
                                <select
                                    v-model="assignForm.contract_type"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    <option value="">Выберите тип</option>
                                    <option
                                        v-for="type in contractTypes.filter((item) => item.value !== 'creator')"
                                        :key="type.value"
                                        :value="type.value"
                                    >
                                        {{ type.label }}
                                    </option>
                                </select>
                            </label>
                            <div v-if="assignForm.errors.contract_type" class="text-xs text-rose-700">
                                {{ assignForm.errors.contract_type }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Название (опционально)
                                <input
                                    v-model="assignForm.name"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="text"
                                    placeholder="Например, Менеджер смены"
                                />
                            </label>

                            <div class="grid gap-3 md:grid-cols-2">
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Начало
                                    <input
                                        v-model="assignForm.starts_at"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        type="date"
                                    />
                                </label>
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Окончание
                                    <input
                                        v-model="assignForm.ends_at"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        type="date"
                                    />
                                </label>
                            </div>
                            <div v-if="assignForm.errors.starts_at || assignForm.errors.ends_at" class="text-xs text-rose-700">
                                {{ assignForm.errors.starts_at || assignForm.errors.ends_at }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Комментарий
                                <textarea
                                    v-model="assignForm.comment"
                                    class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                ></textarea>
                            </label>

                            <div>
                                <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Права</p>
                                <div v-if="allowedPermissions.length" class="mt-2 grid gap-2">
                                    <label
                                        v-for="permission in allowedPermissions"
                                        :key="permission.code"
                                        class="flex items-start gap-2 text-sm text-slate-700"
                                    >
                                        <input
                                            v-model="assignForm.permissions"
                                            type="checkbox"
                                            class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-300"
                                            :value="permission.code"
                                        />
                                        <span>{{ permission.label }}</span>
                                    </label>
                                </div>
                                <p v-else class="mt-2 text-sm text-slate-500">Список прав пуст.</p>
                                <div v-if="assignForm.errors.permissions" class="mt-2 text-xs text-rose-700">
                                    {{ assignForm.errors.permissions }}
                                </div>
                            </div>

                            
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="assignForm.processing"
                            @click="closeAssign"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="isAssignDisabled"
                        >
                            Назначить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="requestOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: requestForm.processing }" @submit.prevent="submitRequest">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">
                            Запросить {{ contractTypeLabels[requestTargetType] || 'контракт' }}
                        </h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeRequest"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Добавьте комментарий для модерации (необязательно).
                        </p>
                        <textarea
                            v-model="requestForm.comment"
                            class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            placeholder="Комментарий"
                        ></textarea>
                        <div v-if="requestForm.errors.comment" class="mt-2 text-xs text-rose-700">
                            {{ requestForm.errors.comment }}
                        </div>
                        <div v-if="requestForm.errors.contract_type" class="mt-2 text-xs text-rose-700">
                            {{ requestForm.errors.contract_type }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="requestForm.processing"
                            @click="closeRequest"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="requestForm.processing"
                        >
                            Отправить запрос
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
