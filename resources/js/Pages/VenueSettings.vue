<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import SystemNoticeStack from '../Components/SystemNoticeStack.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    venue: {
        type: Object,
        default: null,
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    amenities: {
        type: Array,
        default: () => [],
    },
    customAmenities: {
        type: Array,
        default: () => [],
    },
    selectedAmenityIds: {
        type: Array,
        default: () => [],
    },
    amenityNotes: {
        type: Object,
        default: () => ({}),
    },
    paymentOrderOptions: {
        type: Array,
        default: () => [],
    },
    paymentRecipientSources: {
        type: Array,
        default: () => [],
    },
    paymentMethods: {
        type: Array,
        default: () => [],
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
});

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const adminNavigationData = computed(() => props.navigation?.admin ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasAdminSidebar = computed(() => (adminNavigationData.value?.length ?? 0) > 0);
const hasAnySidebar = computed(() => hasSidebar.value || hasAdminSidebar.value);
const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const localNotice = ref('');
const successNotice = computed(() => actionNotice.value || localNotice.value);
const actionError = computed(() => page.props?.errors ?? {});
const paymentMethodTypes = [
    { value: 'sbp', label: 'СБП' },
    { value: 'balance', label: 'Баланс' },
    { value: 'acquiring', label: 'Эквайринг' },
];
const getPaymentMethodTypeLabel = (value) =>
    paymentMethodTypes.find((item) => item.value === value)?.label || value;
const paymentMethods = computed(() => props.paymentMethods ?? []);
const paymentMethodOpen = ref(false);
const paymentMethodTarget = ref(null);
const paymentMethodForm = useForm({
    type: 'sbp',
    label: '',
    phone: '',
    display_name: '',
    is_active: true,
    sort_order: 0,
});
const deletePaymentMethodForm = useForm({});
const paymentMethodError = computed(() => page.props?.errors?.payment_method ?? '');
const formErrorNotice = computed(() => {
    if (!actionError.value || !Object.keys(actionError.value).length) {
        return '';
    }
    return 'Не удалось сохранить изменения. Проверьте значения.';
});

const selectedAmenityIds = ref((props.selectedAmenityIds ?? []).map((id) => Number(id)));
const customAmenityDraft = ref('');
const customAmenityItems = ref(
    (props.customAmenities ?? []).map((item) => ({
        id: item.id,
        name: item.name,
        icon_url: item.icon_url ?? null,
        isNew: false,
    }))
);
const removedCustomAmenityIds = ref([]);
const amenityNotes = ref({ ...(props.amenityNotes ?? {}) });
const normalizedCustomAmenityName = computed(() => customAmenityDraft.value.trim().replace(/\s+/g, ' '));
const hasCustomAmenityName = computed(() => normalizedCustomAmenityName.value.length > 0);
const isCustomAmenityDuplicate = computed(() => {
    const name = normalizedCustomAmenityName.value.toLowerCase();
    if (!name) {
        return false;
    }
    return customAmenityItems.value.some((item) => item.name.toLowerCase() === name);
});
const canAddCustomAmenity = computed(() => hasCustomAmenityName.value && !isCustomAmenityDuplicate.value);

const addCustomAmenity = () => {
    if (!canAddCustomAmenity.value) {
        return;
    }
    customAmenityItems.value.push({
        id: null,
        name: normalizedCustomAmenityName.value,
        icon_url: null,
        isNew: true,
    });
    customAmenityDraft.value = '';
};

const removeCustomAmenity = (amenity) => {
    if (amenity.id) {
        removedCustomAmenityIds.value.push(amenity.id);
        selectedAmenityIds.value = selectedAmenityIds.value.filter((id) => id !== amenity.id);
        delete amenityNotes.value[amenity.id];
    }
    customAmenityItems.value = customAmenityItems.value.filter((item) => item !== amenity);
};

const selectedAmenities = computed(() => {
    const selectedLookup = new Set(selectedAmenityIds.value);
    const standard = (props.amenities ?? []).filter((item) => selectedLookup.has(item.id));
    const custom = customAmenityItems.value.filter((item) => item.id && selectedLookup.has(item.id));
    return [...standard, ...custom];
});

const descriptionModalOpen = ref(false);
const descriptionAmenity = ref(null);
const descriptionDraft = ref('');

const openDescriptionModal = (amenity) => {
    if (!selectedAmenityIds.value.includes(amenity.id)) {
        selectedAmenityIds.value.push(amenity.id);
    }
    descriptionAmenity.value = amenity;
    descriptionDraft.value = amenityNotes.value[amenity.id] ?? '';
    descriptionModalOpen.value = true;
};

const closeDescriptionModal = () => {
    descriptionModalOpen.value = false;
    descriptionAmenity.value = null;
    descriptionDraft.value = '';
};

const saveDescription = () => {
    if (!descriptionAmenity.value) {
        return;
    }
    const trimmed = descriptionDraft.value.trim();
    if (trimmed) {
        amenityNotes.value[descriptionAmenity.value.id] = trimmed;
    } else {
        delete amenityNotes.value[descriptionAmenity.value.id];
    }
    closeDescriptionModal();
};

const isMinutes = ref(false);
const rentalDurationValue = ref(1);
const initialRentalMinutes = props.settings?.rental_duration_minutes ?? 60;
if (initialRentalMinutes % 60 === 0) {
    isMinutes.value = false;
    rentalDurationValue.value = initialRentalMinutes / 60;
} else {
    isMinutes.value = true;
    rentalDurationValue.value = initialRentalMinutes;
}
const rentalDurationMax = computed(() => (isMinutes.value ? 1440 : 24));
const rentalDurationStep = computed(() => (isMinutes.value ? 1 : 0.25));
const canUseRentalHours = computed(() => {
    const current = Number(rentalDurationValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isMinutes.value ? current : current * 60;
    return minutes >= 60;
});


const isPaymentWaitMinutes = ref(false);
const paymentWaitValue = ref(1);
const initialPaymentWait = props.settings?.payment_wait_minutes ?? 60;
if (initialPaymentWait === 0) {
    isPaymentWaitMinutes.value = false;
    paymentWaitValue.value = 0;
} else if (initialPaymentWait % 60 === 0) {
    isPaymentWaitMinutes.value = false;
    paymentWaitValue.value = initialPaymentWait / 60;
} else {
    isPaymentWaitMinutes.value = true;
    paymentWaitValue.value = initialPaymentWait;
}
const paymentWaitMax = computed(() => (isPaymentWaitMinutes.value ? 10080 : 168));
const paymentWaitStep = computed(() => (isPaymentWaitMinutes.value ? 1 : 0.25));
const canUsePaymentWaitHours = computed(() => {
    const current = Number(paymentWaitValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPaymentWaitMinutes.value ? current : current * 60;
    return minutes >= 60;
});


const isPendingReviewMinutes = ref(false);
const pendingReviewValue = ref(1);
const initialPendingReview = props.settings?.pending_review_minutes ?? 120;
if (initialPendingReview === 0) {
    isPendingReviewMinutes.value = false;
    pendingReviewValue.value = 0;
} else if (initialPendingReview % 60 === 0) {
    isPendingReviewMinutes.value = false;
    pendingReviewValue.value = initialPendingReview / 60;
} else {
    isPendingReviewMinutes.value = true;
    pendingReviewValue.value = initialPendingReview;
}
const pendingReviewMax = computed(() => (isPendingReviewMinutes.value ? 10080 : 168));
const pendingReviewStep = computed(() => (isPendingReviewMinutes.value ? 1 : 0.25));
const canUsePendingReviewHours = computed(() => {
    const current = Number(pendingReviewValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingReviewMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isPendingBeforeStartMinutes = ref(false);
const pendingBeforeStartValue = ref(1);
const initialPendingBeforeStart = props.settings?.pending_before_start_minutes ?? 120;
if (initialPendingBeforeStart === 0) {
    isPendingBeforeStartMinutes.value = false;
    pendingBeforeStartValue.value = 0;
} else if (initialPendingBeforeStart % 60 === 0) {
    isPendingBeforeStartMinutes.value = false;
    pendingBeforeStartValue.value = initialPendingBeforeStart / 60;
} else {
    isPendingBeforeStartMinutes.value = true;
    pendingBeforeStartValue.value = initialPendingBeforeStart;
}
const pendingBeforeStartMax = computed(() => (isPendingBeforeStartMinutes.value ? 10080 : 168));
const pendingBeforeStartStep = computed(() => (isPendingBeforeStartMinutes.value ? 1 : 0.25));
const canUsePendingBeforeStartHours = computed(() => {
    const current = Number(pendingBeforeStartValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingBeforeStartMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isPendingWarningMinutes = ref(false);
const pendingWarningValue = ref(1);
const initialPendingWarning = props.settings?.pending_warning_minutes ?? 30;
if (initialPendingWarning === 0) {
    isPendingWarningMinutes.value = false;
    pendingWarningValue.value = 0;
} else if (initialPendingWarning % 60 === 0) {
    isPendingWarningMinutes.value = false;
    pendingWarningValue.value = initialPendingWarning / 60;
} else {
    isPendingWarningMinutes.value = true;
    pendingWarningValue.value = initialPendingWarning;
}
const pendingWarningMax = computed(() => (isPendingWarningMinutes.value ? 10080 : 168));
const pendingWarningStep = computed(() => (isPendingWarningMinutes.value ? 1 : 0.25));
const canUsePendingWarningHours = computed(() => {
    const current = Number(pendingWarningValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isPendingWarningMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isBookingLeadMinutes = ref(false);
const bookingLeadValue = ref(1);
const initialBookingLead = props.settings?.booking_lead_time_minutes ?? 15;
if (initialBookingLead === 0) {
    isBookingLeadMinutes.value = false;
    bookingLeadValue.value = 0;
} else if (initialBookingLead % 60 === 0) {
    isBookingLeadMinutes.value = false;
    bookingLeadValue.value = initialBookingLead / 60;
} else {
    isBookingLeadMinutes.value = true;
    bookingLeadValue.value = initialBookingLead;
}
const bookingLeadMax = computed(() => (isBookingLeadMinutes.value ? 1440 : 24));
const bookingLeadStep = computed(() => (isBookingLeadMinutes.value ? 1 : 0.25));
const canUseBookingLeadHours = computed(() => {
    const current = Number(bookingLeadValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isBookingLeadMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const isBookingMinIntervalMinutes = ref(false);
const bookingMinIntervalValue = ref(1);
const initialBookingMinInterval = props.settings?.booking_min_interval_minutes ?? 30;
if (initialBookingMinInterval === 0) {
    isBookingMinIntervalMinutes.value = false;
    bookingMinIntervalValue.value = 0;
} else if (initialBookingMinInterval % 60 === 0) {
    isBookingMinIntervalMinutes.value = false;
    bookingMinIntervalValue.value = initialBookingMinInterval / 60;
} else {
    isBookingMinIntervalMinutes.value = true;
    bookingMinIntervalValue.value = initialBookingMinInterval;
}
const bookingMinIntervalMax = computed(() => (isBookingMinIntervalMinutes.value ? 1440 : 24));
const bookingMinIntervalStep = computed(() => (isBookingMinIntervalMinutes.value ? 1 : 0.25));
const canUseBookingMinIntervalHours = computed(() => {
    const current = Number(bookingMinIntervalValue.value);
    if (!Number.isFinite(current)) {
        return false;
    }
    const minutes = isBookingMinIntervalMinutes.value ? current : current * 60;
    return minutes >= 60;
});

const form = useForm({
    booking_lead_time_minutes: props.settings?.booking_lead_time_minutes ?? 15,
    booking_min_interval_minutes: props.settings?.booking_min_interval_minutes ?? 30,
    rental_duration_minutes: props.settings?.rental_duration_minutes ?? 60,
    rental_price_rub: props.settings?.rental_price_rub ?? 0,
    payment_order_id: props.settings?.payment_order_id ?? '',
    payment_recipient_source: props.settings?.payment_recipient_source ?? 'auto',
    booking_mode: props.settings?.booking_mode ?? 'instant',
    payment_wait_minutes: props.settings?.payment_wait_minutes ?? 60,
    pending_review_minutes: props.settings?.pending_review_minutes ?? 120,
    pending_before_start_minutes: props.settings?.pending_before_start_minutes ?? 120,
    pending_warning_minutes: props.settings?.pending_warning_minutes ?? 30,
    booking_lead_time_is_minutes: true,
    booking_min_interval_is_minutes: true,
    rental_duration_is_minutes: true,
    payment_wait_is_minutes: true,
    pending_review_is_minutes: true,
    pending_before_start_is_minutes: true,
    pending_warning_is_minutes: true,
    amenity_ids: props.selectedAmenityIds ?? [],
    custom_amenities: [],
    custom_amenities_removed: [],
    amenity_notes: {},
});

const customIconForm = useForm({
    icon: null,
});
const customIconKeys = ref({});

const openPaymentMethod = (method = null) => {
    paymentMethodTarget.value = method;
    paymentMethodForm.clearErrors();
    if (method) {
        paymentMethodForm.type = method.type || 'sbp';
        paymentMethodForm.label = method.label || '';
        paymentMethodForm.phone = method.phone || '';
        paymentMethodForm.display_name = method.display_name || '';
        paymentMethodForm.is_active = Boolean(method.is_active);
        paymentMethodForm.sort_order = Number(method.sort_order || 0);
    } else {
        paymentMethodForm.reset();
        paymentMethodForm.type = 'sbp';
        paymentMethodForm.is_active = true;
        paymentMethodForm.sort_order = 0;
    }
    paymentMethodOpen.value = true;
};

const closePaymentMethod = () => {
    paymentMethodOpen.value = false;
    paymentMethodTarget.value = null;
    paymentMethodForm.reset();
    paymentMethodForm.clearErrors();
};

const submitPaymentMethod = () => {
    if (!props.venue?.alias || !props.activeTypeSlug) {
        return;
    }

    const baseUrl = `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/settings/payment-methods`;
    const targetId = paymentMethodTarget.value?.id;
    const method = targetId ? 'patch' : 'post';
    const url = targetId ? `${baseUrl}/${targetId}` : baseUrl;

    paymentMethodForm[method](url, {
        preserveScroll: true,
        onSuccess: () => {
            closePaymentMethod();
        },
    });
};

const deletePaymentMethod = (method) => {
    if (!method?.id || !props.venue?.alias || !props.activeTypeSlug) {
        return;
    }
    if (!confirm('Удалить метод оплаты?')) {
        return;
    }
    const url = `/venues/${props.activeTypeSlug}/${props.venue.alias}/admin/settings/payment-methods/${method.id}`;
    deletePaymentMethodForm.delete(url, {
        preserveScroll: true,
        onSuccess: () => {
            closePaymentMethod();
        },
    });
};

watch(
    () => paymentMethodForm.type,
    (value) => {
        if (value !== 'sbp') {
            paymentMethodForm.phone = '';
            paymentMethodForm.display_name = '';
        }
    }
);

const submit = () => {
    localNotice.value = '';
    const leadValue = Number(bookingLeadValue.value);
    form.booking_lead_time_minutes = Number.isFinite(leadValue) ? leadValue : null;
    form.booking_lead_time_is_minutes = isBookingLeadMinutes.value;
    const minIntervalValue = Number(bookingMinIntervalValue.value);
    form.booking_min_interval_minutes = Number.isFinite(minIntervalValue) ? minIntervalValue : null;
    form.booking_min_interval_is_minutes = isBookingMinIntervalMinutes.value;
    const durationValue = Number(rentalDurationValue.value);
    form.rental_duration_minutes = Number.isFinite(durationValue) ? durationValue : null;
    form.rental_duration_is_minutes = isMinutes.value;
    const waitValue = Number(paymentWaitValue.value);
    form.payment_wait_minutes = Number.isFinite(waitValue) ? waitValue : null;
    form.payment_wait_is_minutes = isPaymentWaitMinutes.value;
    const reviewValue = Number(pendingReviewValue.value);
    form.pending_review_minutes = Number.isFinite(reviewValue) ? reviewValue : null;
    form.pending_review_is_minutes = isPendingReviewMinutes.value;
    const beforeStartValue = Number(pendingBeforeStartValue.value);
    form.pending_before_start_minutes = Number.isFinite(beforeStartValue) ? beforeStartValue : null;
    form.pending_before_start_is_minutes = isPendingBeforeStartMinutes.value;
    const warningValue = Number(pendingWarningValue.value);
    form.pending_warning_minutes = Number.isFinite(warningValue) ? warningValue : null;
    form.pending_warning_is_minutes = isPendingWarningMinutes.value;
    form.amenity_ids = [...selectedAmenityIds.value];
    form.custom_amenities = customAmenityItems.value
        .filter((item) => item.isNew)
        .map((item) => item.name);
    form.custom_amenities_removed = [...removedCustomAmenityIds.value];
    form.amenity_notes = selectedAmenityIds.value.reduce((result, id) => {
        if (amenityNotes.value[id] !== undefined) {
            result[id] = amenityNotes.value[id];
        }
        return result;
    }, {});
    form.patch(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/admin/settings`, {
        preserveScroll: true,
        onSuccess: () => {
            localNotice.value = actionNotice.value || 'Настройки сохранены.';
        },
    });
};

const uploadCustomIcon = (amenityId, file) => {
    if (!file) {
        return;
    }
    customIconForm.icon = file;
    customIconForm.post(`/venues/${props.activeTypeSlug}/${props.venue?.alias}/admin/settings/amenities/${amenityId}/icon`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            customIconForm.reset('icon');
            customIconKeys.value[amenityId] = (customIconKeys.value[amenityId] ?? 0) + 1;
        },
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="successNotice" :error="formErrorNotice" />

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
                    <h1 class="text-3xl font-semibold text-slate-900">Настройки</h1>
                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <form class="space-y-8" @submit.prevent="submit">
                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Бронирование
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Допустимое время до начала бронирования
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isBookingLeadMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="bookingLeadValue"
                                            type="number"
                                            min="0"
                                            :step="bookingLeadStep"
                                            :max="bookingLeadMax"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.booking_lead_time_minutes" class="text-xs text-rose-700">
                                            {{ actionError.booking_lead_time_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Минимальный интервал бронирования
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isBookingMinIntervalMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="bookingMinIntervalValue"
                                            type="number"
                                            min="1"
                                            :step="bookingMinIntervalStep"
                                            :max="bookingMinIntervalMax"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.booking_min_interval_minutes" class="text-xs text-rose-700">
                                            {{ actionError.booking_min_interval_minutes }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Оплата и режим
                                </p>
                                <div class="grid gap-4 md:grid-cols-3">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Порядок оплаты
                                        </label>
                                        <select
                                            v-model="form.payment_order_id"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        >
                                            <option v-for="option in paymentOrderOptions" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p v-if="actionError.payment_order_id" class="text-xs text-rose-700">
                                            {{ actionError.payment_order_id }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Получатель оплаты
                                        </label>
                                        <select
                                            v-model="form.payment_recipient_source"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        >
                                            <option v-for="option in paymentRecipientSources" :key="option.value" :value="option.value">
                                                {{ option.label }}
                                            </option>
                                        </select>
                                        <p v-if="actionError.payment_recipient_source" class="text-xs text-rose-700">
                                            {{ actionError.payment_recipient_source }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Режим бронирования
                                        </label>
                                        <select
                                            v-model="form.booking_mode"
                                            class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        >
                                            <option value="instant">Мгновенное подтверждение</option>
                                            <option value="approval_required">Подтверждение администратора</option>
                                        </select>
                                        <p v-if="actionError.booking_mode" class="text-xs text-rose-700">
                                            {{ actionError.booking_mode }}
                                        </p>
                                    </div>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Срок ожидания оплаты
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPaymentWaitMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="paymentWaitValue"
                                            type="number"
                                            min="0"
                                            :step="paymentWaitStep"
                                            :max="paymentWaitMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">0 — бессрочно.</p>
                                        <p v-if="actionError.payment_wait_minutes" class="text-xs text-rose-700">
                                            {{ actionError.payment_wait_minutes }}
                                        </p>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Методы оплаты площадки</p>
                                            <p class="text-xs text-slate-500">Используются как фолбэк, если нет активных контрактов.</p>
                                        </div>
                                        <button
                                            type="button"
                                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                            @click="openPaymentMethod()"
                                        >
                                            Добавить метод
                                        </button>
                                    </div>
                                    <div v-if="paymentMethods.length" class="mt-4 grid gap-3">
                                        <div
                                            v-for="method in paymentMethods"
                                            :key="method.id"
                                            class="flex flex-wrap items-start justify-between gap-3 rounded-2xl border border-slate-200 bg-white px-4 py-3"
                                        >
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900">
                                                    {{ method.label }}
                                                    <span class="text-xs text-slate-500">({{ getPaymentMethodTypeLabel(method.type) }})</span>
                                                </p>
                                                <p v-if="method.type === 'sbp'" class="text-xs text-slate-500">
                                                    {{ method.phone }} · {{ method.display_name }}
                                                </p>
                                                <p class="text-xs text-slate-400">
                                                    Статус: {{ method.is_active ? 'Активен' : 'Неактивен' }}
                                                </p>
                                            </div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <button
                                                    type="button"
                                                    class="rounded-full border border-slate-200 px-3 py-1 text-xs text-slate-600 transition hover:border-slate-300"
                                                    @click="openPaymentMethod(method)"
                                                >
                                                    Редактировать
                                                </button>
                                                <button
                                                    type="button"
                                                    class="rounded-full border border-rose-200 px-3 py-1 text-xs text-rose-700 transition hover:border-rose-300"
                                                    @click="deletePaymentMethod(method)"
                                                >
                                                    Удалить
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p v-else class="mt-3 text-sm text-slate-500">Методы оплаты не добавлены.</p>
                                    <p v-if="paymentMethodError" class="mt-3 text-xs text-rose-700">
                                        {{ paymentMethodError }}
                                    </p>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Автоотмена pending
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Время на рассмотрение заявки
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingReviewMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingReviewValue"
                                            type="number"
                                            min="0"
                                            :step="pendingReviewStep"
                                            :max="pendingReviewMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">Считается только в рабочее время площадки.</p>
                                        <p v-if="actionError.pending_review_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_review_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Автоотмена до начала
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingBeforeStartMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingBeforeStartValue"
                                            type="number"
                                            min="0"
                                            :step="pendingBeforeStartStep"
                                            :max="pendingBeforeStartMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">Отсчитывается от времени начала брони.</p>
                                        <p v-if="actionError.pending_before_start_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_before_start_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Предупреждение автоотмены
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isPendingWarningMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="pendingWarningValue"
                                            type="number"
                                            min="0"
                                            :step="pendingWarningStep"
                                            :max="pendingWarningMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p class="text-xs text-slate-500">0 — не отправлять предупреждение.</p>
                                        <p v-if="actionError.pending_warning_minutes" class="text-xs text-rose-700">
                                            {{ actionError.pending_warning_minutes }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Стоимость
                                </p>
                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Длительность аренды
                                        </label>
                                        <label class="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            <input
                                                v-model="isMinutes"
                                                type="checkbox"
                                                class="input-switch"
                                            />
                                            <span>В минутах</span>
                                        </label>
                                        <input
                                            v-model="rentalDurationValue"
                                            type="number"
                                            min="1"
                                            :step="rentalDurationStep"
                                            :max="rentalDurationMax"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.rental_duration_minutes" class="text-xs text-rose-700">
                                            {{ actionError.rental_duration_minutes }}
                                        </p>
                                    </div>

                                    <div class="grid gap-2">
                                        <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            Стоимость аренды (руб.)
                                        </label>
                                        <div class="h-6"></div>
                                        <input
                                            v-model="form.rental_price_rub"
                                            type="number"
                                            min="0"
                                            class="h-12 w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <p v-if="actionError.rental_price_rub" class="text-xs text-rose-700">
                                            {{ actionError.rental_price_rub }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="space-y-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-400">
                                    Опции площадки
                                </p>
                                <div class="grid gap-6 lg:grid-cols-[1.2fr_1fr]">
                                    <div class="space-y-3">
                                        <p class="text-sm font-semibold text-slate-700">
                                            Стандартные опции
                                        </p>
                                        <div v-if="props.amenities?.length" class="grid gap-3 sm:grid-cols-2">
                                            <div
                                                v-for="amenity in props.amenities"
                                                :key="amenity.id"
                                                class="rounded-2xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700"
                                            >
                                                <label class="flex items-center gap-3">
                                                    <input
                                                        v-model="selectedAmenityIds"
                                                        type="checkbox"
                                                        :value="amenity.id"
                                                        class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                                    />
                                                    <div class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                                                        <img
                                                            v-if="amenity.icon_url"
                                                            :src="amenity.icon_url"
                                                            :alt="amenity.name"
                                                            class="h-5 w-5 object-contain"
                                                        />
                                                        <span v-else class="text-[10px] text-slate-400">нет</span>
                                                    </div>
                                                    <span>{{ amenity.name }}</span>
                                                </label>
                                                <button
                                                    type="button"
                                                    class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-slate-800"
                                                    @click="openDescriptionModal(amenity)"
                                                >
                                                    <span>Описание</span>
                                                    <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white text-xs text-slate-600">
                                                        <span v-if="amenityNotes[amenity.id]">✎</span>
                                                        <span v-else>＋</span>
                                                    </span>
                                                </button>
                                            </div>
                                        </div>
                                        <p v-else class="text-sm text-slate-500">
                                            Стандартных опций пока нет.
                                        </p>
                                        <p v-if="actionError.amenity_ids" class="text-xs text-rose-700">
                                            {{ actionError.amenity_ids }}
                                        </p>
                                    </div>

                                    <div class="space-y-3">
                                        <p class="text-sm font-semibold text-slate-700">
                                            Свои опции
                                        </p>
                                        <div class="flex flex-wrap items-center gap-3">
                                            <input
                                                v-model="customAmenityDraft"
                                                type="text"
                                                maxlength="60"
                                                class="h-11 flex-1 rounded-2xl border border-slate-200 px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                                placeholder="Например: сауна, спортбар"
                                            />
                                            <button
                                                type="button"
                                                class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:border-slate-300"
                                                :disabled="!canAddCustomAmenity"
                                                @click="addCustomAmenity"
                                            >
                                                Добавить
                                            </button>
                                        </div>
                                        <p v-if="isCustomAmenityDuplicate" class="text-xs text-rose-700">
                                            Такая опция уже добавлена.
                                        </p>
                                        <div v-if="customAmenityItems.length" class="space-y-2">
                                            <div
                                                v-for="amenity in customAmenityItems"
                                                :key="amenity.id ?? amenity.name"
                                                class="rounded-2xl border border-slate-200/80 px-3 py-2"
                                            >
                                                <div class="flex flex-wrap items-center justify-between gap-3">
                                                    <label class="flex items-center gap-2 text-sm text-slate-700">
                                                        <input
                                                            v-if="amenity.id"
                                                            v-model="selectedAmenityIds"
                                                            type="checkbox"
                                                            :value="amenity.id"
                                                            class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                                        />
                                                        <div class="flex h-7 w-7 items-center justify-center rounded-xl border border-slate-200 bg-slate-50">
                                                            <img
                                                                v-if="amenity.icon_url"
                                                                :src="amenity.icon_url"
                                                                :alt="amenity.name"
                                                                class="h-4 w-4 object-contain"
                                                            />
                                                            <span v-else class="text-[10px] text-slate-400">нет</span>
                                                        </div>
                                                        <span>{{ amenity.name }}</span>
                                                        <span v-if="amenity.isNew" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">
                                                            новая
                                                        </span>
                                                    </label>
                                                    <button
                                                        type="button"
                                                        class="text-xs font-semibold text-rose-600 hover:text-rose-700"
                                                        @click="removeCustomAmenity(amenity)"
                                                    >
                                                        Удалить
                                                    </button>
                                                </div>
                                                <div class="mt-2 flex flex-wrap items-center gap-3">
                                                    <input
                                                        v-if="amenity.id"
                                                        :key="customIconKeys[amenity.id] || 0"
                                                        type="file"
                                                        accept="image/svg+xml,image/png,image/jpeg,image/webp"
                                                        class="text-xs text-slate-500"
                                                        @change="uploadCustomIcon(amenity.id, $event.target.files[0])"
                                                    />
                                                    <button
                                                        v-if="amenity.id"
                                                        type="button"
                                                        class="inline-flex items-center gap-2 text-xs font-semibold text-slate-500 transition hover:text-slate-800"
                                                        @click="openDescriptionModal(amenity)"
                                                    >
                                                        <span>Описание</span>
                                                        <span class="inline-flex h-5 w-5 items-center justify-center rounded-full border border-slate-200 bg-white text-xs text-slate-600">
                                                            <span v-if="amenityNotes[amenity.id]">✎</span>
                                                            <span v-else>＋</span>
                                                        </span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <p v-else class="text-sm text-slate-500">
                                            Добавьте индивидуальные опции для этой площадки.
                                        </p>
                                        <p v-if="actionError.custom_amenities" class="text-xs text-rose-700">
                                            {{ actionError.custom_amenities }}
                                        </p>
                                    </div>
                                </div>
                                <hr class="border-slate-200/80" />
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="submit"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    :disabled="form.processing"
                                >
                                    Сохранить
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <div
            v-if="paymentMethodOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
        >
            <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
                <form :class="{ loading: paymentMethodForm.processing }" @submit.prevent="submitPaymentMethod">
                    <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ paymentMethodTarget ? 'Редактировать метод оплаты' : 'Добавить метод оплаты' }}
                        </h2>
                        <button
                            class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                            type="button"
                            aria-label="Закрыть"
                            @click="closePaymentMethod"
                        >
                            x
                        </button>
                    </div>
                    <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                        <div class="grid gap-3">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Тип
                                <select
                                    v-model="paymentMethodForm.type"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                >
                                    <option v-for="option in paymentMethodTypes" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                            </label>
                            <div v-if="paymentMethodForm.errors.type" class="text-xs text-rose-700">
                                {{ paymentMethodForm.errors.type }}
                            </div>

                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Название
                                <input
                                    v-model="paymentMethodForm.label"
                                    type="text"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    placeholder="Например, основной способ оплаты"
                                />
                            </label>
                            <div v-if="paymentMethodForm.errors.label" class="text-xs text-rose-700">
                                {{ paymentMethodForm.errors.label }}
                            </div>

                            <template v-if="paymentMethodForm.type === 'sbp'">
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Телефон для СБП
                                    <input
                                        v-model="paymentMethodForm.phone"
                                        type="text"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        placeholder="+7 900 000-00-00"
                                    />
                                </label>
                                <div v-if="paymentMethodForm.errors.phone" class="text-xs text-rose-700">
                                    {{ paymentMethodForm.errors.phone }}
                                </div>

                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Отображаемое имя
                                    <input
                                        v-model="paymentMethodForm.display_name"
                                        type="text"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        placeholder="Иванов И. И."
                                    />
                                </label>
                                <div v-if="paymentMethodForm.errors.display_name" class="text-xs text-rose-700">
                                    {{ paymentMethodForm.errors.display_name }}
                                </div>
                            </template>

                            <label class="flex items-center gap-2 text-xs uppercase tracking-[0.15em] text-slate-500">
                                <input v-model="paymentMethodForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-slate-900" />
                                <span>Активен</span>
                            </label>
                        </div>
                    </div>
                    <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                        <button
                            class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                            type="button"
                            :disabled="paymentMethodForm.processing"
                            @click="closePaymentMethod"
                        >
                            Закрыть
                        </button>
                        <button
                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                            type="submit"
                            :disabled="paymentMethodForm.processing"
                        >
                            Сохранить
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div
            v-if="descriptionModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4"
        >
            <div class="w-full max-w-lg rounded-3xl bg-white p-6 shadow-xl">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-lg font-semibold text-slate-900">
                        Описание опции
                    </h3>
                    <button
                        type="button"
                        class="text-slate-400 transition hover:text-slate-600"
                        @click="closeDescriptionModal"
                    >
                        ✕
                    </button>
                </div>
                <p class="mt-2 text-sm text-slate-600">
                    {{ descriptionAmenity?.name || '' }}
                </p>
                <textarea
                    v-model="descriptionDraft"
                    rows="4"
                    class="mt-4 w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm text-slate-700 outline-none transition focus:border-slate-400"
                    placeholder="Краткое описание (например: бесплатная, рядом со входом)"
                ></textarea>
                <div class="mt-4 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-slate-300"
                        @click="closeDescriptionModal"
                    >
                        Отмена
                    </button>
                    <button
                        type="button"
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                        @click="saveDescription"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
