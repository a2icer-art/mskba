<script setup>
import { computed, ref, watch } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../../Components/Breadcrumbs.vue';
import MainFooter from '../../Components/MainFooter.vue';
import MainHeader from '../../Components/MainHeader.vue';
import MainSidebar from '../../Components/MainSidebar.vue';

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
    permissionGroups: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
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
const approveForm = useForm({
    permissions: [],
});
const blockForm = useForm({
    reason: '',
});
const unblockForm = useForm({});
const approveOpen = ref(false);
const approveTarget = ref(null);
const blockOpen = ref(false);
const blockTarget = ref(null);
const viewOpen = ref(false);
const viewTarget = ref(null);
const viewPermissionsForm = useForm({
    permissions: [],
});
const page = usePage();

const statusLabelMap = {
    pending: 'На модерации',
    approved: 'Подтверждено',
    rejected: 'Отклонено',
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
        '/admin/users-moderation',
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

const approveRequest = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';

    approveForm.post(`/admin/users-moderation/${requestItem.id}/approve`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Пользователь подтвержден.';
        },
        onError: (errors) => {
            actionError.value = errors.moderation || 'Не удалось подтвердить заявку.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const blockUser = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';

    blockForm.post(`/admin/users-moderation/${requestItem.id}/block`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Пользователь заблокирован.';
        },
        onError: (errors) => {
            actionError.value = errors.moderation || blockForm.errors.reason || 'Не удалось заблокировать пользователя.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const unblockUser = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';

    unblockForm.post(`/admin/users-moderation/${requestItem.id}/unblock`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Пользователь разблокирован.';
        },
        onError: (errors) => {
            actionError.value = errors.moderation || 'Не удалось разблокировать пользователя.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const openBlock = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';
    blockTarget.value = requestItem;
    blockForm.reason = '';
    blockForm.clearErrors();
    blockOpen.value = true;
};

const closeBlock = () => {
    blockOpen.value = false;
    blockTarget.value = null;
    blockForm.reset('reason');
    blockForm.clearErrors();
};

const submitBlock = () => {
    if (!blockTarget.value) {
        return;
    }

    blockUser(blockTarget.value);
    closeBlock();
};

const openView = (requestItem) => {
    viewTarget.value = requestItem;
    viewPermissionsForm.permissions = requestItem?.assigned_permissions ? [...requestItem.assigned_permissions] : [];
    viewPermissionsForm.clearErrors();
    viewOpen.value = true;
};

const closeView = () => {
    viewOpen.value = false;
    viewTarget.value = null;
    viewPermissionsForm.reset('permissions');
    viewPermissionsForm.clearErrors();
};


const openApprove = (requestItem) => {
    actionNotice.value = '';
    actionError.value = '';
    approveTarget.value = requestItem;
    approveForm.permissions = requestItem?.assigned_permissions ? [...requestItem.assigned_permissions] : [];
    approveForm.clearErrors();
    approveOpen.value = true;
};

const closeApprove = () => {
    approveOpen.value = false;
    approveTarget.value = null;
    approveForm.reset('permissions');
    approveForm.clearErrors();
};

const submitApprove = () => {
    if (!approveTarget.value) {
        return;
    }

    approveRequest(approveTarget.value);
    closeApprove();
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

const submitReject = () => {
    if (!rejectTarget.value) {
        return;
    }

    rejectForm.post(`/admin/users-moderation/${rejectTarget.value.id}/reject`, {
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

const submitViewPermissions = () => {
    if (!viewTarget.value) {
        return;
    }

    actionNotice.value = '';
    actionError.value = '';

    viewPermissionsForm.post(`/admin/users-moderation/${viewTarget.value.id}/permissions`, {
        preserveScroll: true,
        onSuccess: () => {
            actionNotice.value = 'Права пользователя обновлены.';
            if (viewTarget.value) {
                viewTarget.value.assigned_permissions = [...viewPermissionsForm.permissions];
            }
        },
        onError: (errors) => {
            actionError.value = errors.moderation || viewPermissionsForm.errors.permissions || 'Не удалось обновить права.';
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                actionError.value = page.props.errors.moderation;
            }
        },
    });
};

const hasRequests = computed(() => (props.requests?.data?.length ?? 0) > 0);
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
                    <h1 class="text-3xl font-semibold text-slate-900">Модерация пользователей</h1>

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

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                    <div v-else-if="actionError" class="mt-4 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700">
                        {{ actionError }}
                    </div>

                    <div v-if="hasRequests" class="mt-6 w-full overflow-x-auto rounded-2xl border border-slate-200">
                        <div class="min-w-max">
                        <div class="grid grid-cols-[120px_140px_200px_70px_140px_140px_100px_140px_180px_160px_160px_160px] gap-4 bg-slate-50 px-4 py-3 text-xs uppercase tracking-[0.15em] text-slate-500 whitespace-nowrap">
                            <span>Логин</span>
                            <span>Статус</span>
                            <span>Действия</span>
                            <span>ID</span>
                            <span>Фамилия</span>
                            <span>Имя</span>
                            <span>Пол</span>
                            <span>Дата рождения</span>
                            <span>Контакт</span>
                            <span>Отправлено</span>
                            <span>Решение</span>
                            <span>Кто решил</span>
                        </div>

                        <div v-for="requestItem in requests.data" :key="requestItem.id" class="grid grid-cols-[120px_140px_200px_70px_140px_140px_100px_140px_180px_160px_160px_160px] gap-4 border-t border-slate-100 px-4 py-4 text-sm text-slate-700 whitespace-nowrap">
                            <div>
                                <button
                                    class="text-left text-sm font-medium text-slate-800 transition hover:text-slate-700"
                                    type="button"
                                    @click="openView(requestItem)"
                                >
                                    {{ requestItem.user?.login || '—' }}
                                </button>
                            </div>
                            <div>
                                <span
                                    class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600"
                                    :class="{
                                        'border-amber-200 bg-amber-50 text-amber-800': requestItem.status === 'pending',
                                        'border-emerald-200 bg-emerald-50 text-emerald-700': requestItem.status === 'approved',
                                        'border-rose-200 bg-rose-50 text-rose-700': requestItem.status === 'rejected',
                                    }"
                                    :title="requestItem.submitted_at ? formatDate(requestItem.submitted_at) : undefined"
                                >
                                    {{ statusLabelMap[requestItem.status] || '—' }}
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    v-if="requestItem.status === 'pending'"
                                    class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 transition hover:-translate-y-0.5 hover:border-emerald-400"
                                    type="button"
                                    :disabled="approveForm.processing"
                                    @click="openApprove(requestItem)"
                                >
                                    Подтвердить
                                </button>
                                <button
                                    v-if="requestItem.status === 'pending'"
                                    class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                    type="button"
                                    :disabled="rejectForm.processing"
                                    @click="openReject(requestItem)"
                                >
                                    Отклонить
                                </button>
                                <button
                                    v-if="requestItem.status === 'approved' && requestItem.user?.status === 'confirmed'"
                                    class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                    type="button"
                                    :disabled="blockForm.processing"
                                    @click="openBlock(requestItem)"
                                >
                                    Заблокировать
                                </button>
                                <button
                                    v-if="requestItem.status === 'approved' && requestItem.user?.status === 'blocked'"
                                    class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 transition hover:-translate-y-0.5 hover:border-emerald-400"
                                    type="button"
                                    :disabled="unblockForm.processing"
                                    @click="unblockUser(requestItem)"
                                >
                                    Разблокировать
                                </button>
                            </div>
                            <div class="font-semibold text-slate-800">#{{ requestItem.id }}</div>
                            <div>{{ requestItem.profile?.last_name || '—' }}</div>
                            <div>{{ requestItem.profile?.first_name || '—' }}</div>
                            <div>{{ requestItem.profile?.gender || '—' }}</div>
                            <div>{{ requestItem.profile?.birth_date || '—' }}</div>
                            <div class="flex items-center gap-2">
                                <span>{{ requestItem.contact?.value || '—' }}</span>
                                <span
                                    v-if="requestItem.contact?.confirmed_at"
                                    class="text-emerald-600"
                                    :title="formatDate(requestItem.contact.confirmed_at)"
                                >
                                    ✓
                                </span>
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
                            Вы можете указать причину отклонения. Она будет показана пользователю.
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

        <div v-if="approveOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: approveForm.processing }" @submit.prevent="submitApprove">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Подтвердить пользователя</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeApprove"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Пользователь будет подтвержден, а заявка переведена в статус "Подтверждено".
                        </p>
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пользователь</span>
                                <span class="font-semibold">{{ approveTarget?.user?.login || '—' }}</span>
                            </div>
                            <div class="mt-3 grid gap-2 text-sm">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Фамилия</span>
                                    <span>{{ approveTarget?.profile?.last_name || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Имя</span>
                                    <span>{{ approveTarget?.profile?.first_name || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пол</span>
                                    <span>{{ approveTarget?.profile?.gender || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата рождения</span>
                                    <span>{{ approveTarget?.profile?.birth_date || '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Контакт</span>
                                    <span class="flex items-center gap-2">
                                        {{ approveTarget?.contact?.value || '—' }}
                                        <span
                                            v-if="approveTarget?.contact?.confirmed_at"
                                            class="text-emerald-600"
                                            :title="formatDate(approveTarget?.contact?.confirmed_at)"
                                        >
                                            ✓
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4">
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Права после подтверждения</p>
                            <p class="mt-2 text-xs text-slate-500">
                                Выберите права, которые будут назначены пользователю после подтверждения.
                            </p>
                            <div v-if="permissionGroups.length" class="mt-3 max-h-[300px] overflow-y-auto pr-2">
                                <div class="grid gap-4">
                                <div v-for="group in permissionGroups" :key="group.key" class="rounded-2xl border border-slate-200 bg-white px-4 py-3">
                                    <p class="text-sm font-semibold text-slate-800">{{ group.title }}</p>
                                    <div class="mt-3 grid gap-2">
                                        <label
                                            v-for="permission in group.items"
                                            :key="permission.code"
                                            class="flex items-start gap-2 text-sm text-slate-700"
                                        >
                                            <input
                                                v-model="approveForm.permissions"
                                                type="checkbox"
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-300"
                                                :value="permission.code"
                                            />
                                            <span>{{ permission.label }}</span>
                                        </label>
                                    </div>
                                </div>
                                </div>
                            </div>
                            <div v-else class="mt-2 text-sm text-slate-500">
                                Права не загружены.
                            </div>
                            <div v-if="approveForm.errors.permissions" class="mt-2 text-xs text-rose-700">
                                {{ approveForm.errors.permissions }}
                            </div>
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

        <div v-if="blockOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: blockForm.processing }" @submit.prevent="submitBlock">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Заблокировать пользователя</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeBlock"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">
                            Укажите причину блокировки (необязательно). Она может быть использована в коммуникации с пользователем.
                        </p>
                        <textarea
                            v-model="blockForm.reason"
                            class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            placeholder="Причина блокировки (необязательно)"
                        ></textarea>
                        <div v-if="blockForm.errors.reason" class="mt-2 text-xs text-rose-700">
                            {{ blockForm.errors.reason }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="blockForm.processing"
                            @click="closeBlock"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-700"
                            type="submit"
                            :disabled="blockForm.processing"
                        >
                            Заблокировать
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="viewOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Данные пользователя</h2>
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
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пользователь</span>
                            <span class="font-semibold">{{ viewTarget?.user?.login || '—' }}</span>
                        </div>
                        <div class="mt-3 grid gap-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Фамилия</span>
                                <span>{{ viewTarget?.profile?.last_name || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Имя</span>
                                <span>{{ viewTarget?.profile?.first_name || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пол</span>
                                <span>{{ viewTarget?.profile?.gender || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата рождения</span>
                                <span>{{ viewTarget?.profile?.birth_date || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Контакт</span>
                                <span class="flex items-center gap-2">
                                    {{ viewTarget?.contact?.value || '—' }}
                                    <span
                                        v-if="viewTarget?.contact?.confirmed_at"
                                        class="text-emerald-600"
                                        :title="formatDate(viewTarget?.contact?.confirmed_at)"
                                    >
                                        ✓
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Права пользователя</p>
                        <div v-if="permissionGroups.length" class="mt-2 max-h-[240px] overflow-y-auto pr-2">
                            <div class="grid gap-4">
                                <div
                                    v-for="group in permissionGroups"
                                    :key="group.key"
                                    class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                >
                                    <p class="text-sm font-semibold text-slate-800">{{ group.title }}</p>
                                    <div class="mt-3 grid gap-2">
                                        <label
                                            v-for="permission in group.items"
                                            :key="permission.code"
                                            class="flex items-start gap-2 text-sm text-slate-700"
                                        >
                                            <input
                                                v-model="viewPermissionsForm.permissions"
                                                type="checkbox"
                                                class="mt-1 h-4 w-4 rounded border-slate-300 text-emerald-600 focus:ring-emerald-300"
                                                :value="permission.code"
                                            />
                                            <span>{{ permission.label }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-2 text-sm text-slate-500">
                            Права не загружены.
                        </div>
                        <div v-if="viewPermissionsForm.errors.permissions" class="mt-2 text-xs text-rose-700">
                            {{ viewPermissionsForm.errors.permissions }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="viewPermissionsForm.processing"
                        @click="closeView"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="button"
                        :disabled="viewPermissionsForm.processing"
                        @click="submitViewPermissions"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>


