<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
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
    canAssignContracts: {
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
});

const page = usePage();
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasContracts = computed(() => props.contracts?.length > 0);
const assignOpen = ref(false);
const editOpen = ref(false);
const editTarget = ref(null);
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
const filterPermissionsByType = (contractType) => {
    if (!contractType) {
        return [];
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
    filterPermissionsByType(editTarget.value?.contract_type)
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
    manager: 'Менеджер',
    controller: 'Контролер',
    employee: 'Сотрудник',
};

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

const openEdit = (contract) => {
    editForm.clearErrors();
    editTarget.value = contract;
    editForm.permissions = (contract.permissions ?? []).map((permission) => permission.code);
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
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <section class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Администрация</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Контракты</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Площадка: {{ venue?.name || '—' }}
                            </p>
                        </div>
                        <Link
                            v-if="venue?.alias && activeTypeSlug"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            :href="`/venues/${activeTypeSlug}/${venue.alias}`"
                        >
                            Вернуться
                        </Link>
                    </div>

                    <div v-if="canAssignContracts" class="mt-6 flex justify-end">
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openAssign"
                        >
                            Назначить контракт
                        </button>
                    </div>

                    <div v-if="hasContracts" class="mt-6 grid gap-4">
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
                                        v-if="contract.can_update_permissions && contract.status === 'active' && contract.contract_type !== 'creator'"
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
                                <div v-if="contract.permissions?.length" class="mt-2 flex flex-wrap gap-2">
                                    <span
                                        v-for="permission in contract.permissions"
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
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                        Контракты не найдены.
                    </div>
                </div>
            </section>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <form :class="{ loading: editForm.processing }" @submit.prevent="submitEditPermissions">
                <h2 class="text-lg font-semibold text-slate-900">Редактировать права</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Права для контракта: {{ editTarget?.name || 'Контракт' }}
                </p>

                <div class="mt-4 grid gap-3">
                    <div class="text-xs uppercase tracking-[0.15em] text-slate-500">Права</div>
                    <div
                        class="max-h-[300px] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-3"
                    >
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
                    <div v-if="actionError" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        {{ actionError }}
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="editForm.processing"
                        @click="closeEdit"
                    >
                        Отмена
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
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <form :class="{ loading: assignForm.processing }" @submit.prevent="submitAssign">
                <h2 class="text-lg font-semibold text-slate-900">Назначить контракт</h2>
                <p class="mt-2 text-sm text-slate-600">
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

                    <div v-if="actionError" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        {{ actionError }}
                    </div>
                    <div v-else-if="actionNotice" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                        {{ actionNotice }}
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="assignForm.processing"
                        @click="closeAssign"
                    >
                        Отмена
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
    </main>
</template>
