<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    user: {
        type: Object,
        default: null,
    },
    profile: {
        type: Object,
        default: null,
    },
    participantRoles: {
        type: Array,
        default: () => [],
    },
    emails: {
        type: Array,
        default: () => [],
    },
});

const baseTabs = [
    { key: 'user', label: 'Пользователь' },
    { key: 'profile', label: 'Профиль' },
    { key: 'contacts', label: 'Контакты' },
];
const tabs = computed(() => [
    ...baseTabs,
    ...props.participantRoles.map((role) => ({
        key: `role-${role.id}`,
        label: role.label,
        alias: role.alias,
    })),
]);
const activeTab = ref(baseTabs[0].key);
const logoutForm = useForm({});

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

const statusLabelMap = {
    confirmed: 'Подтвержден',
    unconfirmed: 'Не подтвержден',
};

const userItems = computed(() => [
    { label: 'ID', value: props.user?.id ?? '—' },
    { label: 'Логин', value: props.user?.login ?? '—' },
    { label: 'Пароль', value: '********' },
    { label: 'Дата регистрации', value: formatDate(props.user?.created_at) },
    { label: 'Статус', value: statusLabelMap[props.user?.status] ?? '—' },
    ...(props.user?.status === 'confirmed'
        ? [{ label: 'Дата подтверждения', value: formatDate(props.user?.confirmed_at) }]
        : []),
]);

const profileItems = computed(() => [
    { label: 'Имя', value: props.profile?.first_name ?? '—' },
    { label: 'Фамилия', value: props.profile?.last_name ?? '—' },
    { label: 'Отчество', value: props.profile?.middle_name ?? '—' },
    { label: 'Пол', value: props.profile?.gender ?? '—' },
    { label: 'Дата рождения', value: props.profile?.birth_date ?? '—' },
]);

const roleItems = (role) => [
    { label: 'Роль', value: role.label ?? '—' },
    { label: 'Alias', value: role.alias ?? '—' },
];

const activeRole = computed(() => props.participantRoles.find((role) => `role-${role.id}` === activeTab.value) ?? null);

const emails = computed(() =>
    [...props.emails].sort((left, right) => left.id - right.id)
);

const newEmailForm = useForm({
    email: '',
});
const editEmailForm = useForm({
    email: '',
});
const actionForm = useForm({});
const editingEmailId = ref(null);
const newEmailNotice = ref('');
const emailNotices = ref({});
const emailErrors = ref({});

const isEditing = computed(() => editingEmailId.value !== null);
const canAddEmail = computed(() => !isEditing.value);
const formatContactDate = (value) => formatDate(value);

const startEmailEdit = (email) => {
    if (email.confirmed_at) {
        return;
    }

    editingEmailId.value = email.id;
    editEmailForm.email = email.email;
    editEmailForm.clearErrors();
    emailErrors.value = {
        ...emailErrors.value,
        [email.id]: '',
    };
    newEmailNotice.value = '';
};

const cancelEmailEdit = () => {
    editingEmailId.value = null;
    editEmailForm.reset('email');
    editEmailForm.clearErrors();
};

const addEmail = () => {
    newEmailNotice.value = '';
    newEmailForm.post('/account/emails', {
        preserveScroll: true,
        onSuccess: () => {
            newEmailForm.reset('email');
            newEmailNotice.value = 'Email добавлен.';
        },
    });
};

const updateEmail = (email) => {
    emailNotices.value = {
        ...emailNotices.value,
        [email.id]: '',
    };
    emailErrors.value = {
        ...emailErrors.value,
        [email.id]: '',
    };

    editEmailForm.patch(`/account/emails/${email.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emailNotices.value = {
                ...emailNotices.value,
                [email.id]: 'Email обновлен.',
            };
            cancelEmailEdit();
        },
    });
};

const deleteEmail = (email) => {
    emailNotices.value = {
        ...emailNotices.value,
        [email.id]: '',
    };
    emailErrors.value = {
        ...emailErrors.value,
        [email.id]: '',
    };

    actionForm.delete(`/account/emails/${email.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            emailNotices.value = {
                ...emailNotices.value,
                [email.id]: 'Email удален.',
            };
        },
        onError: (errors) => {
            if (errors.email) {
                emailErrors.value = {
                    ...emailErrors.value,
                    [email.id]: errors.email,
                };
            }
        },
    });
};

const confirmEmail = (email) => {
    emailNotices.value = {
        ...emailNotices.value,
        [email.id]: '',
    };
    emailErrors.value = {
        ...emailErrors.value,
        [email.id]: '',
    };

    actionForm.post(`/account/emails/${email.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => {
            emailNotices.value = {
                ...emailNotices.value,
                [email.id]: 'Email подтвержден.',
            };
        },
        onError: (errors) => {
            if (errors.email) {
                emailErrors.value = {
                    ...emailErrors.value,
                    [email.id]: errors.email,
                };
            }
        },
    });
};

const logout = () => {
    logoutForm.post('/logout');
};

</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader :app-name="appName" :is-authenticated="true" :login-label="user?.login" />

            <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Аккаунт</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Профиль пользователя</h1>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                        "
                        type="button"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div v-if="activeTab === 'user'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in userItems" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'profile'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in profileItems" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'contacts'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <fieldset class="space-y-4 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Электронная почта</legend>

                            <div v-for="email in emails" :key="email.id" class="space-y-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex-1">
                                        <input
                                            v-if="editingEmailId === email.id"
                                            v-model="editEmailForm.email"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                            type="email"
                                            autocomplete="email"
                                            :disabled="editEmailForm.processing"
                                        />
                                        <div v-else class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-slate-800">{{ email.email }}</span>
                                            <span
                                                v-if="email.confirmed_at"
                                                class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                :title="formatContactDate(email.confirmed_at)"
                                            >
                                                Подтвержден
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="editingEmailId === email.id"
                                            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                                            type="button"
                                            :disabled="editEmailForm.processing"
                                            @click="updateEmail(email)"
                                        >
                                            Сохранить
                                        </button>
                                        <button
                                            v-if="editingEmailId === email.id"
                                            class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 transition hover:-translate-y-0.5 hover:border-slate-300"
                                            type="button"
                                            :disabled="editEmailForm.processing"
                                            @click="cancelEmailEdit"
                                        >
                                            Отмена
                                        </button>
                                        <button
                                            v-else-if="!email.confirmed_at"
                                            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                                            type="button"
                                            :disabled="isEditing"
                                            @click="startEmailEdit(email)"
                                        >
                                            Редактировать
                                        </button>
                                        <button
                                            v-if="!email.confirmed_at && editingEmailId !== email.id"
                                            class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditing"
                                            @click="confirmEmail(email)"
                                        >
                                            Подтвердить
                                        </button>
                                        <button
                                            v-if="!email.confirmed_at && editingEmailId !== email.id"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditing"
                                            @click="deleteEmail(email)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>

                                <div v-if="editingEmailId === email.id && editEmailForm.errors.email" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ editEmailForm.errors.email }}
                                </div>
                                <div v-else-if="emailErrors[email.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ emailErrors[email.id] }}
                                </div>
                                <div v-else-if="emailNotices[email.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    {{ emailNotices[email.id] }}
                                </div>
                            </div>

                            <div class="mt-4 space-y-2 border-t border-slate-100 pt-4">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex-1">
                                        <input
                                            v-model="newEmailForm.email"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                            type="email"
                                            autocomplete="email"
                                            placeholder="Добавить email"
                                            :disabled="!canAddEmail || newEmailForm.processing"
                                        />
                                    </div>
                                    <button
                                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                        type="button"
                                        :disabled="!newEmailForm.email || newEmailForm.processing || !canAddEmail"
                                        @click="addEmail"
                                    >
                                        Добавить
                                    </button>
                                </div>

                                <div v-if="newEmailForm.errors.email" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ newEmailForm.errors.email }}
                                </div>
                                <div v-else-if="newEmailNotice" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    {{ newEmailNotice }}
                                </div>
                                <p v-if="!canAddEmail" class="text-xs text-slate-500">
                                    Сначала сохраните или отмените текущую правку email.
                                </p>
                            </div>
                        </fieldset>
                    </div>

                    <div v-else-if="activeRole" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in roleItems(activeRole)" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end">
                    <button
                        class="rounded-full border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                        type="button"
                        @click="logout"
                    >
                        Выйти
                    </button>
                </div>
            </section>

            <MainFooter />
        </div>
    </main>
</template>
