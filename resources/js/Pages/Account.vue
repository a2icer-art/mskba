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
    contacts: {
        type: Array,
        default: () => [],
    },
    contactTypes: {
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
const otherContactGroups = computed(() => {
    const grouped = new Map();

    [...props.contacts]
        .filter((contact) => contact.type && contact.type !== 'email')
        .sort((left, right) => left.id - right.id)
        .forEach((contact) => {
            if (!grouped.has(contact.type)) {
                grouped.set(contact.type, []);
            }
            grouped.get(contact.type).push(contact);
        });

    return Array.from(grouped.entries()).map(([type, items]) => ({
        type,
        label: contactTypeLabels.value[type] ?? type,
        items,
    }));
});
const contactTypeLabels = computed(() =>
    props.contactTypes.reduce((result, item) => {
        result[item.value] = item.label;
        return result;
    }, {})
);

const newContactForm = useForm({
    type: '',
    value: '',
});
const editEmailForm = useForm({
    email: '',
});
const editContactForm = useForm({
    value: '',
});
const actionForm = useForm({});
const editingEmailId = ref(null);
const editingContactId = ref(null);
const emailNotices = ref({});
const emailErrors = ref({});
const contactNotices = ref({});
const contactErrors = ref({});
const newContactNotice = ref('');

const isEditingAny = computed(() => editingEmailId.value !== null || editingContactId.value !== null);
const canAddEmail = computed(() => !isEditingAny.value);
const canAddContact = computed(() => !isEditingAny.value);
const formatContactDate = (value) => formatDate(value);

const startEmailEdit = (email) => {
    if (email.confirmed_at) {
        return;
    }

    editingContactId.value = null;
    editingEmailId.value = email.id;
    editEmailForm.email = email.email;
    editEmailForm.clearErrors();
    emailErrors.value = {
        ...emailErrors.value,
        [email.id]: '',
    };
};

const cancelEmailEdit = () => {
    editingEmailId.value = null;
    editEmailForm.reset('email');
    editEmailForm.clearErrors();
};

const startContactEdit = (contact) => {
    if (contact.confirmed_at) {
        return;
    }

    editingEmailId.value = null;
    editingContactId.value = contact.id;
    editContactForm.value = contact.value;
    editContactForm.clearErrors();
    contactErrors.value = {
        ...contactErrors.value,
        [contact.id]: '',
    };
    newContactNotice.value = '';
};

const cancelContactEdit = () => {
    editingContactId.value = null;
    editContactForm.reset('value');
    editContactForm.clearErrors();
};

const addContact = () => {
    newContactNotice.value = '';
    newContactForm.post('/account/contacts', {
        preserveScroll: true,
        onSuccess: () => {
            newContactForm.reset('type', 'value');
            newContactNotice.value = 'Контакт добавлен.';
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

const deleteContact = (contact) => {
    contactNotices.value = {
        ...contactNotices.value,
        [contact.id]: '',
    };
    contactErrors.value = {
        ...contactErrors.value,
        [contact.id]: '',
    };

    actionForm.delete(`/account/contacts/${contact.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            contactNotices.value = {
                ...contactNotices.value,
                [contact.id]: 'Контакт удален.',
            };
        },
        onError: (errors) => {
            if (errors.contact) {
                contactErrors.value = {
                    ...contactErrors.value,
                    [contact.id]: errors.contact,
                };
            }
        },
    });
};

const updateContact = (contact) => {
    contactNotices.value = {
        ...contactNotices.value,
        [contact.id]: '',
    };
    contactErrors.value = {
        ...contactErrors.value,
        [contact.id]: '',
    };

    editContactForm.patch(`/account/contacts/${contact.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            contactNotices.value = {
                ...contactNotices.value,
                [contact.id]: 'Контакт обновлен.',
            };
            cancelContactEdit();
        },
        onError: (errors) => {
            if (errors.contact) {
                contactErrors.value = {
                    ...contactErrors.value,
                    [contact.id]: errors.contact,
                };
            }
        },
    });
};

const confirmContact = (contact) => {
    contactNotices.value = {
        ...contactNotices.value,
        [contact.id]: '',
    };
    contactErrors.value = {
        ...contactErrors.value,
        [contact.id]: '',
    };

    actionForm.post(`/account/contacts/${contact.id}/confirm`, {
        preserveScroll: true,
        onSuccess: () => {
            contactNotices.value = {
                ...contactNotices.value,
                [contact.id]: 'Контакт подтвержден.',
            };
        },
        onError: (errors) => {
            if (errors.contact) {
                contactErrors.value = {
                    ...contactErrors.value,
                    [contact.id]: errors.contact,
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

                    <div v-else-if="activeTab === 'contacts'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4">
                        <fieldset v-if="emails.length" class="space-y-4 rounded-2xl border border-slate-200 bg-white px-4 py-4">
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
                                            :disabled="isEditingAny"
                                            @click="startEmailEdit(email)"
                                        >
                                            Редактировать
                                        </button>
                                        <button
                                            v-if="!email.confirmed_at && editingEmailId !== email.id"
                                            class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditingAny"
                                            @click="confirmEmail(email)"
                                        >
                                            Подтвердить
                                        </button>
                                        <button
                                            v-if="!email.confirmed_at && editingEmailId !== email.id"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditingAny"
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
                                <p v-if="!canAddEmail" class="text-xs text-slate-500">
                                    Сначала сохраните или отмените текущую правку контакта.
                                </p>
                            </div>
                        </fieldset>

                        <fieldset
                            v-for="group in otherContactGroups"
                            :key="group.type"
                            class="space-y-4 rounded-2xl border border-slate-200 bg-white px-4 py-4"
                        >
                            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">{{ group.label }}</legend>

                            <div v-for="contact in group.items" :key="contact.id" class="space-y-2">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="flex-1">
                                        <input
                                            v-if="editingContactId === contact.id"
                                            v-model="editContactForm.value"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                            type="text"
                                            :disabled="editContactForm.processing"
                                        />
                                        <div v-else class="flex flex-wrap items-center gap-2">
                                            <span class="text-sm font-medium text-slate-800">{{ contact.value }}</span>
                                            <span
                                                v-if="contact.confirmed_at"
                                                class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                :title="formatContactDate(contact.confirmed_at)"
                                            >
                                                Подтвержден
                                            </span>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <button
                                            v-if="editingContactId === contact.id"
                                            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                                            type="button"
                                            :disabled="editContactForm.processing"
                                            @click="updateContact(contact)"
                                        >
                                            Сохранить
                                        </button>
                                        <button
                                            v-if="editingContactId === contact.id"
                                            class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-500 transition hover:-translate-y-0.5 hover:border-slate-300"
                                            type="button"
                                            :disabled="editContactForm.processing"
                                            @click="cancelContactEdit"
                                        >
                                            Отмена
                                        </button>
                                        <button
                                            v-else-if="!contact.confirmed_at"
                                            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                                            type="button"
                                            :disabled="isEditingAny"
                                            @click="startContactEdit(contact)"
                                        >
                                            Редактировать
                                        </button>
                                        <button
                                            v-if="!contact.confirmed_at && editingContactId !== contact.id"
                                            class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditingAny"
                                            @click="confirmContact(contact)"
                                        >
                                            Подтвердить
                                        </button>
                                        <button
                                            v-if="!contact.confirmed_at && editingContactId !== contact.id"
                                            class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                            type="button"
                                            :disabled="actionForm.processing || isEditingAny"
                                            @click="deleteContact(contact)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>

                                <div v-if="editingContactId === contact.id && editContactForm.errors.value" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ editContactForm.errors.value }}
                                </div>
                                <div v-else-if="contactErrors[contact.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ contactErrors[contact.id] }}
                                </div>
                                <div v-else-if="contactNotices[contact.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                    {{ contactNotices[contact.id] }}
                                </div>
                            </div>
                        </fieldset>

                        <fieldset class="space-y-4 rounded-2xl border border-slate-200 bg-white px-4 py-4">
                            <legend class="px-2 text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Добавить контакт</legend>

                            <div class="flex flex-wrap items-center gap-3">
                                <div class="w-full sm:w-40">
                                    <select
                                        v-model="newContactForm.type"
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                        :disabled="!canAddContact || newContactForm.processing"
                                    >
                                        <option value="">Тип контакта</option>
                                        <option v-for="type in contactTypes" :key="type.value" :value="type.value">
                                            {{ type.label }}
                                        </option>
                                    </select>
                                </div>
                                <div class="flex-1">
                                    <input
                                        v-model="newContactForm.value"
                                        class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                        type="text"
                                        placeholder="Значение контакта"
                                        :disabled="!canAddContact || newContactForm.processing"
                                    />
                                </div>
                                <button
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    :disabled="!newContactForm.type || !newContactForm.value || newContactForm.processing || !canAddContact"
                                    @click="addContact"
                                >
                                    Добавить
                                </button>
                            </div>

                            <div v-if="newContactForm.errors.value || newContactForm.errors.type" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                {{ newContactForm.errors.value || newContactForm.errors.type }}
                            </div>
                            <div v-else-if="newContactNotice" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                {{ newContactNotice }}
                            </div>
                            <p v-if="!canAddContact" class="text-xs text-slate-500">
                                Сначала сохраните или отмените текущую правку контакта.
                            </p>
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
