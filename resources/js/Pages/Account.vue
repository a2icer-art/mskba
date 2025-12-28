<script setup>
import { computed, ref, nextTick, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

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
    contactVerifications: {
        type: Object,
        default: () => ({}),
    },
    moderationRequest: {
        type: Object,
        default: null,
    },
    activeTab: {
        type: String,
        default: 'user',
    },
    accountNavigation: {
        type: Array,
        default: () => [],
    },
});

const accountMenuItems = computed(() => {
    if (props.accountNavigation.length) {
        return props.accountNavigation;
    }

    const items = [
        { key: 'user', label: 'Пользователь', href: '/account' },
        { key: 'profile', label: 'Профиль', href: '/account/profile' },
        { key: 'contacts', label: 'Контакты', href: '/account/contacts' },
    ];

    const roleItems = props.participantRoles.map((role) => ({
        key: `role-${role.id}`,
        label: role.label,
        href: `/account/roles/${role.id}`,
    }));

    return [...items, ...roleItems];
});
const activeTab = computed(() => props.activeTab || 'user');
const page = usePage();
const activeAccountHref = computed(() => {
    const item = accountMenuItems.value.find((menuItem) => menuItem.key === activeTab.value);
    return item?.href ?? page.url ?? '';
});
const moderationRequest = computed(() => props.moderationRequest ?? null);
const isModerationPending = computed(() => moderationRequest.value?.status === 'pending');
const isModerationRejected = computed(() => moderationRequest.value?.status === 'rejected');
const moderationRejectedAt = computed(() => moderationRequest.value?.reviewed_at ?? moderationRequest.value?.submitted_at);
const moderationRejectedReason = computed(() => moderationRequest.value?.reject_reason ?? '');
const hasModerationRejectReason = computed(() => Boolean(moderationRequest.value?.reject_reason));
const canResubmitModeration = computed(() => hasModerationRejectReason.value);
const isProfileConfirmed = computed(() => props.user?.status === 'confirmed');
const hasSidebar = computed(() => accountMenuItems.value.length > 0);
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


const userItems = computed(() => [
    { key: 'id', label: 'ID', value: props.user?.id ?? '—' },
    { key: 'login', label: 'Логин', value: props.user?.login ?? '—' },
    { key: 'password', label: 'Пароль', value: '********' },
    { key: 'created_at', label: 'Дата регистрации', value: formatDate(props.user?.created_at) },
    { key: 'status', label: 'Статус', value: '' },
    ...(props.user?.status === 'confirmed'
        ? [{ key: 'confirmed_at', label: 'Дата подтверждения', value: formatDate(props.user?.confirmed_at) }]
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
const requestCodeForm = useForm({});
const verifyCodeForm = useForm({
    code: '',
});
const actionForm = useForm({});
const moderationForm = useForm({});
const profileForm = useForm({
    first_name: '',
    last_name: '',
    middle_name: '',
    gender: '',
    birth_date: '',
});
const passwordForm = useForm({
    password: '',
});
const editingEmailId = ref(null);
const editingContactId = ref(null);
const emailNotices = ref({});
const emailErrors = ref({});
const contactNotices = ref({});
const contactErrors = ref({});
const newContactNotice = ref('');
const moderationNotice = ref('');
const moderationErrors = ref([]);
const profileNotice = ref('');
const passwordNotice = ref('');
const profileEditOpen = ref(false);
const passwordEditOpen = ref(false);
const verificationOpen = ref({});
const verificationCodes = ref({});
const verificationPending = ref({});
const verificationError = ref({});
const verificationMessages = ref({});
const verificationCountdowns = ref({});
const verificationCountdownTimers = new Map();
const verificationOverrides = ref({});

const isEditingAny = computed(() => editingEmailId.value !== null || editingContactId.value !== null);
const canAddEmail = computed(() => !isEditingAny.value);
const canAddContact = computed(() => !isEditingAny.value);
const formatContactDate = (value) => formatDate(value);
const confirmButtonLabel = (contactId) => {
    const hasVerification = verificationOpen.value[contactId] || Boolean(getVerificationState(contactId));
    if (hasVerification) {
        return 'Запросить новый код';
    }
    return 'Подтвердить';
};
const shouldShowVerificationInput = (contactId) => {
    if (!verificationOpen.value[contactId]) {
        return false;
    }

    return true;
};
const verificationStateMap = computed(() => props.contactVerifications ?? {});
const getVerificationState = (contactId) =>
    verificationOverrides.value[contactId] ?? verificationStateMap.value[contactId] ?? null;
const hasAttemptsExceeded = (contactId) => {
    const state = getVerificationState(contactId);
    if (!state) {
        return false;
    }
    return state.attempts >= state.max_attempts;
};
const isVerificationBlocked = (contactId) => {
    const state = getVerificationState(contactId);
    if (!state || !state.expires_at) {
        return false;
    }

    if (!hasAttemptsExceeded(contactId)) {
        return false;
    }

    const expiresAt = new Date(state.expires_at);
    return expiresAt.getTime() > Date.now();
};
const getVerificationWaitSeconds = (contactId) => {
    const state = getVerificationState(contactId);
    if (!state || !state.expires_at) {
        return 0;
    }

    const expiresAt = new Date(state.expires_at);
    const diff = Math.floor((expiresAt.getTime() - Date.now()) / 1000);
    return Math.max(0, diff);
};
const formatCountdown = (seconds) => {
    const totalSeconds = Math.max(0, Math.floor(Number(seconds) || 0));
    if (totalSeconds <= 0) {
        return '00:00';
    }

    const minutes = Math.floor(totalSeconds / 60);
    const remainder = totalSeconds % 60;
    return `${String(minutes).padStart(2, '0')}:${String(remainder).padStart(2, '0')}`;
};
const isContactsProcessing = computed(
    () =>
        newContactForm.processing ||
        editEmailForm.processing ||
        editContactForm.processing ||
        requestCodeForm.processing ||
        verifyCodeForm.processing ||
        actionForm.processing
);
const isVerificationLocked = (contactId) => {
    if (!hasAttemptsExceeded(contactId)) {
        return false;
    }

    return true;
};
const exhaustedAttemptsMessage = 'Попытки израсходованы. Запросите новый код.';
const setVerificationCountdown = (contactId, seconds) => {
    if (verificationCountdownTimers.has(contactId)) {
        clearInterval(verificationCountdownTimers.get(contactId));
        verificationCountdownTimers.delete(contactId);
    }

    if (seconds <= 0) {
        verificationCountdowns.value = {
            ...verificationCountdowns.value,
            [contactId]: 0,
        };
        return;
    }

    verificationCountdowns.value = {
        ...verificationCountdowns.value,
        [contactId]: seconds,
    };

    const timer = setInterval(() => {
        const current = verificationCountdowns.value[contactId] ?? 0;
        if (current <= 1) {
            clearInterval(timer);
            verificationCountdownTimers.delete(contactId);
            verificationCountdowns.value = {
                ...verificationCountdowns.value,
                [contactId]: 0,
            };
            return;
        }

        verificationCountdowns.value = {
            ...verificationCountdowns.value,
            [contactId]: current - 1,
        };
    }, 1000);

    verificationCountdownTimers.set(contactId, timer);
};

const setVerificationAttempts = (contactId, attempts, maxAttempts) => {
    const base = verificationStateMap.value[contactId] ?? {};
    verificationOverrides.value = {
        ...verificationOverrides.value,
        [contactId]: {
            ...base,
            attempts,
            max_attempts: maxAttempts ?? base.max_attempts,
        },
    };
    syncVerificationOpen();
};

const clearVerificationOverride = (contactId) => {
    if (!verificationOverrides.value[contactId]) {
        return;
    }
    const next = { ...verificationOverrides.value };
    delete next[contactId];
    verificationOverrides.value = next;
    syncVerificationOpen();
};

const syncVerificationOpen = () => {
    const next = {};

    props.contacts.forEach((contact) => {
        if (contact.confirmed_at) {
            next[contact.id] = false;
            return;
        }

        if (getVerificationState(contact.id)) {
            next[contact.id] = true;
        }
    });

    verificationOpen.value = {
        ...verificationOpen.value,
        ...next,
    };
};

watch(
    () => props.contactVerifications,
    () => {
        syncVerificationOpen();
    },
    { immediate: true, deep: true }
);

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

const requestVerificationCode = (contact, errorMap) => {
    if (isVerificationBlocked(contact.id)) {
        setVerificationCountdown(contact.id, getVerificationWaitSeconds(contact.id));
        errorMap.value = {
            ...errorMap.value,
            [contact.id]: 'Слишком много попыток. Подождите.',
        };
        return;
    }

    errorMap.value = {
        ...errorMap.value,
        [contact.id]: '',
    };
    verificationMessages.value = {
        ...verificationMessages.value,
        [contact.id]: '',
    };
    verificationError.value = {
        ...verificationError.value,
        [contact.id]: false,
    };
    verificationPending.value = {
        ...verificationPending.value,
        [contact.id]: true,
    };

    requestCodeForm.post(`/account/contacts/${contact.id}/confirm-request`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            verificationOpen.value = {
                ...verificationOpen.value,
                [contact.id]: true,
            };
            verificationCodes.value = {
                ...verificationCodes.value,
                [contact.id]: '',
            };
            setVerificationCountdown(contact.id, 0);
            clearVerificationOverride(contact.id);
        },
        onError: (errors) => {
            const message = errors.contact || errors.email;
            if (message) {
                errorMap.value = {
                    ...errorMap.value,
                    [contact.id]: message,
                };
            }
            const waitSeconds = Number(errors.wait_seconds ?? 0);
            if (waitSeconds > 0) {
                setVerificationCountdown(contact.id, waitSeconds);
            }
        },
        onFinish: () => {
            verificationPending.value = {
                ...verificationPending.value,
                [contact.id]: false,
            };
        },
    });
};

const submitVerificationCode = (contact, errorMap) => {
    errorMap.value = {
        ...errorMap.value,
        [contact.id]: '',
    };
    verificationMessages.value = {
        ...verificationMessages.value,
        [contact.id]: '',
    };
    verificationError.value = {
        ...verificationError.value,
        [contact.id]: false,
    };
    const code = verificationCodes.value[contact.id];

    if (!code) {
        errorMap.value = {
            ...errorMap.value,
            [contact.id]: 'Введите код.',
        };
        verificationMessages.value = {
            ...verificationMessages.value,
            [contact.id]: 'Введите код.',
        };
        verificationError.value = {
            ...verificationError.value,
            [contact.id]: true,
        };
        return;
    }

    verifyCodeForm.code = code;
    verifyCodeForm.post(`/account/contacts/${contact.id}/confirm-verify`, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            verificationOpen.value = {
                ...verificationOpen.value,
                [contact.id]: false,
            };
            verificationCodes.value = {
                ...verificationCodes.value,
                [contact.id]: '',
            };
            verificationMessages.value = {
                ...verificationMessages.value,
                [contact.id]: '',
            };
            setVerificationCountdown(contact.id, 0);
            clearVerificationOverride(contact.id);
        },
        onError: (errors) => {
            const message = errors.code || errors.contact;
            if (message) {
                errorMap.value = {
                    ...errorMap.value,
                    [contact.id]: message,
                };
                verificationMessages.value = {
                    ...verificationMessages.value,
                    [contact.id]: message,
                };
                verificationOpen.value = {
                    ...verificationOpen.value,
                    [contact.id]: true,
                };
                verificationError.value = {
                    ...verificationError.value,
                    [contact.id]: true,
                };
            }
            const attemptsLeft = Number(errors.attempts_left ?? NaN);
            const maxAttempts = Number(errors.max_attempts ?? NaN);
            if (!Number.isNaN(attemptsLeft)) {
                const safeMax = Number.isNaN(maxAttempts) ? getVerificationState(contact.id)?.max_attempts : maxAttempts;
                if (safeMax) {
                    setVerificationAttempts(contact.id, Math.max(0, safeMax - attemptsLeft), safeMax);
                }
            }
        },
        onFinish: () => {
            nextTick(() => {
                const fallbackError = page.props?.errors?.code;
                const formError = verifyCodeForm.errors.code;
                const message = formError || fallbackError;
                if (message) {
                    errorMap.value = {
                        ...errorMap.value,
                        [contact.id]: message,
                    };
                    verificationMessages.value = {
                        ...verificationMessages.value,
                        [contact.id]: message,
                    };
                    verificationOpen.value = {
                        ...verificationOpen.value,
                        [contact.id]: true,
                    };
                    verificationError.value = {
                        ...verificationError.value,
                        [contact.id]: true,
                    };
                }
            });
        },
    });
};

const clearVerificationError = (contactId) => {
    verificationError.value = {
        ...verificationError.value,
        [contactId]: false,
    };
    verificationMessages.value = {
        ...verificationMessages.value,
        [contactId]: '',
    };
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

const submitModerationRequest = () => {
    moderationNotice.value = '';
    moderationErrors.value = [];

    moderationForm.post('/account/moderation-request', {
        preserveScroll: true,
        onSuccess: () => {
            moderationNotice.value = 'Заявка отправлена на модерацию.';
        },
        onError: (errors) => {
            if (errors.moderation) {
                moderationErrors.value = errors.moderation.split('\n').filter(Boolean);
            }
        },
        onFinish: () => {
            if (page.props?.errors?.moderation) {
                moderationErrors.value = page.props.errors.moderation.split('\n').filter(Boolean);
            }
        },
    });
};

const openProfileEdit = () => {
    profileNotice.value = '';
    passwordNotice.value = '';
    profileForm.clearErrors();
    passwordForm.clearErrors();

    profileForm.first_name = props.profile?.first_name ?? '';
    profileForm.last_name = props.profile?.last_name ?? '';
    profileForm.middle_name = props.profile?.middle_name ?? '';
    profileForm.gender = props.profile?.gender ?? '';
    profileForm.birth_date = props.profile?.birth_date ?? '';

    profileEditOpen.value = true;
};

const closeProfileEdit = () => {
    profileEditOpen.value = false;
    profileForm.reset('first_name', 'last_name', 'middle_name', 'gender', 'birth_date');
    profileForm.clearErrors();
};

const submitProfileUpdate = () => {
    profileNotice.value = '';
    profileForm.transform((data) => {
        if (isProfileConfirmed.value) {
            return {
                middle_name: data.middle_name,
            };
        }

        return data;
    }).patch('/account/profile', {
        preserveScroll: true,
        onSuccess: () => {
            profileNotice.value = 'Профиль обновлен.';
        },
    });
};

const openPasswordEdit = () => {
    passwordNotice.value = '';
    passwordForm.clearErrors();
    passwordEditOpen.value = true;
};

const closePasswordEdit = () => {
    passwordEditOpen.value = false;
    passwordForm.reset('password');
    passwordForm.clearErrors();
};

const submitPasswordUpdate = () => {
    passwordNotice.value = '';
    passwordForm.patch('/account/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordNotice.value = 'Пароль обновлен.';
            passwordForm.reset('password');
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

            <section class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    title="Аккаунт"
                    :items="accountMenuItems"
                    :active-href="activeAccountHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Аккаунт</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Профиль пользователя</h1>
                        </div>
                        <button
                            v-if="activeTab === 'profile'"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openProfileEdit"
                        >
                            Редактировать
                        </button>
                        <button
                            v-else-if="activeTab === 'user'"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openPasswordEdit"
                        >
                            Изменить пароль
                        </button>
                    </div>

                    <div class="mt-6 grid gap-4">
                        <div
                            v-if="activeTab === 'user'"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            :class="{ loading: moderationForm.processing }"
                        >
                            <div v-for="item in userItems" :key="item.key" class="border-b border-slate-100 last:border-b-0">
                                <div class="flex items-center justify-between py-3">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                                    <div class="flex flex-wrap items-center justify-end gap-2 text-sm font-medium text-slate-800">
                                        <span v-if="item.key !== 'status'">{{ item.value }}</span>
                                        <template v-if="item.key === 'status'">
                                            <span
                                                v-if="user?.status === 'confirmed'"
                                                class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                                :title="formatDate(user?.confirmed_at)"
                                            >
                                                Подтвержден
                                            </span>
                                            <span
                                                v-else-if="user?.status === 'blocked'"
                                                class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700"
                                            >
                                                Заблокирован
                                            </span>
                                            <span
                                                v-else-if="isModerationPending"
                                                class="rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800"
                                                :title="formatDate(moderationRequest?.submitted_at)"
                                            >
                                                На модерации
                                            </span>
                                            <span
                                                v-else-if="isModerationRejected && !hasModerationRejectReason"
                                                class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700"
                                                :title="formatDate(moderationRejectedAt)"
                                            >
                                                Отклонено
                                            </span>
                                            <button
                                                v-if="isModerationRejected && canResubmitModeration && user?.status === 'unconfirmed'"
                                                class="rounded-full border border-slate-900 bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                                type="button"
                                                :disabled="moderationForm.processing"
                                                @click="submitModerationRequest"
                                            >
                                                Отправить повторно
                                            </button>
                                            <button
                                                v-else-if="!isModerationPending && !isModerationRejected && user?.status === 'unconfirmed'"
                                                class="rounded-full border border-slate-900 bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                                type="button"
                                                :disabled="moderationForm.processing"
                                                @click="submitModerationRequest"
                                            >
                                                Отправить на модерацию
                                            </button>
                                        </template>
                                    </div>
                                </div>
                                <div v-if="item.key === 'status'" class="pb-3">
                                    <div v-if="moderationErrors.length" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        <p class="font-semibold">Не выполнены требования:</p>
                                        <ul class="mt-1 list-disc space-y-1 pl-4">
                                            <li v-for="(message, index) in moderationErrors" :key="index">{{ message }}</li>
                                        </ul>
                                    </div>
                                    <div v-else-if="isModerationRejected && hasModerationRejectReason" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ moderationRejectedReason }}
                                    </div>
                                    <div v-else-if="isModerationRejected" class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                                        Причина отклонения пока не указана. Повторная отправка станет доступна после комментария модератора.
                                    </div>
                                    <div v-else-if="user?.status === 'blocked'" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ user?.block_reason || 'Причина блокировки не определена.' }}
                                    </div>
                                    <div v-else-if="moderationNotice" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                        {{ moderationNotice }}
                                    </div>
                                </div>
                            </div>
                        </div>

                    <div v-else-if="activeTab === 'profile'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in profileItems" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>

                    <div
                        v-else-if="activeTab === 'contacts'"
                        class="rounded-2xl border border-slate-200 bg-slate-50 p-4 space-y-4"
                        :class="{ loading: isContactsProcessing }"
                    >
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
                                            :disabled="verificationPending[email.id] || isEditingAny"
                                            @click="requestVerificationCode(email, emailErrors)"
                                        >
                                            {{ confirmButtonLabel(email.id) }}
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

                                <div v-if="shouldShowVerificationInput(email.id) && !email.confirmed_at" class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <input
                                            v-model="verificationCodes[email.id]"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                            :class="{
                                                'input-error': verificationError[email.id],
                                            }"
                                            type="text"
                                            placeholder="Введите код"
                                            :disabled="isVerificationLocked(email.id)"
                                            @input="clearVerificationError(email.id)"
                                        />
                                    </div>
                                    <div v-if="verificationMessages[email.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ verificationMessages[email.id] }}
                                    </div>
                                    <div v-else-if="isVerificationLocked(email.id)" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ exhaustedAttemptsMessage }}
                                    </div>
                                    <button
                                        v-if="!isVerificationLocked(email.id)"
                                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                        type="button"
                                        :disabled="verifyCodeForm.processing"
                                        @click="submitVerificationCode(email, emailErrors)"
                                    >
                                        Подтвердить код
                                    </button>
                                    <div v-if="emailErrors[email.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ emailErrors[email.id] }}
                                    </div>
                                    <div v-else-if="emailNotices[email.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                        {{ emailNotices[email.id] }}
                                    </div>
                                </div>

                                <div v-if="editingEmailId === email.id && editEmailForm.errors.email" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ editEmailForm.errors.email }}
                                </div>
                                <div v-else-if="verificationCountdowns[email.id]" class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    Подождите {{ formatCountdown(verificationCountdowns[email.id]) }}
                                </div>
                                <div v-else-if="emailErrors[email.id] && !verificationOpen[email.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ emailErrors[email.id] }}
                                </div>
                                <div v-else-if="emailNotices[email.id] && !verificationOpen[email.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
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
                                            :disabled="verificationPending[contact.id] || isEditingAny"
                                            @click="requestVerificationCode(contact, contactErrors)"
                                        >
                                            {{ confirmButtonLabel(contact.id) }}
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

                                <div v-if="shouldShowVerificationInput(contact.id) && !contact.confirmed_at" class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <input
                                            v-model="verificationCodes[contact.id]"
                                            class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                                            :class="{
                                                'input-error': verificationError[contact.id],
                                            }"
                                            type="text"
                                            placeholder="Введите код"
                                            :disabled="isVerificationLocked(contact.id)"
                                            @input="clearVerificationError(contact.id)"
                                        />
                                    </div>
                                    <div v-if="verificationMessages[contact.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ verificationMessages[contact.id] }}
                                    </div>
                                    <div v-else-if="isVerificationLocked(contact.id)" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ exhaustedAttemptsMessage }}
                                    </div>
                                    <button
                                        v-if="!isVerificationLocked(contact.id)"
                                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                        type="button"
                                        :disabled="verifyCodeForm.processing"
                                        @click="submitVerificationCode(contact, contactErrors)"
                                    >
                                        Подтвердить код
                                    </button>
                                    <div v-if="contactErrors[contact.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                        {{ contactErrors[contact.id] }}
                                    </div>
                                    <div v-else-if="contactNotices[contact.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                        {{ contactNotices[contact.id] }}
                                    </div>
                                </div>

                                <div v-if="editingContactId === contact.id && editContactForm.errors.value" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ editContactForm.errors.value }}
                                </div>
                                <div v-else-if="verificationCountdowns[contact.id]" class="rounded-2xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                    Подождите {{ formatCountdown(verificationCountdowns[contact.id]) }}
                                </div>
                                <div v-else-if="contactErrors[contact.id] && !verificationOpen[contact.id]" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                    {{ contactErrors[contact.id] }}
                                </div>
                                <div v-else-if="contactNotices[contact.id] && !verificationOpen[contact.id]" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
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
                </div>
            </section>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="profileEditOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <form :class="{ loading: profileForm.processing }" @submit.prevent="submitProfileUpdate">
                <h2 class="text-lg font-semibold text-slate-900">Редактирование профиля</h2>
                <p class="mt-2 text-sm text-slate-600">
                    Заполните доступные поля профиля и сохраните изменения.
                </p>
                <p v-if="isProfileConfirmed" class="mt-2 text-xs text-slate-500">
                    Часть полей недоступна для редактирования так как запись уже подтверждена.
                </p>

                <div class="mt-4 flex flex-col gap-3">
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Имя
                        <input
                            v-model="profileForm.first_name"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 disabled:bg-slate-100"
                            type="text"
                            :disabled="isProfileConfirmed"
                        />
                    </label>
                    <div v-if="profileForm.errors.first_name" class="text-xs text-rose-700">
                        {{ profileForm.errors.first_name }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Фамилия
                        <input
                            v-model="profileForm.last_name"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 disabled:bg-slate-100"
                            type="text"
                            :disabled="isProfileConfirmed"
                        />
                    </label>
                    <div v-if="profileForm.errors.last_name" class="text-xs text-rose-700">
                        {{ profileForm.errors.last_name }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Отчество
                        <input
                            v-model="profileForm.middle_name"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="text"
                        />
                    </label>
                    <div v-if="profileForm.errors.middle_name" class="text-xs text-rose-700">
                        {{ profileForm.errors.middle_name }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Пол
                        <select
                            v-model="profileForm.gender"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 disabled:bg-slate-100"
                            :disabled="isProfileConfirmed"
                        >
                            <option value="">Не определен</option>
                            <option value="male">Мужской</option>
                            <option value="female">Женский</option>
                        </select>
                    </label>
                    <div v-if="profileForm.errors.gender" class="text-xs text-rose-700">
                        {{ profileForm.errors.gender }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Дата рождения
                        <input
                            v-model="profileForm.birth_date"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 disabled:bg-slate-100"
                            type="date"
                            :disabled="isProfileConfirmed"
                        />
                    </label>
                    <div v-if="profileForm.errors.birth_date" class="text-xs text-rose-700">
                        {{ profileForm.errors.birth_date }}
                    </div>
                </div>

                <div v-if="profileNotice" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    {{ profileNotice }}
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="profileForm.processing"
                        @click="closeProfileEdit"
                    >
                        Отмена
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                        type="submit"
                        :disabled="profileForm.processing"
                    >
                        Сохранить профиль
                    </button>
                </div>
                </form>
            </div>
        </div>

        <div v-if="passwordEditOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <form :class="{ loading: passwordForm.processing }" @submit.prevent="submitPasswordUpdate">
                <h2 class="text-lg font-semibold text-slate-900">Изменить пароль</h2>
                <p class="mt-2 text-sm text-slate-600">Укажите новый пароль для вашей учетной записи.</p>
                <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                    Новый пароль
                    <input
                        v-model="passwordForm.password"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        type="password"
                        autocomplete="new-password"
                    />
                </label>
                <div v-if="passwordForm.errors.password" class="text-xs text-rose-700">
                    {{ passwordForm.errors.password }}
                </div>

                <div v-if="passwordNotice" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    {{ passwordNotice }}
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="passwordForm.processing"
                        @click="closePasswordEdit"
                    >
                        Отмена
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                        type="submit"
                        :disabled="passwordForm.processing"
                    >
                        Сохранить пароль
                    </button>
                </div>
                </form>
            </div>
        </div>
    </main>
</template>
