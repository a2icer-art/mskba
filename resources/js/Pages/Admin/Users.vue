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
        default: () => ({
            q: '',
            status: '',
            role: '',
            registered_via: '',
            registered_from: '',
            registered_to: '',
        }),
    },
    statusOptions: {
        type: Array,
        default: () => [],
    },
    roleOptions: {
        type: Array,
        default: () => [],
    },
    registeredViaOptions: {
        type: Array,
        default: () => [],
    },
    participantRoleOptions: {
        type: Array,
        default: () => [],
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
    role: props.filters?.role ?? '',
    registered_via: props.filters?.registered_via ?? '',
    registered_from: props.filters?.registered_from ?? '',
    registered_to: props.filters?.registered_to ?? '',
});

const systemRoleOptions = computed(() => props.roleOptions?.filter((role) => role.value) ?? []);

const applyFilters = () => {
    router.get(
        '/admin/users',
        {
            q: filterForm.q,
            status: filterForm.status,
            role: filterForm.role,
            registered_via: filterForm.registered_via,
            registered_from: filterForm.registered_from,
            registered_to: filterForm.registered_to,
        },
        {
            preserveState: true,
            replace: true,
        }
    );
};

watch(
    () => [
        filterForm.q,
        filterForm.status,
        filterForm.role,
        filterForm.registered_via,
        filterForm.registered_from,
        filterForm.registered_to,
    ],
    () => {
        applyFilters();
    }
);

const formatStatus = (status) => {
    if (status === 'confirmed') {
        return 'Подтвержден';
    }
    if (status === 'blocked') {
        return 'Заблокирован';
    }
    return 'Не подтвержден';
};

const formatContactType = (type) => {
    const map = {
        email: 'Email',
        phone: 'Телефон',
        telegram: 'Telegram',
        vk: 'VK',
        other: 'Другое',
    };
    return map[type] || type || '—';
};

const mapRolesByAlias = (aliases, options) => {
    const lookup = new Map(options.map((role) => [role.value, role.label]));
    return aliases.map((alias) => ({
        alias,
        name: lookup.get(alias) || alias,
    }));
};

const activeUser = ref(null);
const editOpen = ref(false);
const systemRoleSelection = ref([]);
const participantRoleSelection = ref([]);

const openUser = (user) => {
    actionNotice.value = '';
    actionError.value = '';
    activeUser.value = user;
    systemRoleSelection.value = (user.roles || []).map((role) => role.alias);
    participantRoleSelection.value = (user.participant_roles || []).map((role) => role.alias);
    editOpen.value = true;
};

const closeUser = () => {
    editOpen.value = false;
};

const resetForm = useForm({});
const systemRoleForm = useForm({ roles: [] });
const participantRoleForm = useForm({ roles: [] });

const resetContact = (contact) => {
    if (!activeUser.value) {
        return;
    }

    actionNotice.value = '';
    actionError.value = '';

    resetForm.post(`/admin/users/${activeUser.value.id}/contacts/${contact.id}/reset-confirmation`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            actionNotice.value = 'Подтверждение контакта сброшено.';
            if (activeUser.value) {
                activeUser.value = {
                    ...activeUser.value,
                    contacts: activeUser.value.contacts.map((item) =>
                        item.id === contact.id
                            ? {
                                ...item,
                                confirmed_at: null,
                            }
                            : item
                    ),
                };
            }
        },
        onError: (errors) => {
            actionError.value = errors.contact || 'Не удалось сбросить подтверждение.';
        },
        onFinish: () => {
            if (page.props?.errors?.contact) {
                actionError.value = page.props.errors.contact;
            }
        },
    });
};

const saveSystemRoles = () => {
    if (!activeUser.value) {
        return;
    }

    actionNotice.value = '';
    actionError.value = '';
    systemRoleForm.roles = [...systemRoleSelection.value];

    systemRoleForm.post(`/admin/users/${activeUser.value.id}/roles`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            const aliases = systemRoleSelection.value.includes('user')
                ? systemRoleSelection.value
                : [...systemRoleSelection.value, 'user'];
            activeUser.value = {
                ...activeUser.value,
                roles: mapRolesByAlias(aliases, systemRoleOptions.value),
            };
            actionNotice.value = 'Роли пользователя обновлены.';
        },
        onError: (errors) => {
            actionError.value = errors.roles || 'Не удалось обновить роли пользователя.';
        },
    });
};

const saveParticipantRoles = () => {
    if (!activeUser.value) {
        return;
    }

    actionNotice.value = '';
    actionError.value = '';
    participantRoleForm.roles = [...participantRoleSelection.value];

    participantRoleForm.post(`/admin/users/${activeUser.value.id}/participant-roles`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            activeUser.value = {
                ...activeUser.value,
                participant_roles: mapRolesByAlias(participantRoleSelection.value, props.participantRoleOptions),
            };
            actionNotice.value = 'Роли участника обновлены.';
        },
        onError: (errors) => {
            actionError.value = errors.roles || 'Не удалось обновить роли участника.';
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
                :is-authenticated="true"
                :login-label="page.props.auth?.user?.login"
                :sidebar-title="navigation?.title || 'Разделы'"
                :sidebar-items="navigationData"
                :sidebar-active-href="activeHref"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation?.title || 'Разделы'"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Пользователи</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Список пользователей с возможностью сброса подтверждений контактов.
                            </p>
                        </div>
                    </div>

                    <SystemNoticeStack class="mt-4" />

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
                        <div class="grid gap-3 md:grid-cols-3">
                            <input
                                v-model="filterForm.q"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                type="text"
                                placeholder="Поиск по login или ID"
                            />
                            <select
                                v-model="filterForm.status"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            >
                                <option v-for="option in statusOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <select
                                v-model="filterForm.role"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            >
                                <option v-for="option in roleOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <select
                                v-model="filterForm.registered_via"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                            >
                                <option v-for="option in registeredViaOptions" :key="option.value" :value="option.value">
                                    {{ option.label }}
                                </option>
                            </select>
                            <input
                                v-model="filterForm.registered_from"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                type="date"
                                placeholder="Дата с"
                            />
                            <input
                                v-model="filterForm.registered_to"
                                class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                type="date"
                                placeholder="Дата по"
                            />
                        </div>
                    </div>

                    <div v-if="users.data?.length" class="mt-6 w-full overflow-x-auto rounded-2xl border border-slate-200">
                        <div class="min-w-max">
                            <div class="grid grid-cols-[80px_160px_1fr_140px_170px_170px_170px_150px] gap-4 border-b border-slate-200 bg-slate-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500 whitespace-nowrap">
                                <div>ID</div>
                                <div>Login</div>
                                <div>Роли</div>
                                <div>Статус</div>
                                <div>Изменен</div>
                                <div>Кем изменен</div>
                                <div>Регистрация</div>
                                <div>Как зарегистрирован</div>
                            </div>
                            <div class="divide-y divide-slate-100">
                                <div
                                    v-for="user in users.data"
                                    :key="user.id"
                                    class="grid grid-cols-[80px_160px_1fr_140px_170px_170px_170px_150px] items-center gap-4 px-4 py-3 text-sm whitespace-nowrap"
                                >
                                    <div class="font-semibold text-slate-900">{{ user.id }}</div>
                                    <button
                                        class="text-left font-semibold text-slate-900 hover:underline"
                                        type="button"
                                        @click="openUser(user)"
                                    >
                                        {{ user.login }}
                                    </button>
                                    <div class="flex flex-wrap gap-2">
                                        <span
                                            v-for="role in user.roles"
                                            :key="role.alias"
                                            class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-600"
                                        >
                                            {{ role.name || role.alias }}
                                        </span>
                                        <span
                                            v-for="role in user.participant_roles"
                                            :key="`participant-${role.alias}`"
                                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700"
                                        >
                                            {{ role.name || role.alias }}
                                        </span>
                                        <span v-if="!user.roles?.length && !user.participant_roles?.length" class="text-xs text-slate-400">—</span>
                                    </div>
                                    <div>{{ formatStatus(user.status) }}</div>
                                    <div class="text-xs text-slate-500">{{ user.status_changed_at || '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ user.status_changed_by || '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ user.registered_at || '—' }}</div>
                                    <div class="text-xs text-slate-500">{{ user.registered_via_label || '—' }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-6 text-sm text-slate-500">
                        Пользователи не найдены.
                    </div>

                    <div v-if="users.links?.length" class="mt-6 flex flex-wrap items-center gap-2 text-sm">
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

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        Пользователь {{ activeUser?.login }} #{{ activeUser?.id }}
                    </h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeUser"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div v-if="actionError" class="mb-4 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        {{ actionError }}
                    </div>
                    <div v-if="actionNotice" class="mb-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                        {{ actionNotice }}
                    </div>

                    <div class="space-y-6">
                        <div class="space-y-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Системные роли</div>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="role in systemRoleOptions"
                                    :key="role.value || role.label"
                                    class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600"
                                >
                                    <input
                                        v-model="systemRoleSelection"
                                        class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        type="checkbox"
                                        :value="role.value"
                                    />
                                    <span>{{ role.label }}</span>
                                </label>
                            </div>
                            <button
                                class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                type="button"
                                :disabled="systemRoleForm.processing"
                                @click="saveSystemRoles"
                            >
                                Сохранить роли
                            </button>
                        </div>

                        <div class="space-y-3">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Роли участника</div>
                            <div class="flex flex-wrap gap-2">
                                <label
                                    v-for="role in participantRoleOptions"
                                    :key="role.value"
                                    class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-600"
                                >
                                    <input
                                        v-model="participantRoleSelection"
                                        class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                        type="checkbox"
                                        :value="role.value"
                                    />
                                    <span>{{ role.label }}</span>
                                </label>
                            </div>
                            <button
                                class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                type="button"
                                :disabled="participantRoleForm.processing"
                                @click="saveParticipantRoles"
                            >
                                Сохранить роли участника
                            </button>
                        </div>

                        <div class="space-y-2">
                            <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Контакты</div>
                            <div v-if="activeUser?.contacts?.length" class="space-y-2">
                                <div
                                    v-for="contact in activeUser.contacts"
                                    :key="contact.id"
                                    class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3"
                                >
                                    <div>
                                        <div class="text-sm font-semibold text-slate-800">
                                            {{ formatContactType(contact.type) }}
                                        </div>
                                        <div class="text-xs text-slate-600">
                                            {{ contact.value }}
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span
                                            v-if="contact.confirmed_at"
                                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                        >
                                            Подтвержден
                                        </span>
                                        <span v-else class="rounded-full border border-slate-200 bg-white px-2 py-0.5 text-xs text-slate-500">
                                            Не подтвержден
                                        </span>
                                        <button
                                            v-if="contact.confirmed_at"
                                            class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                            type="button"
                                            :disabled="resetForm.processing"
                                            @click="resetContact(contact)"
                                        >
                                            Сбросить подтверждение
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-4 text-sm text-slate-500">
                                Контакты не найдены.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeUser"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
