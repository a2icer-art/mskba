<script setup>
import { computed, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import AuthModal from '../Components/AuthModal.vue';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    venues: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Навигация', data: [] }),
    },
    activeType: {
        type: String,
        default: '',
    },
    activeTypeSlug: {
        type: String,
        default: '',
    },
    types: {
        type: Array,
        default: () => [],
    },
    metros: {
        type: Array,
        default: () => [],
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
const userPermissions = computed(() => page.props.auth?.user?.permissions ?? []);
const isUserBlocked = computed(() => page.props.auth?.user?.status === 'blocked');
const canCreateVenue = computed(() => !isUserBlocked.value && userPermissions.value.includes('venue.create'));
const showAuthModal = ref(false);
const authMode = ref('login');
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);

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

const statusFilter = ref('');
const venueQuery = ref('');
const venueFilterId = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;
const myVenuesOnly = ref(false);
const groupByType = ref(false);
const pageIndex = ref(1);
const perPage = 6;

const createOpen = ref(false);
const createNotice = ref('');
const addressQuery = ref('');
const addressSuggestions = ref([]);
const addressSuggestError = ref('');
const addressSuggestLoading = ref(false);
const isApplyingAddress = ref(false);
let addressSuggestTimer = null;
let addressSuggestRequestId = 0;
const createForm = useForm({
    name: '',
    venue_type_id: '',
    city: '',
    metro_id: '',
    street: '',
    building: '',
    str_address: '',
});

const statusOptions = [
    { value: '', label: 'Все статусы' },
    { value: 'confirmed', label: 'Подтвержденные' },
    { value: 'moderation', label: 'На модерации' },
    { value: 'unconfirmed', label: 'Неподтвержденные' },
    { value: 'blocked', label: 'Заблокированные' },
];

const availableTypes = computed(() => props.types ?? []);
const activeTypeOption = computed(() => availableTypes.value.find((type) => type.alias === props.activeType) ?? null);
const addButtonLabel = computed(() => {
    if (!activeTypeOption.value) {
        return 'Добавить площадку';
    }

    return `Добавить ${activeTypeOption.value.name?.toLowerCase()}`;
});

const formatMetroLabel = (metro) => {
    if (!metro) {
        return '';
    }
    return metro.line_name ? `${metro.name} — ${metro.line_name}` : metro.name;
};

const normalizeMetroColor = (metro) => {
    const raw = metro?.line_color ?? metro?.color ?? '';
    const color = raw?.toString?.() ?? '';
    if (!color) return null;
    const trimmed = color.trim();
    if (trimmed.startsWith('#')) return trimmed;
    if (/^[0-9a-fA-F]{6}$/.test(trimmed)) return `#${trimmed}`;
    return trimmed; // could be color name like 'red' or rgba()
};

const normalized = (value) => (value ?? '').toString().toLowerCase();

const pluralize = (value, forms) => {
    const absValue = Math.abs(value);
    const mod100 = absValue % 100;
    if (mod100 >= 11 && mod100 <= 14) {
        return forms[2];
    }
    const mod10 = absValue % 10;
    if (mod10 === 1) {
        return forms[0];
    }
    if (mod10 >= 2 && mod10 <= 4) {
        return forms[1];
    }
    return forms[2];
};

const formatRentalUnit = (minutes) => {
    const value = Number(minutes) || 60;
    if (value % 60 === 0) {
        const hours = value / 60;
        return `${hours} ${pluralize(hours, ['час', 'часа', 'часов'])}`;
    }
    return `${value} мин`;
};

const formatPriceLabel = (hall) => {
    const basePrice = Number(hall?.rental_price_rub) || 0;
    if (basePrice <= 0) {
        return '';
    }
    const totalPrice = Number(hall?.price_with_fee_rub) || basePrice;
    const unitLabel = formatRentalUnit(hall?.rental_duration_minutes);
    return `от ${totalPrice.toLocaleString('ru-RU')} р / ${unitLabel}`;
};

const scheduleVenueSuggestions = (value) => {
    venueFilterId.value = '';
    venueSuggestError.value = '';
    if (venueSuggestTimer) {
        clearTimeout(venueSuggestTimer);
    }
    if (!value || value.trim().length < 2) {
        venueSuggestLoading.value = false;
        venueSuggestions.value = [];
        return;
    }
    venueSuggestTimer = setTimeout(() => {
        fetchVenueSuggestions(value.trim());
    }, 250);
};

const fetchVenueSuggestions = async (query) => {
    const requestId = ++venueSuggestRequestId;
    venueSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/venue-suggest?query=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('suggest_failed');
        }
        const data = await response.json();
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = data?.suggestions ?? [];
        venueSuggestError.value = venueSuggestions.value.length ? '' : 'Варианты не найдены.';
    } catch (error) {
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = [];
        venueSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId === venueSuggestRequestId) {
            venueSuggestLoading.value = false;
        }
    }
};

const applyVenueSuggestion = (suggestion) => {
    venueFilterId.value = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};

const clearVenueSelection = () => {
    venueFilterId.value = '';
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};

const filtered = computed(() => {
    const searchNeedle = normalized(venueQuery.value);

    return props.venues.filter((hall) => {
        if (props.activeType && hall.type?.alias !== props.activeType) {
            return false;
        }

        if (myVenuesOnly.value && !hall.is_my_venue) {
            return false;
        }

        if (statusFilter.value && hall.status !== statusFilter.value) {
            return false;
        }

        if (venueFilterId.value && Number(hall.id) !== Number(venueFilterId.value)) {
            return false;
        }

        if (searchNeedle && !venueFilterId.value) {
            const nameMatch = normalized(hall.name).includes(searchNeedle);
            const addressMatch = normalized(hall.address).includes(searchNeedle);
            const metroMatch = normalized(hall.metro?.name).includes(searchNeedle);
            if (!nameMatch && !addressMatch && !metroMatch) {
                return false;
            }
        }

        return true;
    });
});

const sorted = computed(() => {
    const list = [...filtered.value];

    list.sort((a, b) => {
        const nameA = normalized(a.name);
        const nameB = normalized(b.name);
        return nameA.localeCompare(nameB);
    });

    return list;
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage)));

watch([statusFilter, venueQuery, venueFilterId, myVenuesOnly, groupByType], () => {
    pageIndex.value = 1;
});

watch(isAuthenticated, (value) => {
    if (!value) {
        myVenuesOnly.value = false;
    }
});

watch(totalPages, (value) => {
    if (pageIndex.value > value) {
        pageIndex.value = value;
    }
});

const paged = computed(() => {
    const start = (pageIndex.value - 1) * perPage;
    return sorted.value.slice(start, start + perPage);
});

const grouped = computed(() => {
    if (!groupByType.value) {
        return [{ name: 'Все площадки', items: paged.value }];
    }

    const groups = new Map();
    paged.value.forEach((hall) => {
        const key = hall.type?.name || 'Без типа';
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key).push(hall);
    });

    return Array.from(groups.entries()).map(([name, items]) => ({ name, items }));
});

watch(
    () => props.activeType,
    (value) => {
        pageIndex.value = 1;
        if (activeTypeOption.value) {
            createForm.venue_type_id = activeTypeOption.value.id;
        }
    },
    { immediate: true }
);

const openCreate = () => {
    createNotice.value = '';
    createForm.clearErrors();
    createForm.name = '';
    createForm.venue_type_id = activeTypeOption.value?.id ?? '';
    createForm.city = '';
    createForm.metro_id = '';
    createForm.street = '';
    createForm.building = '';
    createForm.str_address = '';
    addressQuery.value = '';
    addressSuggestions.value = [];
    addressSuggestError.value = '';
    createOpen.value = true;
};

const closeCreate = () => {
    createOpen.value = false;
    createForm.reset('name', 'venue_type_id', 'city', 'metro_id', 'street', 'building', 'str_address');
    createForm.clearErrors();
    addressSuggestions.value = [];
    addressSuggestError.value = '';
};

watch(addressQuery, (value) => {
    const query = value?.trim() ?? '';
    if (query !== '') {
        return;
    }

    createForm.city = '';
    createForm.metro_id = '';
    createForm.street = '';
    createForm.building = '';
    createForm.str_address = '';
    addressSuggestions.value = [];
    addressSuggestError.value = '';
});

const submitCreate = () => {
    createNotice.value = '';
    createForm.post('/venues', {
        preserveScroll: true,
        onSuccess: () => {
            createNotice.value = 'Площадка создана.';
            closeCreate();
        },
    });
};

const canSubmitCreate = computed(() => {
    return Boolean(
        createForm.name?.trim()
            && createForm.venue_type_id
            && createForm.city?.trim()
            && createForm.street?.trim()
            && createForm.building?.trim()
    );
});

const scheduleAddressSuggest = (value) => {
    if (isApplyingAddress.value) {
        isApplyingAddress.value = false;
        return;
    }
    addressSuggestError.value = '';
    createForm.city = '';
    createForm.street = '';
    createForm.building = '';
    createForm.metro_id = '';
    createForm.str_address = '';
    if (addressSuggestTimer) {
        clearTimeout(addressSuggestTimer);
    }

    const query = value?.trim() ?? '';
    if (query.length < 3) {
        addressSuggestions.value = [];
        return;
    }

    addressSuggestTimer = setTimeout(() => {
        fetchAddressSuggestions(query);
    }, 350);
};

const fetchAddressSuggestions = async (query) => {
    const requestId = ++addressSuggestRequestId;
    addressSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/address-suggest?query=${encodeURIComponent(query)}`, {
            credentials: 'same-origin',
        });
        if (!response.ok) {
            if (requestId !== addressSuggestRequestId) {
                return;
            }
            addressSuggestions.value = [];
            addressSuggestError.value = 'Не удалось получить подсказки.';
            return;
        }
        const data = await response.json();
        if (requestId !== addressSuggestRequestId) {
            return;
        }
        addressSuggestions.value = data?.suggestions ?? [];
        if (!addressSuggestions.value.length) {
            addressSuggestError.value = 'Варианты не найдены.';
        } else {
            addressSuggestError.value = '';
        }
    } catch (error) {
        if (requestId !== addressSuggestRequestId) {
            return;
        }
        addressSuggestions.value = [];
        addressSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId !== addressSuggestRequestId) {
            return;
        }
        addressSuggestLoading.value = false;
    }
};

const applyAddressSuggestion = (suggestion) => {
    if (!suggestion?.has_house) {
        addressSuggestError.value = 'Выберите вариант с номером дома.';
        return;
    }
    isApplyingAddress.value = true;
    addressQuery.value = suggestion.label || '';
    createForm.city = suggestion.city || '';
    createForm.street = suggestion.street || '';
    createForm.building = suggestion.building || '';
    createForm.str_address = suggestion.label || '';
    createForm.metro_id = suggestion.metro_id || '';
    addressSuggestions.value = [];
    addressSuggestError.value = '';
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
                @open-login="authMode = 'login'; showAuthModal = true"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeTypeSlug ? `/venues/${activeTypeSlug}` : '/venues'"
                >
                    <details class="event-filters rounded-2xl border border-slate-200/80 bg-white/80 p-4">
                        <summary class="event-filters__summary cursor-pointer text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">
                            Фильтры
                        </summary>
                        <div class="event-filters__body mt-4 flex flex-col gap-3 text-sm text-slate-700 w-full">
                            <div class="relative w-full">
                                <input
                                    v-model="venueQuery"
                                    class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 w-full"
                                    :class="{ 'is-loading': venueSuggestLoading }"
                                    placeholder="Поиск площадки"
                                    type="text"
                                    @input="scheduleVenueSuggestions($event.target.value)"
                                />
                                <div v-if="venueSuggestError" class="text-xs text-rose-700">
                                    {{ venueSuggestError }}
                                </div>
                                <div
                                    v-else-if="!venueSuggestLoading && venueSuggestions.length"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in venueSuggestions"
                                        :key="`${suggestion.id}-${index}`"
                                        class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                        type="button"
                                        @click="applyVenueSuggestion(suggestion)"
                                    >
                                        {{ suggestion.label || suggestion.name }}
                                    </button>
                                </div>
                                <div v-if="venueFilterId" class="flex items-center justify-between text-xs text-slate-500">
                                    <span>Площадка выбрана.</span>
                                    <button
                                        class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                        type="button"
                                        @click="clearVenueSelection"
                                    >
                                        Очистить
                                    </button>
                                </div>
                            </div>
                            <select v-model="statusFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 w-full">
                                <option v-for="status in statusOptions" :key="status.value" :value="status.value">
                                    {{ status.label }}
                                </option>
                            </select>
                            <label class="flex items-center gap-3 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <input v-model="groupByType" class="input-switch" type="checkbox" />
                                Группировать
                            </label>
                            <label class="flex items-center gap-3 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <input
                                    v-model="myVenuesOnly"
                                    class="input-switch"
                                    type="checkbox"
                                    :disabled="!isAuthenticated"
                                />
                                Мои площадки
                            </label>
                        </div>
                    </details>
                </MainSidebar>

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Список площадок</h1>
                        </div>
                        <button
                            v-if="canCreateVenue"
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                            type="button"
                            @click="openCreate"
                        >
                            {{ addButtonLabel }}
                        </button>
                    </div>

                    <div v-if="paged.length" class="mt-6 grid gap-4">
                        <div v-for="group in grouped" :key="group.name" class="space-y-3">
                            <h2 v-if="groupByType" class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ group.name }}
                            </h2>

                            <article
                                v-for="hall in group.items"
                                :key="hall.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span
                                                v-if="hall.status === 'confirmed'"
                                                class="flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.15em] text-emerald-700"
                                                title="Подтверждено"
                                            >
                                                ✓
                                            </span>
                                            <span
                                                v-else-if="hall.status === 'blocked'"
                                                class="flex items-center rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.15em] text-rose-700"
                                                title="Заблокирован"
                                            >
                                                ✕
                                            </span>
                                            <span
                                                v-else-if="hall.status === 'moderation'"
                                                class="flex items-center rounded-full border border-amber-200 bg-amber-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.15em] text-amber-800"
                                                title="На модерации"
                                            >
                                                •
                                            </span>
                                            <span
                                                v-else
                                                class="flex items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.15em] text-slate-700"
                                                title="Не подтвержден"
                                            >
                                                ?
                                            </span>
                                            <h3 class="text-lg font-semibold text-slate-900">
                                            <Link
                                                v-if="hall.type_slug"
                                                class="transition hover:text-slate-700"
                                                :href="`/venues/${hall.type_slug}/${hall.alias}`"
                                            >
                                                {{ hall.name }}
                                            </Link>
                                            <span v-else>{{ hall.name }}</span>
                                            </h3>
                                        </div>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ hall.type?.name || 'Тип не указан' }}
                                        </p>
                                        <p v-if="hall.address" class="mt-2 text-sm text-slate-600">
                                            {{ hall.address }}
                                        </p>
                                        <div v-if="hall.metro" class="flex items-center gap-2 text-sm text-slate-700">
                                            <span
                                                class="h-2.5 w-2.5 rounded-full"
                                                :style="{ backgroundColor: normalizeMetroColor(hall.metro) || '#94a3b8' }"
                                            ></span>
                                            <span>{{ formatMetroLabel(hall.metro) }}</span>
                                        </div>
                                    </div>
                                    <div class="mt-1 text-sm text-slate-600">
                                        <span v-if="formatPriceLabel(hall)">
                                            {{ formatPriceLabel(hall) }}
                                        </span>
                                        <Link
                                            v-else-if="hall.type_slug"
                                            class="font-semibold text-slate-900 transition hover:text-slate-700"
                                            :href="`/venues/${hall.type_slug}/${hall.alias}`"
                                        >
                                            Уточнить цену
                                        </Link>
                                        <span v-else>Уточнить цену</span>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-600">
                        По текущим фильтрам площадок не найдено.
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                        <div>Страница {{ pageIndex }} из {{ totalPages }}</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                class="rounded-full border border-slate-300 px-3 py-1 text-sm transition disabled:opacity-40"
                                :disabled="pageIndex === 1"
                                type="button"
                                @click="pageIndex = Math.max(1, pageIndex - 1)"
                            >
                                Назад
                            </button>
                            <button
                                v-for="pageNumber in totalPages"
                                :key="pageNumber"
                                class="h-9 w-9 rounded-full border text-sm transition"
                                :class="
                                    pageNumber === pageIndex
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 text-slate-700 hover:border-slate-400'
                                "
                                type="button"
                                @click="pageIndex = pageNumber"
                            >
                                {{ pageNumber }}
                            </button>
                            <button
                                class="rounded-full border border-slate-300 px-3 py-1 text-sm transition disabled:opacity-40"
                                :disabled="pageIndex === totalPages"
                                type="button"
                                @click="pageIndex = Math.min(totalPages, pageIndex + 1)"
                            >
                                Вперёд
                            </button>
                        </div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <AuthModal
            :app-name="appName"
            :is-open="showAuthModal"
            :participant-roles="page.props.participantRoles || []"
            :initial-mode="authMode"
            @close="showAuthModal = false"
        />

        <div v-if="createOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeCreate">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: createForm.processing }" @submit.prevent="submitCreate">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Новая площадка</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeCreate"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">Заполните обязательные поля для создания площадки.</p>

                        <div class="mt-4 flex flex-col gap-3">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Тип
                                <select
                                    v-model="createForm.venue_type_id"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    :disabled="Boolean(activeTypeOption)"
                                >
                                    <option value="">Выберите тип</option>
                                    <option v-for="type in availableTypes" :key="type.id" :value="type.id">
                                        {{ type.name }}
                                    </option>
                                </select>
                                <input v-if="activeTypeOption" type="hidden" :value="activeTypeOption.id" />
                            </label>
                            <div v-if="createForm.errors.venue_type_id" class="text-xs text-rose-700">
                                {{ createForm.errors.venue_type_id }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Название
                                <input
                                    v-model="createForm.name"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="text"
                                    placeholder="Например, Арена 11"
                                />
                            </label>
                            <div v-if="createForm.errors.name" class="text-xs text-rose-700">
                                {{ createForm.errors.name }}
                            </div>

                            <div class="relative">
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Адрес
                                    <input
                                        v-model="addressQuery"
                                        @input="scheduleAddressSuggest($event.target.value)"
                                        class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        :class="{ 'is-loading': addressSuggestLoading }"
                                        type="text"
                                        placeholder="Начните вводить адрес"
                                    />
                                </label>
                                <input v-model="createForm.city" type="hidden" />
                                <input v-model="createForm.metro_id" type="hidden" />
                                <input v-model="createForm.street" type="hidden" />
                                <input v-model="createForm.building" type="hidden" />
                                <input v-model="createForm.str_address" type="hidden" />
                                <div v-if="addressSuggestError" class="text-xs text-rose-700">
                                    {{ addressSuggestError }}
                                </div>
                                <div
                                    v-else-if="!addressSuggestLoading && addressSuggestions.length"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in addressSuggestions"
                                        :key="`${suggestion.label}-${index}`"
                                        class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400"
                                        type="button"
                                        :disabled="!suggestion.has_house"
                                        @click="applyAddressSuggestion(suggestion)"
                                    >
                                        {{ suggestion.label }}
                                    </button>
                                </div>
                                <div v-if="createForm.errors.city || createForm.errors.street || createForm.errors.building" class="text-xs text-rose-700">
                                    {{ createForm.errors.city || createForm.errors.street || createForm.errors.building }}
                                </div>
                            </div>
                        </div>

                        <div v-if="createForm.errors.venue" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                            {{ createForm.errors.venue }}
                        </div>
                        <div v-else-if="createNotice" class="mt-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700">
                            {{ createNotice }}
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="createForm.processing"
                            @click="closeCreate"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="createForm.processing || !canSubmitCreate"
                        >
                            Создать
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
