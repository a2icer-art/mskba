<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
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
    moderationRequest: {
        type: Object,
        default: null,
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
    canSubmitModeration: {
        type: Boolean,
        default: false,
    },
});

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const adminNavigationData = computed(() => props.navigation?.admin ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasAdminSidebar = computed(() => (adminNavigationData.value?.length ?? 0) > 0);
const hasAnySidebar = computed(() => hasSidebar.value || hasAdminSidebar.value);

const moderationForm = useForm({});
const moderationNotice = ref('');
const moderationErrors = ref([]);
const moderationRequest = computed(() => props.moderationRequest ?? null);
const isModerationPending = computed(() => moderationRequest.value?.status === 'pending');
const isModerationRejected = computed(() => moderationRequest.value?.status === 'rejected');
const moderationRejectedAt = computed(() => moderationRequest.value?.reviewed_at ?? moderationRequest.value?.submitted_at);
const moderationRejectedReason = computed(() => moderationRequest.value?.reject_reason ?? '');
const hasModerationRejectReason = computed(() => Boolean(moderationRequest.value?.reject_reason));

const submitModerationRequest = () => {
    moderationNotice.value = '';
    moderationErrors.value = [];

    if (!props.canSubmitModeration) {
        moderationErrors.value = ['Недостаточно прав для отправки на модерацию.'];
        return;
    }

    moderationForm.post(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/moderation-request`, {
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
            moderationErrors.value = (moderationForm.errors?.moderation || '')
                .split('\n')
                .filter(Boolean);
        },
    });
};

const formatDate = (value) => {
    if (!value) {
        return '';
    }
    return value;
};

const formatMetroLabel = (metro) => {
    if (!metro) {
        return '—';
    }
    const name = metro.name || '';
    const line = metro.line_name ? ` (${metro.line_name})` : '';
    return `${name}${line}` || '—';
};
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

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasAnySidebar }">
                <div v-if="hasAnySidebar" class="flex flex-col gap-4">
                    <MainSidebar
                        v-if="hasSidebar"
                        :data="navigationData"
                        :active-href="activeHref"
                    />
                    <MainSidebar
                        v-if="hasAdminSidebar"
                        :data="adminNavigationData"
                        :active-href="activeHref"
                    />
                </div>

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <h1 class="text-3xl font-semibold text-slate-900">Общее</h1>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4" :class="{ loading: moderationForm.processing }">
                        <div class="flex items-center justify-between gap-4">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Статус</span>
                            <div class="flex flex-wrap items-center gap-2 text-sm font-medium text-slate-800">
                                <span
                                    v-if="venue?.status === 'confirmed'"
                                    class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700"
                                    :title="formatDate(venue?.confirmed_at)"
                                >
                                    Подтверждено
                                </span>
                                <span
                                    v-else-if="venue?.status === 'blocked'"
                                    class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold text-rose-700"
                                >
                                    Заблокировано
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
                                    v-if="isModerationRejected && canSubmitModeration && venue?.status === 'unconfirmed'"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    :disabled="moderationForm.processing"
                                    @click="submitModerationRequest"
                                >
                                    Отправить повторно
                                </button>
                                <button
                                    v-else-if="!isModerationPending && !isModerationRejected && venue?.status === 'unconfirmed' && canSubmitModeration"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    :disabled="moderationForm.processing"
                                    @click="submitModerationRequest"
                                >
                                    Отправить на модерацию
                                </button>
                            </div>
                        </div>
                        <div class="mt-3">
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
                                Причина отклонения пока не указана.
                            </div>
                            <div v-else-if="venue?.status === 'blocked'" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                                {{ venue?.block_reason || 'Причина блокировки не определена.' }}
                            </div>
                            <div v-else-if="moderationNotice" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                                {{ moderationNotice }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Тип</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.type?.name || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Адрес</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.address?.display || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Метро</span>
                            <span class="text-sm font-medium text-slate-800">{{ formatMetroLabel(venue?.address?.metro) }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.commentary || '-' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Создатель</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.creator?.login || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Создана</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.created_at || '—' }}</span>
                        </div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
