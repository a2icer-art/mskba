<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
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
        default: () => ({ title: 'Площадки', items: [] }),
    },
    activeTypeSlug: {
        type: String,
        default: '',
    },
    types: {
        type: Array,
        default: () => [],
    },
    editableFields: {
        type: Array,
        default: () => [],
    },
    canEdit: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const hasSidebar = computed(() => (props.navigation?.items?.length ?? 0) > 0);
const moderationForm = useForm({});
const moderationNotice = ref('');
const moderationErrors = ref([]);
const editOpen = ref(false);
const editNotice = ref('');
const editForm = useForm({
    name: '',
    address: '',
    venue_type_id: '',
    commentary: '',
});

const availableTypes = computed(() => props.types ?? []);
const editableFields = computed(() => props.editableFields ?? []);
const canEdit = computed(() => props.canEdit);
const isVenueConfirmed = computed(() => props.venue?.status === 'confirmed');

const moderationRequest = computed(() => props.moderationRequest ?? null);
const isModerationPending = computed(() => moderationRequest.value?.status === 'pending');
const isModerationRejected = computed(() => moderationRequest.value?.status === 'rejected');
const moderationRejectedAt = computed(() => moderationRequest.value?.reviewed_at ?? moderationRequest.value?.submitted_at);
const moderationRejectedReason = computed(() => moderationRequest.value?.reject_reason ?? '');
const hasModerationRejectReason = computed(() => Boolean(moderationRequest.value?.reject_reason));
const canResubmitModeration = computed(() => hasModerationRejectReason.value);

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

const openEdit = () => {
    editNotice.value = '';
    editForm.clearErrors();
    editForm.name = props.venue?.name ?? '';
    editForm.address = props.venue?.address ?? '';
    editForm.venue_type_id = props.venue?.venue_type_id ?? props.venue?.type?.id ?? '';
    editForm.commentary = props.venue?.commentary ?? '';
    editOpen.value = true;
};

const closeEdit = () => {
    editOpen.value = false;
    editForm.reset('name', 'address', 'venue_type_id', 'commentary');
    editForm.clearErrors();
};

const submitEdit = () => {
    editNotice.value = '';
    editForm.transform((data) => {
        const filtered = {};
        editableFields.value.forEach((field) => {
            if (field in data) {
                filtered[field] = data[field];
            }
        });
        return filtered;
    }).patch(`/venues/${props.activeTypeSlug}/${props.venue?.alias}`, {
        preserveScroll: true,
        onSuccess: () => {
            editNotice.value = 'Площадка обновлена.';
        },
    });
};

const submitModerationRequest = () => {
    moderationNotice.value = '';
    moderationErrors.value = [];

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
            if (page.props?.errors?.moderation) {
                moderationErrors.value = page.props.errors.moderation.split('\n').filter(Boolean);
            }
        },
    });
};
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <section class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :items="navigation.items"
                    :active-href="activeTypeSlug ? `/venues/${activeTypeSlug}` : ''"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Площадки</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">{{ venue?.name || 'Площадка' }}</h1>
                        </div>
                        <button
                            v-if="canEdit"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openEdit"
                        >
                            Редактировать
                        </button>
                    </div>

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
                                    v-if="isModerationRejected && canResubmitModeration && venue?.status === 'unconfirmed'"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-3 py-1 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="button"
                                    :disabled="moderationForm.processing"
                                    @click="submitModerationRequest"
                                >
                                    Отправить повторно
                                </button>
                                <button
                                    v-else-if="!isModerationPending && !isModerationRejected && venue?.status === 'unconfirmed'"
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
                                Причина отклонения пока не указана. Повторная отправка станет доступна после комментария модератора.
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
                            <span class="text-sm font-medium text-slate-800">{{ venue?.address || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                            <span class="text-sm font-medium text-slate-800">{{ venue?.commentary || '—' }}</span>
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
            </section>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <form :class="{ loading: editForm.processing }" @submit.prevent="submitEdit">
                <h2 class="text-lg font-semibold text-slate-900">Редактировать площадку</h2>
                <p class="mt-2 text-sm text-slate-600">Заполните доступные поля площадки.</p>
                <p v-if="isVenueConfirmed" class="mt-2 text-xs text-slate-500">
                    Часть полей недоступна для редактирования так как запись уже подтверждена.
                </p>

                <div class="mt-4 flex flex-col gap-3">
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Тип
                        <select
                            v-model="editForm.venue_type_id"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            :disabled="!editableFields.includes('venue_type_id')"
                        >
                            <option value="">Выберите тип</option>
                            <option v-for="type in availableTypes" :key="type.id" :value="type.id">
                                {{ type.name }}
                            </option>
                        </select>
                    </label>
                    <div v-if="editForm.errors.venue_type_id" class="text-xs text-rose-700">
                        {{ editForm.errors.venue_type_id }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Название
                        <input
                            v-model="editForm.name"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="text"
                            placeholder="Например, Арена 11"
                            :disabled="!editableFields.includes('name')"
                        />
                    </label>
                    <div v-if="editForm.errors.name" class="text-xs text-rose-700">
                        {{ editForm.errors.name }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Адрес
                        <input
                            v-model="editForm.address"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="text"
                            placeholder="Адрес площадки"
                            :disabled="!editableFields.includes('address')"
                        />
                    </label>
                    <div v-if="editForm.errors.address" class="text-xs text-rose-700">
                        {{ editForm.errors.address }}
                    </div>

                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Комментарий
                        <textarea
                            v-model="editForm.commentary"
                            class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            placeholder="Дополнительная информация"
                            :disabled="!editableFields.includes('commentary')"
                        ></textarea>
                    </label>
                    <div v-if="editForm.errors.commentary" class="text-xs text-rose-700">
                        {{ editForm.errors.commentary }}
                    </div>
                </div>

                <div v-if="editForm.errors.venue" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                    {{ editForm.errors.venue }}
                </div>
                <div v-else-if="editNotice" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                    {{ editNotice }}
                </div>

                <div class="mt-6 flex flex-wrap justify-end gap-3">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="editForm.processing"
                        @click="closeEdit"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                        type="submit"
                        :disabled="editForm.processing || !editableFields.length"
                    >
                        Сохранить
                    </button>
                </div>
                </form>
            </div>
        </div>
    </main>
</template>
