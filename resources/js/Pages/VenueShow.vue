<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthModal from '../Components/AuthModal.vue';
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
    canSubmitModeration: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const showAuthModal = ref(false);
const authMode = ref('login');
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const moderationForm = useForm({});
const moderationNotice = ref('');
const moderationErrors = ref([]);
const editOpen = ref(false);
const editNotice = ref('');
const editAddressQuery = ref('');
const editAddressSuggestions = ref([]);
const editAddressSuggestError = ref('');
const editAddressSuggestLoading = ref(false);
const isApplyingEditAddress = ref(false);
let editAddressSuggestTimer = null;
let editAddressSuggestRequestId = 0;
const editForm = useForm({
    name: '',
    venue_type_id: '',
    commentary: '',
    city: '',
    metro_id: '',
    street: '',
    building: '',
    str_address: '',
});

const availableTypes = computed(() => props.types ?? []);
const editableFields = computed(() => props.editableFields ?? []);
const addressEditable = computed(() =>
    ['city', 'street', 'building', 'metro_id'].some((field) => editableFields.value.includes(field))
);
const canEdit = computed(() => props.canEdit);
const canSubmitModeration = computed(() => props.canSubmitModeration);
const isVenueConfirmed = computed(() => props.venue?.status === 'confirmed');
const isVenueOnModeration = computed(() => props.venue?.status === 'moderation');
const nonEditableItems = computed(() => {
    const addressFields = ['city', 'street', 'building', 'metro_id'];
    const labels = {
        venue_type_id: 'Тип',
        name: 'Название',
        commentary: 'Комментарий',
    };
    const values = {
        venue_type_id: props.venue?.type?.name ?? '-',
        name: props.venue?.name ?? '-',
        commentary: props.venue?.commentary ?? '-',
    };

    const items = Object.keys(labels)
        .filter((field) => !editableFields.value.includes(field))
        .map((field) => ({
            key: field,
            label: labels[field],
            value: values[field] ?? '-',
        }));

    const hasNonEditableAddress = addressFields.some((field) => !editableFields.value.includes(field));
    if (hasNonEditableAddress) {
        const addressParts = [
            props.venue?.address?.city,
            props.venue?.address?.street,
            props.venue?.address?.building,
        ].filter(Boolean);
        const addressValue = addressParts.join(', ') || '-';
        items.push({
            key: 'address',
            label: 'Адрес',
            value: addressValue,
        });
        if (props.venue?.address?.metro) {
            items.push({
                key: 'address_metro',
                label: 'Метро',
                value: formatMetroLabel(props.venue?.address?.metro),
            });
        }
    }

    return items;
});

const moderationRequest = computed(() => props.moderationRequest ?? null);
const isModerationPending = computed(() => moderationRequest.value?.status === 'pending');
const isModerationRejected = computed(() => moderationRequest.value?.status === 'rejected');
const moderationRejectedAt = computed(() => moderationRequest.value?.reviewed_at ?? moderationRequest.value?.submitted_at);
const moderationRejectedReason = computed(() => moderationRequest.value?.reject_reason ?? '');
const hasModerationRejectReason = computed(() => Boolean(moderationRequest.value?.reject_reason));

watch(
    () => page.props.errors,
    (errors) => {
        if (errors?.email || errors?.participant_role_id) {
            authMode.value = 'register';
            showAuthModal.value = true;
            return;
        }

        if (errors?.login) {
            authMode.value = 'login';
            showAuthModal.value = true;
        }
    },
    { immediate: true }
);

const formatDate = (value) => {
    if (!value) {
        return '-';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('ru-RU');
};

const formatMetroLabel = (metro) => {
    if (!metro) {
        return '-';
    }
    return metro.line_name ? `${metro.name} — ${metro.line_name}` : metro.name;
};

const openEdit = () => {
    editNotice.value = '';
    editForm.clearErrors();
    editForm.name = props.venue?.name ?? '';
    editForm.venue_type_id = props.venue?.venue_type_id ?? props.venue?.type?.id ?? '';
    editForm.metro_id = props.venue?.address?.metro_id ?? props.venue?.address?.metro?.id ?? '';
    editForm.commentary = props.venue?.commentary ?? '';
    editForm.city = props.venue?.address?.city ?? '';
    editForm.street = props.venue?.address?.street ?? '';
    editForm.building = props.venue?.address?.building ?? '';
    editForm.str_address = isVenueConfirmed.value || isVenueOnModeration.value
        ? props.venue?.str_address ?? ''
        : props.venue?.address?.str_address ?? '';
    editAddressQuery.value = props.venue?.address?.display ?? '';
    editAddressSuggestions.value = [];
    editAddressSuggestError.value = '';
    editOpen.value = true;
};

const closeEdit = () => {
    editOpen.value = false;
    editForm.reset('name', 'venue_type_id', 'metro_id', 'commentary', 'city', 'street', 'building', 'str_address');
    editForm.clearErrors();
    editAddressQuery.value = '';
    editAddressSuggestions.value = [];
    editAddressSuggestError.value = '';
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

const scheduleEditAddressSuggest = (value) => {
    if (isApplyingEditAddress.value) {
        isApplyingEditAddress.value = false;
        return;
    }
    editAddressSuggestError.value = '';
    editForm.city = '';
    editForm.street = '';
    editForm.building = '';
    editForm.metro_id = '';
    editForm.str_address = '';
    if (editAddressSuggestTimer) {
        clearTimeout(editAddressSuggestTimer);
    }

    const query = value?.trim() ?? '';
    if (query.length < 3) {
        editAddressSuggestions.value = [];
        return;
    }

    editAddressSuggestTimer = setTimeout(() => {
        fetchEditAddressSuggestions(query);
    }, 350);
};

const fetchEditAddressSuggestions = async (query) => {
    const requestId = ++editAddressSuggestRequestId;
    editAddressSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/address-suggest?query=${encodeURIComponent(query)}`, {
            credentials: 'same-origin',
        });
        if (!response.ok) {
            if (requestId !== editAddressSuggestRequestId) {
                return;
            }
            editAddressSuggestions.value = [];
            editAddressSuggestError.value = 'Не удалось получить подсказки.';
            return;
        }
        const data = await response.json();
        if (requestId !== editAddressSuggestRequestId) {
            return;
        }
        editAddressSuggestions.value = data?.suggestions ?? [];
        if (!editAddressSuggestions.value.length) {
            editAddressSuggestError.value = 'Варианты не найдены.';
        } else {
            editAddressSuggestError.value = '';
        }
    } catch (error) {
        if (requestId !== editAddressSuggestRequestId) {
            return;
        }
        editAddressSuggestions.value = [];
        editAddressSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId !== editAddressSuggestRequestId) {
            return;
        }
        editAddressSuggestLoading.value = false;
    }
};

const applyEditAddressSuggestion = (suggestion) => {
    if (!suggestion?.has_house) {
        editAddressSuggestError.value = 'Выберите вариант с номером дома.';
        return;
    }
    isApplyingEditAddress.value = true;
    editAddressQuery.value = suggestion.label || '';
    editForm.city = suggestion.city || '';
    editForm.street = suggestion.street || '';
    editForm.building = suggestion.building || '';
    editForm.str_address = suggestion.label || '';
    editForm.metro_id = suggestion.metro_id || '';
    editAddressSuggestions.value = [];
    editAddressSuggestError.value = '';
};

const submitModerationRequest = () => {
    moderationNotice.value = '';
    moderationErrors.value = [];

    if (!canSubmitModeration.value) {
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
            if (page.props?.errors?.moderation) {
                moderationErrors.value = page.props.errors.moderation.split('\n').filter(Boolean);
            }
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
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
                @open-login="authMode = 'login'; showAuthModal = true"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
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

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeEdit">
            <div class="relative w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
                <button
                    class="absolute right-5 top-5 rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                    type="button"
                    aria-label="Закрыть"
                    @click="closeEdit"
                >
                    x
                </button>
                <form :class="{ loading: editForm.processing }" @submit.prevent="submitEdit">
                <h2 class="text-lg font-semibold text-slate-900">Редактировать площадку</h2>
                <p class="mt-2 text-sm text-slate-600">Заполните доступные поля площадки.</p>
                <div
                    v-if="isVenueConfirmed || isVenueOnModeration"
                    class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600"
                >
                    <p class="font-semibold text-slate-700">
                        {{ isVenueConfirmed ? 'Подтвержденная информация' : 'Данная информация на модерации' }}
                    </p>
                    <div v-if="nonEditableItems.length" class="mt-2 space-y-2">
                        <div v-for="item in nonEditableItems" :key="item.key" class="flex items-center justify-between gap-3">
                            <span class="text-[10px] uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-xs font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3" v-if="editableFields.length">
                    <div v-if="editableFields.includes('venue_type_id')">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Тип
                            <select
                                v-model="editForm.venue_type_id"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
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
                    </div>

                    <div v-if="editableFields.includes('name')">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Название
                            <input
                                v-model="editForm.name"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="text"
                                placeholder="Например, Арена 11"
                            />
                        </label>
                        <div v-if="editForm.errors.name" class="text-xs text-rose-700">
                            {{ editForm.errors.name }}
                        </div>
                    </div>

                    <div v-if="addressEditable && !isVenueConfirmed && !isVenueOnModeration" class="relative">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Адрес
                            <input
                                v-model="editAddressQuery"
                                @input="scheduleEditAddressSuggest($event.target.value)"
                                class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                :class="{ 'is-loading': editAddressSuggestLoading }"
                                type="text"
                                placeholder="Начните вводить адрес"
                            />
                        </label>
                        <input v-model="editForm.city" type="hidden" />
                        <input v-model="editForm.metro_id" type="hidden" />
                        <input v-model="editForm.street" type="hidden" />
                        <input v-model="editForm.building" type="hidden" />
                        <input v-model="editForm.str_address" type="hidden" />
                        <div v-if="editAddressSuggestError" class="text-xs text-rose-700">
                            {{ editAddressSuggestError }}
                        </div>
                        <div
                            v-else-if="!editAddressSuggestLoading && editAddressSuggestions.length"
                            class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                        >
                            <button
                                v-for="(suggestion, index) in editAddressSuggestions"
                                :key="`${suggestion.label}-${index}`"
                                class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400"
                                type="button"
                                :disabled="!suggestion.has_house"
                                @click="applyEditAddressSuggestion(suggestion)"
                            >
                                {{ suggestion.label }}
                            </button>
                        </div>
                        <div v-if="editForm.errors.city || editForm.errors.street || editForm.errors.building" class="text-xs text-rose-700">
                            {{ editForm.errors.city || editForm.errors.street || editForm.errors.building }}
                        </div>
                    </div>

                    <div v-if="editableFields.includes('str_address') && (isVenueConfirmed || isVenueOnModeration)">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Адрес (строкой)
                            <input
                                v-model="editForm.str_address"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="text"
                                placeholder="Например, Россия, Москва, Тверская, 1"
                            />
                        </label>
                        <div v-if="editForm.errors.str_address" class="text-xs text-rose-700">
                            {{ editForm.errors.str_address }}
                        </div>
                    </div>

                    <div v-if="editableFields.includes('commentary')">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий
                            <textarea
                                v-model="editForm.commentary"
                                class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                placeholder="Дополнительная информация"
                            ></textarea>
                        </label>
                        <div v-if="editForm.errors.commentary" class="text-xs text-rose-700">
                            {{ editForm.errors.commentary }}
                        </div>
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

        <AuthModal
            :app-name="appName"
            :is-open="showAuthModal"
            :participant-roles="page.props.participantRoles || []"
            :initial-mode="authMode"
            @close="showAuthModal = false"
        />
    </div>
</template>

