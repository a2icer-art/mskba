<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    event: {
        type: Object,
        default: null,
    },
    participantsCount: {
        type: Number,
        default: 0,
    },
    reserveCount: {
        type: Number,
        default: 0,
    },
    allowedRoles: {
        type: Array,
        default: () => [],
    },
    userAllowedRoles: {
        type: Array,
        default: () => [],
    },
    limitRole: {
        type: String,
        default: 'player',
    },
    userParticipation: {
        type: Object,
        default: null,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    isExpired: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
const isExpired = computed(() => props.isExpired);
const participantRoles = computed(() => {
    const labels = {
        player: 'Игрок',
        coach: 'Тренер',
        referee: 'Судья',
        media: 'Медиа',
        seller: 'Продавец',
        staff: 'Стафф',
    };
    const allowed = props.userAllowedRoles?.length
        ? props.userAllowedRoles
        : props.allowedRoles?.length
            ? props.allowedRoles
            : ['player'];
    return allowed.map((value) => ({
        value,
        label: labels[value] || value,
    }));
});
const resolveParticipantStatusLabel = (status, statusChangedBy) => {
    const currentUserId = page.props.auth?.user?.id;
    const changedByOrganizer = statusChangedBy && statusChangedBy !== currentUserId;
    if (status === 'confirmed') {
        return changedByOrganizer ? 'Организатор подтвердил участие' : 'Вы участвуете';
    }
    if (status === 'reserve') {
        return changedByOrganizer ? 'Организатор перевел в резерв' : 'Вы в резерве';
    }
    if (status === 'declined') {
        return changedByOrganizer ? 'Организатор отклонил участие' : 'Вы отказались от участия';
    }
    if (status === 'invited') {
        return 'Вас пригласили';
    }
    return 'Статус неизвестен';
};
const statusRank = {
    declined: 0,
    invited: 1,
    reserve: 2,
    confirmed: 3,
};
const joinForm = useForm({
    role: props.userAllowedRoles?.[0] || props.allowedRoles?.[0] || 'player',
    status: 'confirmed',
});
const respondForm = useForm({
    status: 'confirmed',
    reason: '',
});
const respondOpen = ref(false);

const formatDateRange = (startsAt, endsAt) => {
    if (!startsAt || !endsAt) {
        return '—';
    }
    const start = new Date(startsAt);
    const end = new Date(endsAt);
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return `${startsAt} – ${endsAt}`;
    }
    const dateLabel = start.toLocaleDateString('ru-RU');
    const startTime = start.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    const endTime = end.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
    return `${dateLabel}, ${startTime} – ${endTime}`;
};

const formatAmount = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${Number(value)} ₽`;
};

const participantsLimitLabel = computed(() => {
    const limit = Number(props.event?.participants_limit ?? 0);
    if (!limit) {
        return '—';
    }
    return String(limit);
});
const perParticipantCost = computed(() => {
    const total = Number(props.event?.price_amount_minor ?? 0);
    const limit = Number(props.event?.participants_limit ?? 0);
    if (!total || !limit) {
        return 0;
    }
    return Math.ceil(total / limit);
});

const isLimitReached = computed(() => {
    const limit = Number(props.event?.participants_limit ?? 0);
    if (!limit) {
        return false;
    }
    return Number(props.participantsCount ?? 0) >= limit;
});
const isUpgradeBlocked = (targetStatus) => {
    if (!props.userParticipation?.status_changed_by) {
        return false;
    }
    if (props.userParticipation?.status === 'invited') {
        return false;
    }
    const current = props.userParticipation?.status || 'invited';
    return (statusRank[targetStatus] ?? 0) > (statusRank[current] ?? 0);
};

const syncUserReason = () => {
    respondForm.reason = props.userParticipation?.user_status_reason || '';
};

watch(
    () => props.userParticipation,
    () => {
        syncUserReason();
    },
    { immediate: true }
);

watch(
    () => props.userAllowedRoles,
    (roles) => {
        if (Array.isArray(roles) && roles.length) {
            joinForm.role = roles.includes(joinForm.role) ? joinForm.role : roles[0];
        } else {
            joinForm.role = props.allowedRoles?.[0] || 'player';
        }
    },
    { immediate: true }
);

const submitJoin = () => {
    if (isExpired.value) {
        return;
    }
    const isLimitRole = joinForm.role === props.limitRole;
    joinForm.status = isLimitReached.value && isLimitRole ? 'reserve' : 'confirmed';
    joinForm.post(`/events/${props.event?.id}/participants/join`, {
        preserveScroll: true,
    });
};

const respondInvitation = (status) => {
    if (isExpired.value) {
        return;
    }
    respondForm.status = status;
    respondForm.reason = props.userParticipation?.user_status_reason || '';
    respondForm.clearErrors();
    respondOpen.value = true;
};

const closeRespond = () => {
    respondOpen.value = false;
    respondForm.reset('status', 'reason');
    respondForm.clearErrors();
};

const submitRespond = () => {
    respondForm.post(`/events/${props.event?.id}/participants/${props.userParticipation?.id}/respond`, {
        preserveScroll: true,
        onSuccess: () => {
            closeRespond();
        },
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader :app-name="appName" :is-authenticated="isAuthenticated" :login-label="loginLabel" />

            <main class="grid gap-6">
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex-1">
                            <h1 class="text-3xl font-semibold text-slate-900">
                                {{ event?.title || 'Событие' }}
                            </h1>
                            <p class="mt-2 text-sm text-slate-600">
                                {{ event?.type?.label || 'Тип не задан' }}
                            </p>
                            <p v-if="isExpired" class="mt-2 text-sm font-semibold text-rose-600">
                                Событие завершено. Действия недоступны.
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата</span>
                        <div class="mt-1">{{ formatDateRange(event?.starts_at, event?.ends_at) }}</div>
                    </div>

                    <div class="mt-3 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Стоимость участия</span>
                        <div class="mt-1">{{ perParticipantCost ? formatAmount(perParticipantCost) : '—' }}</div>
                    </div>

                    <div class="mt-3 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Участники</span>
                        <div class="mt-1">{{ participantsCount }}/{{ participantsLimitLabel }}</div>
                    </div>

                    <div class="mt-3 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">В резерве</span>
                        <div class="mt-1">{{ reserveCount }}</div>
                    </div>

                    <div
                        v-if="event?.approved_venue?.alias && event?.approved_venue?.type_slug"
                        class="mt-3 text-sm text-slate-700"
                    >
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Площадка</span>
                        <div class="mt-1">
                            <a
                                class="font-semibold text-slate-900 transition hover:text-slate-700"
                                :href="`/venues/${event.approved_venue.type_slug}/${event.approved_venue.alias}`"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                {{ event.approved_venue.name || 'Площадка' }}
                            </a>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white px-4 py-3 text-sm text-slate-700">
                        <div class="text-xs uppercase tracking-[0.15em] text-slate-500">Участие</div>
                        <div v-if="userParticipation" class="mt-2 space-y-2">
                            <div class="text-sm text-slate-700">
                                {{ resolveParticipantStatusLabel(userParticipation.status, userParticipation.status_changed_by) }}
                            </div>
                            <div v-if="userParticipation.status === 'invited' && userParticipation.status_change_reason" class="text-sm text-slate-600">
                                Комментарий организатора: {{ userParticipation.status_change_reason }}
                            </div>
                            <p
                                v-if="userParticipation.status_changed_by && userParticipation.status !== 'invited' && isUpgradeBlocked('confirmed')"
                                class="text-xs text-slate-500"
                            >
                                Статус изменён организатором, изменение недоступно.
                            </p>
                            <div v-if="!isExpired" class="flex flex-wrap gap-2">
                                <button
                                    v-if="userParticipation.status !== 'confirmed' && !(isLimitReached && props.limitRole === userParticipation.role)"
                                    class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                                    type="button"
                                    :disabled="respondForm.processing || isUpgradeBlocked('confirmed')"
                                    @click="respondInvitation('confirmed')"
                                >
                                    Подтвердить
                                </button>
                                <button
                                    v-if="userParticipation.status !== 'reserve'"
                                    class="rounded-full border border-amber-600 bg-amber-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-amber-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                                    type="button"
                                    :disabled="respondForm.processing || isUpgradeBlocked('reserve')"
                                    @click="respondInvitation('reserve')"
                                >
                                    В резерв
                                </button>
                                <button
                                    v-if="userParticipation.status !== 'declined'"
                                    class="rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300 disabled:cursor-not-allowed disabled:text-slate-400"
                                    type="button"
                                    :disabled="respondForm.processing"
                                    @click="respondInvitation('declined')"
                                >
                                    Отказаться
                                </button>
                            </div>
                        </div>
                        <form v-else-if="!isExpired" class="mt-2 grid gap-3 sm:grid-cols-[1fr_auto]" @submit.prevent="submitJoin">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Роль
                                <select
                                    v-model="joinForm.role"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    <option v-for="role in participantRoles" :key="role.value" :value="role.value">
                                        {{ role.label }}
                                    </option>
                                </select>
                            </label>
                            <div class="flex items-end">
                                <button
                                    class="w-full rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                                    type="submit"
                                    :disabled="joinForm.processing"
                                >
                                    {{ isLimitReached ? 'В резерв' : 'Участвовать' }}
                                </button>
                            </div>
                            <div v-if="joinForm.errors.role" class="text-xs text-rose-700">
                                {{ joinForm.errors.role }}
                            </div>
                        </form>
                    </div>

                    <div v-if="event?.organizer?.login" class="mt-3 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Организатор</span>
                        <div class="mt-1">{{ event.organizer.login }}</div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>

    <div v-if="respondOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: respondForm.processing }" @submit.prevent="submitRespond">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Изменить статус участия</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeRespond"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        Укажите причину изменения статуса (необязательно).
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Новый статус</span>
                            <span>{{ resolveParticipantStatusLabel(respondForm.status, page.props.auth?.user?.id) }}</span>
                        </div>
                    </div>
                    <textarea
                        v-model="respondForm.reason"
                        class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        placeholder="Причина (необязательно)"
                    ></textarea>
                    <div v-if="respondForm.errors.reason" class="mt-2 text-xs text-rose-700">
                        {{ respondForm.errors.reason }}
                    </div>
                    <div v-if="respondForm.errors.status" class="mt-2 text-xs text-rose-700">
                        {{ respondForm.errors.status }}
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="respondForm.processing"
                        @click="closeRespond"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="respondForm.processing"
                    >
                        Подтвердить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
