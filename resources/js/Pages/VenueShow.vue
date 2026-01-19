<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthModal from '../Components/AuthModal.vue';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import EventCreateModal from '../Components/EventCreateModal.vue';
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
    about: {
        type: Object,
        default: () => ({}),
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
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
const aboutData = computed(() => props.about ?? {});
const scheduleDays = computed(() => aboutData.value?.schedule_days ?? []);
const scheduleUrl = computed(() => aboutData.value?.schedule_url ?? '');
const feedUrl = computed(() => aboutData.value?.feed_url ?? '');
const ratingValue = computed(() => aboutData.value?.rating ?? null);
const ratingCount = computed(() => aboutData.value?.rating_count ?? null);
const mapApiKey = computed(() => aboutData.value?.map_api_key ?? '');
const addressRouteUrl = computed(() => {
    const address = props.venue?.address?.display;
    if (!address) {
        return '';
    }
    return `https://yandex.ru/maps/?text=${encodeURIComponent(address)}`;
});
const editableFields = computed(() => props.editableFields ?? []);
const addressEditable = computed(() =>
    ['city', 'street', 'building', 'metro_id'].some((field) => editableFields.value.includes(field))
);
const canEdit = computed(() => props.canEdit);
const canSubmitModeration = computed(() => props.canSubmitModeration);
const isVenueConfirmed = computed(() => props.venue?.status === 'confirmed');
const isVenueOnModeration = computed(() => props.venue?.status === 'moderation');
const isVenueUnavailableForBooking = computed(() => props.venue?.status && props.venue?.status !== 'confirmed');
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

watch(
    () => scheduleDays.value.length,
    () => {
        nextTick(() => {
            refreshAnchorMetrics();
        });
    }
);

const anchorSections = [
    { id: 'address', label: 'Адрес' },
    { id: 'schedule', label: 'Расписание' },
    { id: 'posts', label: 'Посты' },
    { id: 'reviews', label: 'Отзывы' },
];
const activeSectionId = ref('address');
const sectionRefs = ref({});
const setSectionRef = (id) => (element) => {
    if (element) {
        sectionRefs.value[id] = element;
    }
};
const anchorNavRef = ref(null);
const anchorNavHeight = ref(0);
const anchorNavOffsetTop = ref(0);
const anchorNavLeft = ref(0);
const anchorNavWidth = ref(0);
const isAnchorSticky = ref(false);

const scrollToSection = (id) => {
    const element = sectionRefs.value[id];
    if (!element) {
        return;
    }
    const topOffset = 96;
    const top = window.scrollY + element.getBoundingClientRect().top - topOffset;
    window.scrollTo({ top, behavior: 'smooth' });
};

let scrollFrame = null;
const refreshAnchorMetrics = () => {
    if (!anchorNavRef.value) {
        return;
    }
    const rect = anchorNavRef.value.getBoundingClientRect();
    anchorNavHeight.value = rect.height;
    anchorNavOffsetTop.value = window.scrollY + rect.top;
    anchorNavLeft.value = rect.left;
    anchorNavWidth.value = rect.width;
};

const updateActiveSection = () => {
    if (scrollFrame) {
        return;
    }
    scrollFrame = window.requestAnimationFrame(() => {
        scrollFrame = null;
        const stickyThreshold = anchorNavOffsetTop.value + anchorNavHeight.value;
        if (anchorNavHeight.value > 0) {
            isAnchorSticky.value = window.scrollY >= stickyThreshold;
        }
        let current = anchorSections[0]?.id ?? '';
        const offset = 120;
        anchorSections.forEach((section) => {
            const element = sectionRefs.value[section.id];
            if (!element) {
                return;
            }
            const top = element.getBoundingClientRect().top;
            if (top - offset <= 0) {
                current = section.id;
            }
        });
        if (current) {
            activeSectionId.value = current;
        }
    });
};

onMounted(() => {
    nextTick(() => {
        refreshAnchorMetrics();
        updateActiveSection();
    });
    window.addEventListener('scroll', updateActiveSection, { passive: true });
    window.addEventListener('resize', refreshAnchorMetrics);
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', updateActiveSection);
    window.removeEventListener('resize', refreshAnchorMetrics);
    if (scrollFrame) {
        window.cancelAnimationFrame(scrollFrame);
        scrollFrame = null;
    }
});

const selectedDay = ref(null);
const showDayBookingForm = ref(false);
const dayLoading = ref(false);
const dayError = ref('');
const openDayDetails = (day) => {
    selectedDay.value = {
        date: day.date,
        is_today: day.is_today,
        is_closed: false,
        is_closed_by_exception: false,
        intervals: [],
        bookings: [],
        comment: null,
    };
    dayLoading.value = true;
    dayError.value = '';
    showDayBookingForm.value = false;
    fetchDayDetails(day.date);
};
const closeDayDetails = () => {
    selectedDay.value = null;
    showDayBookingForm.value = false;
    createPrefill.value = {};
    dayLoading.value = false;
    dayError.value = '';
};
const closeDayBookingForm = () => {
    showDayBookingForm.value = false;
};
const createPrefill = ref({});
const embeddedCreateRef = ref(null);
const bookingFormRef = ref(null);
const popupBodyRef = ref(null);
const scrollPopupToBookingForm = () => {
    const container = popupBodyRef.value;
    const target = bookingFormRef.value;
    if (!container || !target) {
        return;
    }
    const offset = Math.max(target.offsetTop - container.offsetTop - 8, 0);
    container.scrollTo({ top: offset, behavior: 'smooth' });
};
const refreshDayBookings = async () => {
    if (!selectedDay.value?.date || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    try {
        const params = new URLSearchParams({ date: selectedDay.value.date });
        const response = await fetch(
            `/venues/${props.activeTypeSlug}/${props.venue.alias}/schedule-day-bookings?${params.toString()}`
        );
        if (!response.ok) {
            return;
        }
        const data = await response.json();
        selectedDay.value.bookings = data?.bookings ?? [];
    } catch (error) {
        return;
    }
};
const fetchDayDetails = async (date) => {
    if (!date || !props.venue?.alias || !props.activeTypeSlug) {
        dayLoading.value = false;
        return;
    }
    try {
        const params = new URLSearchParams({ date });
        const response = await fetch(
            `/venues/${props.activeTypeSlug}/${props.venue.alias}/schedule-day?${params.toString()}`
        );
        if (!response.ok) {
            throw new Error('day_failed');
        }
        const data = await response.json();
        selectedDay.value = {
            date,
            is_today: data?.is_today ?? false,
            is_closed: data?.is_closed ?? false,
            is_closed_by_exception: data?.is_closed_by_exception ?? false,
            intervals: data?.intervals ?? [],
            bookings: data?.bookings ?? [],
            comment: data?.comment ?? null,
        };
    } catch (error) {
        dayError.value = 'Не удалось загрузить данные дня.';
    } finally {
        dayLoading.value = false;
    }
};
const openBookingFromDay = () => {
    if (!selectedDay.value) {
        return;
    }
    createPrefill.value = {
        venue: props.venue?.alias || '',
        date: selectedDay.value.date || '',
    };
    showDayBookingForm.value = true;
    nextTick(() => {
        scrollPopupToBookingForm();
    });
};
const handleBookingFormLoaded = () => {
    nextTick(() => {
        embeddedCreateRef.value?.focusType?.();
    });
};
const submitEmbeddedBooking = () => {
    if (!embeddedCreateRef.value || embeddedCreateRef.value.isDisabled) {
        return;
    }
    embeddedCreateRef.value.submit();
};
const isEmbeddedSubmitDisabled = computed(() => embeddedCreateRef.value?.isDisabled ?? true);

const formatDayLabel = (date) =>
    new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' }).format(new Date(date));
const formatWeekdayLabel = (date) =>
    new Intl.DateTimeFormat('ru-RU', { weekday: 'short' }).format(new Date(date));
const weekdayHeaders = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];

const mapRef = ref(null);
const mapError = ref('');
let mapInstance = null;

const loadYandexMaps = () =>
    new Promise((resolve, reject) => {
        if (window.ymaps) {
            resolve(window.ymaps);
            return;
        }
        if (!mapApiKey.value) {
            reject(new Error('API key not provided'));
            return;
        }
        const existing = document.querySelector('script[data-ymaps]');
        if (existing) {
            existing.addEventListener('load', () => resolve(window.ymaps));
            existing.addEventListener('error', () => reject(new Error('failed')));
            return;
        }
        const script = document.createElement('script');
        script.src = `https://api-maps.yandex.ru/2.1/?apikey=${mapApiKey.value}&lang=ru_RU`;
        script.async = true;
        script.dataset.ymaps = 'true';
        script.onload = () => resolve(window.ymaps);
        script.onerror = () => reject(new Error('failed'));
        document.head.appendChild(script);
    });

const initMap = async () => {
    if (!mapRef.value) {
        return;
    }
    const address = props.venue?.address?.display;
    if (!address) {
        mapError.value = 'Адрес не указан.';
        return;
    }
    try {
        const ymaps = await loadYandexMaps();
        await ymaps.ready();
        const result = await ymaps.geocode(address);
        const first = result.geoObjects.get(0);
        if (!first) {
            mapError.value = 'Не удалось определить координаты.';
            return;
        }
        const coords = first.geometry.getCoordinates();
        mapInstance = new ymaps.Map(
            mapRef.value,
            {
                center: coords,
                zoom: 15,
                controls: [],
            },
            {
                suppressMapOpenBlock: true,
            }
        );
        mapInstance.behaviors.disable('scrollZoom');
        const placemark = new ymaps.Placemark(coords, {}, { preset: 'islands#redIcon' });
        mapInstance.geoObjects.add(placemark);
    } catch (error) {
        mapError.value = 'Не удалось загрузить карту.';
    }
};

onMounted(() => {
    initMap();
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
    <div class="relative min-h-screen overflow-x-hidden bg-[#f7f1e6] text-slate-900">
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
                    :active-href="activeTypeSlug && venue?.alias ? `/venues/${activeTypeSlug}/${venue?.alias}` : ''"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">{{ venue?.name || 'Площадка' }}</h1>
                            <div v-if="ratingValue !== null" class="mt-2 flex items-center gap-2 text-sm text-slate-700">
                                <div class="flex items-center gap-1 text-amber-400">
                                    <span v-for="index in 5" :key="index">★</span>
                                </div>
                                <span class="font-semibold">{{ ratingValue }}</span>
                                <span v-if="ratingCount !== null" class="text-slate-500">({{ ratingCount }} отзывов)</span>
                            </div>
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

                    <div class="mt-6">
                        <div v-if="isAnchorSticky" :style="{ height: `${anchorNavHeight}px` }"></div>
                        <div
                            ref="anchorNavRef"
                            class="border-b border-slate-200/80 bg-white/90 py-3 backdrop-blur"
                            :class="isAnchorSticky ? 'fixed z-30' : 'relative'"
                            :style="
                                isAnchorSticky
                                    ? { top: '0px', left: `${anchorNavLeft}px`, width: `${anchorNavWidth}px` }
                                    : {}
                            "
                        >
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">Навигация</p>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    v-for="section in anchorSections"
                                    :key="section.id"
                                    type="button"
                                    class="rounded-full border px-4 py-2 text-sm font-semibold transition"
                                    :class="
                                        activeSectionId === section.id
                                            ? 'border-slate-900 bg-slate-900 text-white'
                                            : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                                    "
                                    @click="scrollToSection(section.id)"
                                >
                                    {{ section.label }}
                                </button>
                            </div>
                        </div>

                        <div class="mt-6 space-y-10">
                            <section :id="anchorSections[0].id" :ref="setSectionRef('address')" class="scroll-mt-24 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="text-lg font-semibold text-slate-900">Адрес</h2>
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-400">Карта</span>
                                </div>
                                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                                    <div ref="mapRef" class="h-64 w-full"></div>
                                    <div v-if="mapError" class="border-t border-slate-200 px-4 py-3 text-sm text-rose-700">
                                        {{ mapError }}
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div v-if="venue?.address?.metro" class="flex items-center gap-2 text-sm text-slate-700">
                                        <span
                                            class="h-2.5 w-2.5 rounded-full"
                                            :style="{ backgroundColor: venue?.address?.metro?.line_color || '#94a3b8' }"
                                        ></span>
                                        <span>{{ formatMetroLabel(venue?.address?.metro) }}</span>
                                    </div>
                                    <p class="text-sm text-slate-700">{{ venue?.address?.display || 'Адрес не указан.' }}</p>
                                    <a
                                        v-if="addressRouteUrl"
                                        :href="addressRouteUrl"
                                        class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 transition hover:text-slate-900"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Маршрут
                                    </a>
                                </div>
                            </section>

                            <section :id="anchorSections[1].id" :ref="setSectionRef('schedule')" class="scroll-mt-24 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-semibold text-slate-900">Расписание</h2>
                                        <p class="mt-1 text-sm text-slate-600">Ближайшие две недели.</p>
                                    </div>
                                    <a
                                        :href="scheduleUrl"
                                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800"
                                    >
                                        Подробнее
                                    </a>
                                </div>
                                <div class="grid grid-cols-7 gap-3 text-xs uppercase tracking-[0.2em] text-slate-400">
                                    <span v-for="label in weekdayHeaders" :key="label" class="text-center">{{ label }}</span>
                                </div>
                                <div v-if="scheduleDays.length" class="grid gap-3 md:grid-cols-3 lg:grid-cols-7">
                                    <button
                                        v-for="day in scheduleDays"
                                        :key="day.date"
                                        type="button"
                                        class="relative flex flex-col gap-2 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:border-slate-300"
                                        :class="{ 'ring-2 ring-emerald-200': day.is_today }"
                                        @click="openDayDetails(day)"
                                    >
                                        <div class="text-xs uppercase tracking-[0.2em] text-slate-500">
                                            {{ formatDayLabel(day.date) }}
                                        </div>
                                        <span
                                            class="absolute right-4 top-3.5 h-2.5 w-2.5 rounded-full"
                                            :class="
                                                day.is_closed_by_exception
                                                    ? 'bg-rose-500'
                                                    : day.intervals?.length
                                                        ? 'bg-emerald-500'
                                                        : 'bg-slate-400'
                                            "
                                        ></span>
                                        <span v-if="day.bookings?.length" class="absolute right-4 top-7.5 h-2 w-2 rounded-full bg-violet-500"></span>
                                        <div class="text-sm text-slate-700">
                                            <p v-if="day.is_closed_by_exception" class="text-rose-700">Закрыто</p>
                                            <div v-else-if="day.intervals?.length" class="space-y-1">
                                                <div v-for="(interval, index) in day.intervals" :key="index" class="text-xs font-normal text-slate-700">
                                                    {{ interval.starts_at }}–{{ interval.ends_at }}
                                                </div>
                                            </div>
                                            <p v-else class="text-slate-500">-</p>
                                        </div>
                                    </button>
                                </div>
                                <p v-else class="text-sm text-slate-500">Расписание пока не задано.</p>
                                <div v-if="scheduleDays.length" class="mt-4 flex flex-wrap gap-3 text-xs text-slate-600">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                                        Открыто
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-violet-500"></span>
                                        Есть бронирования
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-slate-400"></span>
                                        Нет интервалов
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                                        Закрыто
                                    </div>
                                </div>
                            </section>

                            <section :id="anchorSections[2].id" :ref="setSectionRef('posts')" class="scroll-mt-24 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="text-lg font-semibold text-slate-900">Последние посты</h2>
                                    <a
                                        :href="feedUrl"
                                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800"
                                    >
                                        Подробнее
                                    </a>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                        <p class="text-sm font-semibold text-slate-900">Турнир выходного дня</p>
                                        <p class="mt-1 text-sm text-slate-600">Скоро анонсируем новые события площадки.</p>
                                        <p class="mt-3 text-xs uppercase tracking-[0.15em] text-slate-400">Раздел в разработке</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                        <p class="text-sm font-semibold text-slate-900">Обновление расписания</p>
                                        <p class="mt-1 text-sm text-slate-600">Следите за свежими новостями и изменениями.</p>
                                        <p class="mt-3 text-xs uppercase tracking-[0.15em] text-slate-400">Раздел в разработке</p>
                                    </div>
                                </div>
                            </section>

                            <section :id="anchorSections[3].id" :ref="setSectionRef('reviews')" class="scroll-mt-24 space-y-4">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="text-lg font-semibold text-slate-900">Отзывы</h2>
                                    <a
                                        :href="feedUrl"
                                        class="rounded-full border border-slate-200 px-4 py-2 text-xs font-semibold text-slate-600 transition hover:border-slate-300 hover:text-slate-800"
                                    >
                                        Подробнее
                                    </a>
                                </div>
                                <div class="grid gap-3 md:grid-cols-2">
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                        <p class="text-sm font-semibold text-slate-900">Отличный зал</p>
                                        <p class="mt-1 text-sm text-slate-600">Покрытие и освещение — супер.</p>
                                        <p class="mt-3 text-xs uppercase tracking-[0.15em] text-slate-400">Раздел в разработке</p>
                                    </div>
                                    <div class="rounded-2xl border border-slate-200 bg-white px-4 py-4">
                                        <p class="text-sm font-semibold text-slate-900">Удобная локация</p>
                                        <p class="mt-1 text-sm text-slate-600">Рядом метро и парковка.</p>
                                        <p class="mt-3 text-xs uppercase tracking-[0.15em] text-slate-400">Раздел в разработке</p>
                                    </div>
                                </div>
                            </section>
                        </div>
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

        <div v-if="selectedDay" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeDayDetails">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-900">Расписание на день</h2>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ formatDayLabel(selectedDay.date) }}
                        </p>
                    </div>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeDayDetails"
                    >
                        x
                    </button>
                </div>
                <div ref="popupBodyRef" class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4" :class="{ loading: dayLoading }">
                    <div v-if="dayError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        {{ dayError }}
                    </div>
                    <div v-else-if="dayLoading" class="flex min-h-[180px] items-center justify-center rounded-2xl border border-slate-200 bg-slate-50">
                        <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
                    </div>
                    <template v-else>
                    <div v-if="selectedDay.is_closed" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                        Площадка закрыта.
                    </div>
                    <div
                        v-else-if="isVenueUnavailableForBooking"
                        class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800"
                    >
                        Бронирование недоступно для неподтвержденной площадки.
                    </div>
                    <div v-else class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-xs font-normal text-slate-600">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">График работы</p>
                        <div v-if="selectedDay.intervals?.length" class="mt-2 space-y-1">
                            <div v-for="(interval, index) in selectedDay.intervals" :key="index">
                                {{ interval.starts_at }}–{{ interval.ends_at }}
                            </div>
                        </div>
                        <p v-else class="mt-2 text-slate-500">Интервалы не заданы.</p>
                    </div>

                    <div v-if="selectedDay.is_closed">
                        <div class="mt-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
                            <p v-if="!selectedDay.is_closed_by_exception" class="text-sm text-slate-600">Интервалы не заданы.</p>
                            <p v-if="selectedDay.comment" class="mt-2 text-sm text-slate-700">{{ selectedDay.comment }}</p>
                        </div>
                    </div>
                    <div v-else-if="!isVenueUnavailableForBooking" class="mt-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Занятые интервалы</p>
                        <div v-if="selectedDay.bookings?.length" class="mt-2 flex flex-wrap gap-2">
                            <span
                                v-for="(booking, index) in selectedDay.bookings"
                                :key="index"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs text-slate-700"
                            >
                                {{ booking.starts_at }}–{{ booking.ends_at }}
                            </span>
                        </div>
                        <p v-else class="mt-2 text-sm text-slate-500">Бронирований нет.</p>
                    </div>

                    <div v-if="showDayBookingForm" ref="bookingFormRef" class="mt-6">
                        <hr class="my-4 border-slate-200/80" />
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Забронировать</p>
                        <div class="mt-2"></div>
                        <EventCreateModal
                            ref="embeddedCreateRef"
                            embedded
                            :prefill="createPrefill"
                            :can-book-fallback="false"
                            :hide-submit="true"
                            @close="closeDayDetails"
                            @loaded="handleBookingFormLoaded"
                            @booking-conflict="refreshDayBookings"
                        />
                    </div>
                    </template>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        v-if="dayLoading"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeDayDetails"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="selectedDay.is_closed"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeDayDetails"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="!dayLoading && !selectedDay.is_closed && isVenueUnavailableForBooking"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeDayDetails"
                    >
                        Закрыть
                    </button>
                    <button
                        v-if="showDayBookingForm && !selectedDay.is_closed"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeDayBookingForm"
                    >
                        Отмена
                    </button>
                    <button
                        v-if="!selectedDay.is_closed && !dayLoading && !isVenueUnavailableForBooking"
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="button"
                        :disabled="showDayBookingForm ? isEmbeddedSubmitDisabled : false"
                        @click="showDayBookingForm ? submitEmbeddedBooking() : openBookingFromDay()"
                    >
                        {{ showDayBookingForm ? 'Подтвердить' : 'Забронировать' }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="editOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4" @click.self="closeEdit">
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: editForm.processing }" @submit.prevent="submitEdit">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">Редактировать площадку</h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closeEdit"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <p class="text-sm text-slate-600">Заполните доступные поля площадки.</p>
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
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
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
