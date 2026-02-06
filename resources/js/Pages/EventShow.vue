<script setup>
import { computed, ref, watch } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import AuthModal from '../Components/AuthModal.vue';
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
    event: {
        type: Object,
        default: null,
    },
    bookings: {
        type: Array,
        default: () => [],
    },
    participants: {
        type: Array,
        default: () => [],
    },
    allowedRoles: {
        type: Array,
        default: () => [],
    },
    limitRole: {
        type: String,
        default: 'player',
    },
    canBook: {
        type: Boolean,
        default: false,
    },
    bookingDeadlinePassed: {
        type: Boolean,
        default: false,
    },
    canDelete: {
        type: Boolean,
        default: false,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    isExpired: {
        type: Boolean,
        default: false,
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Навигация', data: [] }),
    },
    activeTypeCode: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
const showAuthModal = ref(false);
const authMode = ref('login');
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors?.booking ?? '');
const isExpired = computed(() => props.isExpired);
const hasBookings = computed(() => props.bookings.length > 0);
const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasApprovedBooking = computed(() => props.bookings.some((booking) => booking.status === 'approved'));
const hasCancelledBooking = computed(() => props.bookings.some((booking) => booking.status === 'cancelled'));
const participantsConfirmedCount = computed(() =>
    props.participants.filter((participant) => participant.status === 'confirmed' && participant.role === props.limitRole).length
);
const participantsReserveCount = computed(() =>
    props.participants.filter((participant) => participant.status === 'reserve' && participant.role === props.limitRole).length
);
const participantsLimitLabel = computed(() => {
    const limit = Number(eventForm.participants_limit ?? 0);
    if (!limit) {
        return '—';
    }
    return String(limit);
});
const participantRoles = computed(() => {
    const labels = {
        player: 'Игрок',
        coach: 'Тренер',
        referee: 'Судья',
        media: 'Медиа',
        seller: 'Продавец',
        staff: 'Стафф',
    };
    const allowed = props.allowedRoles?.length ? props.allowedRoles : ['player'];
    return allowed.map((value) => ({
        value,
        label: labels[value] || value,
    }));
});
const participantStatusLabels = {
    invited: 'Приглашен',
    pending: 'На рассмотрении',
    confirmed: 'Подтвержден',
    reserve: 'Резерв',
    declined: 'Отказался',
};
const requestSourceLabels = {
    user_request: 'Ожидает решения организатора',
    organizer_invite: 'Ожидает ответа участника',
    user_reconfirm: 'Повторный запрос участника',
};
const participantsByRole = computed(() => {
    return participantRoles.value.map((role) => ({
        ...role,
        items: props.participants.filter((participant) => participant.role === role.value),
    }));
});
const resolvedPriceAmount = computed(() => {
    const current = Number(props.event?.price_amount_minor ?? 0);
    if (current > 0) {
        return current;
    }
    const approvedCost = Number(props.event?.approved_booking_cost_minor ?? 0);
    if (approvedCost > 0) {
        return approvedCost;
    }
    const lastBookingCost = Number(props.event?.last_booking_cost_minor ?? 0);
    if (lastBookingCost > 0) {
        return lastBookingCost;
    }
    return 0;
});
const isPriceFromApprovedBooking = computed(() => {
    const stored = Number(props.event?.price_amount_minor ?? 0);
    const approved = Number(props.event?.approved_booking_cost_minor ?? 0);
    return stored === 0 && approved > 0;
});
const isPriceFromLastBooking = computed(() => {
    const stored = Number(props.event?.price_amount_minor ?? 0);
    const approved = Number(props.event?.approved_booking_cost_minor ?? 0);
    const lastBooking = Number(props.event?.last_booking_cost_minor ?? 0);
    return stored === 0 && approved === 0 && lastBooking > 0;
});
const perParticipantCost = computed(() => {
    const total = Number(eventForm.price_amount_minor ?? 0);
    const limit = Number(eventForm.participants_limit ?? 0);
    if (!total || !limit) {
        return 0;
    }
    return Math.ceil(total / limit);
});
const bookingStatusLabel = (status) => {
    if (status === 'awaiting_payment') {
        return 'Ожидает оплату';
    }
    if (status === 'paid') {
        return 'Оплачено';
    }
    if (status === 'approved') {
        return 'Подтверждено';
    }
    if (status === 'cancelled') {
        return 'Отменено';
    }
    return 'Ожидает';
};

const isOrganizerInvite = (participant) => {
    return participant?.status === 'pending' && participant?.request_source === 'organizer_invite';
};

const shouldShowPaymentDetails = (booking) => {
    if (!booking) {
        return false;
    }
    return ['awaiting_payment', 'paid', 'approved'].includes(booking.status);
};

const formatAmount = (value) => {
    if (value === null || value === undefined || Number.isNaN(Number(value))) {
        return '—';
    }
    return `${Number(value)} ₽`;
};

const resolvePaymentAmount = (booking) => {
    if (!booking) {
        return null;
    }
    if (booking.status === 'paid' || booking.status === 'approved') {
        if (booking.payment_total_amount_minor) {
            return booking.payment_total_amount_minor;
        }
        if (booking.payment_amount_minor) {
            return booking.payment_amount_minor;
        }
        return null;
    }
    if (booking.payment_partial_amount_minor) {
        return booking.payment_partial_amount_minor;
    }
    if (booking.payment_amount_minor) {
        return booking.payment_amount_minor;
    }
    if (booking.payment_total_amount_minor) {
        return booking.payment_total_amount_minor;
    }
    return null;
};

const paymentAmountLabel = (booking) => {
    if (!booking) {
        return 'К оплате';
    }
    if (booking.status === 'paid' || booking.status === 'approved') {
        return 'Оплачено';
    }
    return 'К оплате';
};

const paymentConfirmStatusLabel = (status) => {
    if (status === 'user_paid_pending') {
        return 'Ожидает подтверждения';
    }
    if (status === 'user_paid_rejected') {
        return 'Оплата отклонена';
    }
    if (status === 'admin_confirmed') {
        return 'Оплата подтверждена';
    }
    return 'Не запрошено';
};

const resolveSelectedMethod = (booking) => {
    const methods = Array.isArray(booking?.payment_methods) ? booking.payment_methods : [];
    const selectedId = Number(paymentConfirmForm.payment_method_id);
    if (!selectedId) {
        return methods[0] || null;
    }
    return methods.find((method) => Number(method.id) === selectedId) || methods[0] || null;
};

const isMethodSupported = (method) => {
    return method?.type === 'sbp';
};

const methodDetailsLabel = (method) => {
    if (!method) {
        return '';
    }
    if (method.type === 'sbp') {
        const phone = method.phone || '—';
        const name = method.display_name || '—';
        return `${phone} · ${name}`;
    }
    return 'Способ оплаты пока не поддерживается.';
};

const methodTypeLabel = (type) => {
    if (type === 'sbp') {
        return 'СБП';
    }
    if (type === 'balance') {
        return 'Баланс';
    }
    if (type === 'acquiring') {
        return 'Эквайринг';
    }
    return 'Метод';
};

const isPaymentOverdue = (booking) => {
    if (!booking?.payment_due_at) {
        return false;
    }
    const due = new Date(booking.payment_due_at);
    if (Number.isNaN(due.getTime())) {
        return false;
    }
    return due.getTime() <= Date.now();
};

const organizerPaymentNotice = (booking) => {
    if (!booking) {
        return '';
    }
    if (isPaymentOverdue(booking)) {
        return '';
    }
    if (booking.payment_confirm_status === 'user_paid_pending') {
        return 'Запрос на подтверждение оплаты уже отправлен.';
    }
    if (booking.payment_confirm_status === 'admin_confirmed') {
        return 'Оплата подтверждена администратором.';
    }
    if (booking.payment_confirm_status === 'user_paid_rejected') {
        return 'Оплата отклонена. Можно отправить новый запрос.';
    }
    return 'Отправить подтверждение может только организатор события.';
};

const paymentConfirmCardStatusLabel = (booking) => {
    if (isPaymentOverdue(booking)) {
        return 'Срок оплаты истек';
    }
    return paymentConfirmStatusLabel(booking?.payment_confirm_status);
};

const getSelectedMethodId = (booking) => {
    if (!booking) {
        return '';
    }
    const stored = selectedPaymentMethodIds.value[booking.id];
    if (stored !== undefined && stored !== null) {
        return String(stored);
    }
    const first = booking.payment_methods?.[0];
    return first?.id ? String(first.id) : '';
};

const setSelectedMethodId = (booking, value) => {
    if (!booking) {
        return;
    }
    selectedPaymentMethodIds.value = {
        ...selectedPaymentMethodIds.value,
        [booking.id]: value ? Number(value) : null,
    };
};

const selectedMethodForBooking = (booking) => {
    if (!booking) {
        return null;
    }
    const id = Number(selectedPaymentMethodIds.value[booking.id] ?? 0);
    if (!id) {
        return booking.payment_methods?.[0] ?? null;
    }
    return booking.payment_methods?.find((method) => Number(method.id) === id) || booking.payment_methods?.[0] || null;
};

const bookingOpen = ref(false);
const deleteOpen = ref(false);
const bookingForm = useForm({
    venue_id: '',
    date: '',
    starts_time: '',
    ends_time: '',
    starts_at: '',
    ends_at: '',
});
const paymentConfirmOpen = ref(false);
const paymentConfirmBooking = ref(null);
const paymentConfirmForm = useForm({
    payment_method_id: null,
    comment: '',
    evidence_media: null,
});
const selectedPaymentMethodIds = ref({});
const defaultLeadMinutes = 15;
const defaultMinIntervalMinutes = 30;
const venueSettings = ref({
    leadMinutes: defaultLeadMinutes,
    minIntervalMinutes: defaultMinIntervalMinutes,
});
const eventForm = useForm({
    participants_limit: Number(props.event?.participants_limit ?? 0),
    price_amount_minor: resolvedPriceAmount.value,
});
const inviteForm = useForm({
    login: '',
    user_id: '',
    role: '',
    reason: '',
});
const inviteOpen = ref(false);
const inviteRole = ref(props.allowedRoles?.[0] || 'player');
const inviteTitle = computed(() => {
    const label = participantRoles.value.find((role) => role.value === inviteRole.value)?.label || inviteRole.value;
    return `Пригласить: ${label}`;
});
const participantStatusForm = useForm({
    status: '',
    reason: '',
});
const cancelInviteForm = useForm({});
const statusChangeOpen = ref(false);
const statusChangeTarget = ref(null);
const cancelInviteOpen = ref(false);
const cancelInviteTarget = ref(null);
const userSuggestLoading = ref(false);
const userSuggestError = ref('');
const userSuggestions = ref([]);
let userSuggestTimer = null;
let userSuggestRequestId = 0;
const venueQuery = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;

const openAuthModal = () => {
    authMode.value = 'login';
    showAuthModal.value = true;
};

const openBooking = () => {
    if (isExpired.value) {
        return;
    }
    bookingForm.clearErrors();
    if (!bookingForm.date && props.event?.starts_at) {
        bookingForm.date = toLocalDate(props.event.starts_at);
    }
    if (!bookingForm.starts_time && props.event?.starts_at) {
        bookingForm.starts_time = toLocalTime(props.event.starts_at);
    }
    if (!bookingForm.ends_time && props.event?.ends_at) {
        bookingForm.ends_time = toLocalTime(props.event.ends_at);
    }
    bookingOpen.value = true;
};

const closeBooking = () => {
    bookingForm.reset('venue_id', 'date', 'starts_time', 'ends_time', 'starts_at', 'ends_at');
    bookingForm.clearErrors();
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    bookingOpen.value = false;
};

const submitBooking = () => {
    bookingForm.starts_at = combineDateTime(bookingForm.date, bookingForm.starts_time);
    bookingForm.ends_at = combineDateTime(bookingForm.date, bookingForm.ends_time);
    bookingForm.post(`/events/${props.event?.id}/bookings`, {
        preserveScroll: true,
        onSuccess: closeBooking,
    });
};

const openPaymentConfirm = (booking) => {
    if (!booking?.can_request_payment_confirmation) {
        return;
    }
    paymentConfirmBooking.value = booking;
    paymentConfirmForm.reset();
    paymentConfirmForm.clearErrors();
    const methods = Array.isArray(booking.payment_methods) ? booking.payment_methods : [];
    const storedMethodId = Number(selectedPaymentMethodIds.value[booking.id] ?? 0);
    const preferred = storedMethodId
        ? methods.find((method) => Number(method.id) === storedMethodId)
        : null;
    const defaultMethod =
        preferred
        || methods.find((method) => method?.type === 'sbp' && method?.is_active !== false)
        || methods[0];
    paymentConfirmForm.payment_method_id = defaultMethod?.id ?? null;
    paymentConfirmOpen.value = true;
};

const closePaymentConfirm = () => {
    paymentConfirmOpen.value = false;
    paymentConfirmBooking.value = null;
    paymentConfirmForm.reset();
    paymentConfirmForm.clearErrors();
};

const submitPaymentConfirm = () => {
    if (!paymentConfirmBooking.value) {
        return;
    }
    paymentConfirmForm.post(
        `/events/${props.event?.id}/bookings/${paymentConfirmBooking.value.id}/payment-confirmations`,
        {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: closePaymentConfirm,
        }
    );
};


const copyToClipboard = async (value) => {
    if (!value) {
        return;
    }
    try {
        if (navigator?.clipboard?.writeText) {
            await navigator.clipboard.writeText(String(value));
            return;
        }
    } catch (error) {
        // fallback below
    }
    const textarea = document.createElement('textarea');
    textarea.value = String(value);
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
    } finally {
        document.body.removeChild(textarea);
    }
};

const submitEventDetails = () => {
    eventForm.patch(`/events/${props.event?.id}`, {
        preserveScroll: true,
    });
};

const submitInvite = () => {
    inviteForm.post(`/events/${props.event?.id}/participants/invite`, {
        preserveScroll: true,
        data: {
            login: inviteForm.login,
            user_id: inviteForm.user_id,
            role: inviteForm.role,
            reason: inviteForm.reason,
        },
        onSuccess: () => {
            closeInvite();
        },
    });
};

const openInvite = (role) => {
    if (isExpired.value) {
        return;
    }
    inviteForm.reset('login', 'user_id', 'role', 'reason');
    inviteForm.clearErrors();
    inviteRole.value = role;
    inviteForm.role = role;
    userSuggestions.value = [];
    userSuggestError.value = '';
    inviteOpen.value = true;
};

const closeInvite = () => {
    inviteOpen.value = false;
    inviteForm.reset('login', 'user_id', 'role', 'reason');
    inviteForm.clearErrors();
    userSuggestions.value = [];
    userSuggestError.value = '';
};

const openStatusChange = (participant, status) => {
    if (isExpired.value) {
        return;
    }
    statusChangeTarget.value = participant;
    participantStatusForm.status = status;
    participantStatusForm.reason = '';
    participantStatusForm.clearErrors();
    statusChangeOpen.value = true;
};

const closeStatusChange = () => {
    statusChangeOpen.value = false;
    statusChangeTarget.value = null;
    participantStatusForm.reset('status', 'reason');
    participantStatusForm.clearErrors();
};

const submitStatusChange = () => {
    if (!statusChangeTarget.value) {
        return;
    }

    participantStatusForm.post(
        `/events/${props.event?.id}/participants/${statusChangeTarget.value.id}/status`,
        {
            preserveScroll: true,
            onSuccess: () => {
                closeStatusChange();
            },
        }
    );
};

const openCancelInvite = (participant) => {
    if (!participant?.id) {
        return;
    }
    cancelInviteTarget.value = participant;
    cancelInviteOpen.value = true;
};

const closeCancelInvite = () => {
    cancelInviteOpen.value = false;
    cancelInviteTarget.value = null;
    cancelInviteForm.clearErrors();
};

const submitCancelInvite = () => {
    if (!cancelInviteTarget.value?.id) {
        return;
    }
    cancelInviteForm.post(`/events/${props.event?.id}/participants/${cancelInviteTarget.value.id}/cancel-invite`, {
        preserveScroll: true,
        onSuccess: closeCancelInvite,
    });
};

watch(
    () => props.allowedRoles,
    (roles) => {
        if (Array.isArray(roles) && roles.length) {
            inviteRole.value = roles.includes(inviteRole.value) ? inviteRole.value : roles[0];
            inviteForm.role = inviteRole.value;
        } else {
            inviteRole.value = 'player';
            inviteForm.role = 'player';
        }
    },
    { immediate: true }
);

const scheduleUserSuggestions = (value) => {
    inviteForm.user_id = '';
    userSuggestError.value = '';
    if (userSuggestTimer) {
        clearTimeout(userSuggestTimer);
    }
    if (!value || value.trim().length < 2) {
        userSuggestLoading.value = false;
        userSuggestions.value = [];
        return;
    }
    userSuggestTimer = setTimeout(() => {
        fetchUserSuggestions(value.trim());
    }, 250);
};

const fetchUserSuggestions = async (query) => {
    const requestId = ++userSuggestRequestId;
    userSuggestLoading.value = true;
    try {
        const roleParam = inviteRole.value ? `&role=${encodeURIComponent(inviteRole.value)}` : '';
        const response = await fetch(`/integrations/user-suggest?query=${encodeURIComponent(query)}${roleParam}`);
        if (!response.ok) {
            throw new Error('failed');
        }
        const data = await response.json();
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestions.value = data?.suggestions ?? [];
        userSuggestError.value = userSuggestions.value.length ? '' : 'Варианты не найдены.';
    } catch (error) {
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestions.value = [];
        userSuggestError.value = 'Не удалось получить подсказки.';
    } finally {
        if (requestId !== userSuggestRequestId) {
            return;
        }
        userSuggestLoading.value = false;
    }
};

const applyUserSuggestion = (suggestion) => {
    inviteForm.login = suggestion.login;
    inviteForm.user_id = suggestion.id;
    userSuggestions.value = [];
    userSuggestError.value = '';
};

const clearInviteUserSelection = () => {
    inviteForm.login = '';
    inviteForm.user_id = '';
    userSuggestions.value = [];
    userSuggestError.value = '';
};

const openDelete = () => {
    if (isExpired.value) {
        return;
    }
    deleteOpen.value = true;
};

const closeDelete = () => {
    deleteOpen.value = false;
};

const deleteForm = useForm({});

const submitDelete = () => {
    deleteForm.delete(`/events/${props.event?.id}`, {
        preserveScroll: true,
        onSuccess: closeDelete,
    });
};

const isBookingDisabled = computed(() => {
    if (bookingForm.processing) {
        return true;
    }
    return !bookingForm.venue_id || !bookingForm.date || !bookingForm.starts_time || !bookingForm.ends_time || bookingClientError.value !== '';
});

const scheduleVenueSuggestions = (value) => {
    bookingForm.venue_id = '';
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
    } catch (error) {
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = [];
        venueSuggestError.value = 'Не удалось получить подсказки площадок.';
    } finally {
        if (requestId === venueSuggestRequestId) {
            venueSuggestLoading.value = false;
        }
    }
};

const applyVenueSuggestion = (suggestion) => {
    bookingForm.venue_id = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
    venueSettings.value = {
        leadMinutes: Number(suggestion.booking_lead_time_minutes) || defaultLeadMinutes,
        minIntervalMinutes: Number(suggestion.booking_min_interval_minutes) || defaultMinIntervalMinutes,
    };
};

const clearVenueSelection = () => {
    bookingForm.venue_id = '';
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    venueSettings.value = {
        leadMinutes: defaultLeadMinutes,
        minIntervalMinutes: defaultMinIntervalMinutes,
    };
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return value;
    }
    return date.toLocaleString('ru-RU');
};

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

const toLocalDate = (value) => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (number) => String(number).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const toLocalTime = (value) => {
    if (!value) {
        return '';
    }
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const pad = (number) => String(number).padStart(2, '0');
    return `${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const combineDateTime = (date, time) => {
    if (!date || !time) {
        return '';
    }
    return `${date}T${time}`;
};

const bookingClientError = computed(() => {
    if (!bookingForm.date || !bookingForm.starts_time) {
        return '';
    }
    const start = new Date(`${bookingForm.date}T${bookingForm.starts_time}`);
    if (Number.isNaN(start.getTime())) {
        return '';
    }
    const minStart = new Date();
    minStart.setMinutes(minStart.getMinutes() + venueSettings.value.leadMinutes);
    if (start < minStart) {
        return `Бронирование возможно не ранее чем через ${venueSettings.value.leadMinutes} мин.`;
    }
    if (!bookingForm.ends_time) {
        return '';
    }
    const end = new Date(`${bookingForm.date}T${bookingForm.ends_time}`);
    if (Number.isNaN(end.getTime())) {
        return '';
    }
    const minEnd = new Date(start.getTime() + venueSettings.value.minIntervalMinutes * 60000);
    if (end < minEnd) {
        return `Длительность бронирования должна быть не менее ${venueSettings.value.minIntervalMinutes} мин.`;
    }
    return '';
});

const paymentConfirmClientError = computed(() => {
    if (!paymentConfirmOpen.value) {
        return '';
    }
    const booking = paymentConfirmBooking.value;
    if (!booking) {
        return 'Бронирование не выбрано.';
    }
    const method = resolveSelectedMethod(booking);
    if (!method) {
        return 'Выберите метод оплаты.';
    }
    if (!isMethodSupported(method)) {
        return 'Выбранный способ оплаты пока не поддерживается.';
    }
    const comment = paymentConfirmForm.comment ? paymentConfirmForm.comment.trim() : '';
    const hasFile = !!paymentConfirmForm.evidence_media;
    if (!comment && !hasFile) {
        return 'Добавьте скриншот или комментарий.';
    }
    return '';
});

const isPaymentConfirmDisabled = computed(() => {
    return paymentConfirmForm.processing || paymentConfirmClientError.value !== '';
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" :error="actionError" />

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
                @open-login="openAuthModal"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="props.navigation.title"
                    :data="navigationData"
                    :active-href="props.activeTypeCode ? `/events?type=${props.activeTypeCode}` : '/events'"
                />

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
                        <div class="ml-auto flex flex-wrap items-center justify-end gap-2">
                            <button
                                v-if="canDelete && !isExpired"
                                class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-sm font-semibold text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-400"
                                type="button"
                                @click="openDelete"
                            >
                                Удалить событие
                            </button>
                            <button
                                v-if="canBook && !hasApprovedBooking && !bookingDeadlinePassed && !isExpired"
                                class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700"
                                type="button"
                                @click="openBooking"
                            >
                                Забронировать площадку
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 text-sm text-slate-700">
                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Время</span>
                        <div class="mt-1">{{ formatDateRange(event?.starts_at, event?.ends_at) }}</div>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2 text-xs font-semibold uppercase tracking-[0.15em]">
                        <span
                            v-if="hasApprovedBooking"
                            class="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-emerald-700"
                        >
                            Есть подтвержденное бронирование
                        </span>
                        <span
                            v-if="hasCancelledBooking"
                            class="rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-rose-700"
                        >
                            Есть отмененные бронирования
                        </span>
                    </div>

                    <section class="mt-8 rounded-3xl border border-slate-200/80 bg-white px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Параметры события</h2>
                        </div>

                        <form class="mt-4 grid gap-4" @submit.prevent="submitEventDetails">
                            <div>
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Кол-во участников
                                    <input
                                        v-model.number="eventForm.participants_limit"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        type="number"
                                        min="0"
                                        :disabled="isExpired"
                                    />
                                </label>
                                <p class="mt-1 text-xs text-slate-500">0 — без ограничений.</p>
                                <div v-if="eventForm.errors.participants_limit" class="text-xs text-rose-700">
                                    {{ eventForm.errors.participants_limit }}
                                </div>
                            </div>

                            <div>
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Стоимость события
                                    <input
                                        v-model.number="eventForm.price_amount_minor"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        type="number"
                                        min="0"
                                        :disabled="isExpired"
                                    />
                                </label>
                                <p v-if="isPriceFromApprovedBooking" class="mt-1 text-xs text-slate-500">
                                    По умолчанию подтянута стоимость подтвержденной брони площадки.
                                </p>
                                <p v-if="isPriceFromLastBooking" class="mt-1 text-xs text-slate-500">
                                    По умолчанию указана стоимость последней брони площадки.
                                </p>
                                <div class="mt-3 text-sm text-slate-500">
                                    <p><span class="font-semibold">Участники:</span> {{ participantsConfirmedCount }}/{{ participantsLimitLabel }}</p>
                                    <p><span class="font-semibold">В резерве:</span> {{ participantsReserveCount }}</p>
                                    <p>
                                        <span class="font-semibold">Стоимость для одного участника:</span>
                                        {{ perParticipantCost ? `${perParticipantCost} ₽` : '—' }}
                                    </p>
                                </div>
                                <div v-if="eventForm.errors.price_amount_minor" class="text-xs text-rose-700">
                                    {{ eventForm.errors.price_amount_minor }}
                                </div>
                            </div>

                            <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                Дополнительные опции — заглушка.
                            </div>

                            <div class="flex flex-wrap justify-end gap-3">
                                <button
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500"
                                    type="submit"
                                    :disabled="eventForm.processing || isExpired"
                                >
                                    Сохранить параметры
                                </button>
                            </div>
                        </form>
                    </section>

                    <section class="mt-8 rounded-3xl border border-slate-200/80 bg-white px-5 py-4">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Участники</h2>
                        </div>

                        <div v-if="!isExpired" class="mt-4">
                            <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Пригласить участника</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                <button
                                    v-for="role in participantRoles"
                                    :key="role.value"
                                    class="rounded-full border border-slate-200 bg-white px-3 py-1 text-xs font-semibold text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                                    type="button"
                                    @click="openInvite(role.value)"
                                >
                                    {{ role.label }}
                                </button>
                            </div>
                        </div>

                        <div v-if="participantsByRole.every((group) => group.items.length === 0)" class="mt-4 text-sm text-slate-500">
                            Участники пока не добавлены.
                        </div>

                        <div v-else class="mt-4 grid gap-4">
                            <div v-for="group in participantsByRole" :key="group.value" class="rounded-2xl border border-slate-200/80 bg-white px-4 py-3">
                                <div class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    {{ group.label }}
                                </div>
                                <div v-if="group.items.length === 0" class="mt-2 text-sm text-slate-500">
                                    Нет участников.
                                </div>
                                <ul v-else class="mt-2 space-y-2">
                                    <li v-for="participant in group.items" :key="participant.id" class="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-700">
                                        <div class="flex flex-wrap items-center gap-3">
                                            <span>{{ participant.user?.login || 'Пользователь' }}</span>
                                            <span
                                                class="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-xs font-semibold text-slate-600"
                                                :class="{
                                                    'border-emerald-200 bg-emerald-50 text-emerald-700': participant.status === 'confirmed',
                                                    'border-amber-200 bg-amber-50 text-amber-700': participant.status === 'reserve',
                                                    'border-rose-200 bg-rose-50 text-rose-700': participant.status === 'declined',
                                                    'border-slate-200 bg-slate-100 text-slate-600': participant.status === 'invited',
                                                    'border-blue-200 bg-blue-50 text-blue-700': participant.status === 'pending',
                                                }"
                                            >
                                                {{ participantStatusLabels[participant.status] || participant.status }}
                                            </span>
                                        </div>
                                        <p v-if="participant.status === 'pending'" class="text-xs text-slate-500">
                                            {{ requestSourceLabels[participant.request_source] || 'Заявка на рассмотрении' }}
                                            <span v-if="participant.requested_status">
                                                · Запрос: {{ participantStatusLabels[participant.requested_status] || participant.requested_status }}
                                            </span>
                                        </p>
                                        <div v-if="!isExpired" class="flex flex-wrap items-center gap-2">
                                            <button
                                                v-if="participant.status !== 'confirmed' && participant.status !== 'declined' && !isOrganizerInvite(participant)"
                                                class="rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-800 transition hover:-translate-y-0.5 hover:border-emerald-400"
                                                type="button"
                                                :disabled="participantStatusForm.processing"
                                                @click="openStatusChange(participant, 'confirmed')"
                                            >
                                                Подтвердить
                                            </button>
                                            <button
                                                v-if="participant.status !== 'reserve' && participant.status !== 'declined' && !isOrganizerInvite(participant)"
                                                class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                                type="button"
                                                :disabled="participantStatusForm.processing"
                                                @click="openStatusChange(participant, 'reserve')"
                                            >
                                                В резерв
                                            </button>
                                            <button
                                                v-if="participant.status !== 'declined'"
                                                class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-xs font-semibold text-rose-800 transition hover:-translate-y-0.5 hover:border-rose-400"
                                                type="button"
                                                :disabled="participantStatusForm.processing || (isOrganizerInvite(participant) && cancelInviteForm.processing)"
                                                @click="isOrganizerInvite(participant) ? openCancelInvite(participant) : openStatusChange(participant, 'declined')"
                                            >
                                                {{ isOrganizerInvite(participant) ? 'Отменить приглашение' : 'Отклонить' }}
                                            </button>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </section>

                    <section class="mt-8">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="text-lg font-semibold text-slate-900">Бронирования</h2>
                        </div>

                        <div v-if="!hasBookings" class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">
                            Бронирования пока не созданы.
                        </div>
                        <div v-else class="mt-4 grid gap-3">
                            <div
                                v-for="booking in bookings"
                                :key="booking.id"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3"
                            >
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <p class="text-sm font-semibold text-slate-900">
                                            {{ booking.venue?.name || 'Площадка' }}
                                        </p>
                                        <p class="mt-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                            {{ bookingStatusLabel(booking.status) }}
                                        </p>
                                    </div>
                                    <div class="text-sm text-slate-700">
                                        {{ formatDateRange(booking.starts_at, booking.ends_at) }}
                                    </div>
                                </div>
                                <div v-if="booking.moderation_comment" class="mt-3 text-sm text-slate-700">
                                    <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Комментарий</span>
                                    <p class="mt-1">{{ booking.moderation_comment }}</p>
                                </div>
                                <div v-if="shouldShowPaymentDetails(booking)">
                                    <div v-if="booking.payment_order" class="mt-3 text-sm text-slate-700">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Порядок оплаты</span>
                                        <p class="mt-1">{{ booking.payment_order }}</p>
                                    </div>
                                    <div v-if="booking.payment_methods?.length" class="mt-3 text-sm text-slate-700">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">
                                            {{ isPaymentOverdue(booking) ? 'Метод оплаты' : 'Выбор метода оплаты' }}
                                        </span>
                                        <div class="mt-2 grid gap-2">
                                            <template v-if="isPaymentOverdue(booking)">
                                                <div class="text-sm text-slate-700">
                                                    <p class="mt-1">
                                                        {{ methodTypeLabel(selectedMethodForBooking(booking)?.type) }}
                                                        — {{ selectedMethodForBooking(booking)?.label || '—' }}
                                                    </p>
                                                </div>
                                            </template>
                                            <select
                                                v-else
                                                class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                                :value="getSelectedMethodId(booking)"
                                                :disabled="!booking.can_request_payment_confirmation"
                                                @change="setSelectedMethodId(booking, $event.target.value)"
                                            >
                                                <option
                                                    v-for="method in booking.payment_methods"
                                                    :key="method.id || `${method.type}-${method.label}`"
                                                    :value="method.id"
                                                >
                                                    {{ methodTypeLabel(method.type) }} — {{ method.label }}
                                                </option>
                                            </select>
                                            <p
                                                v-if="selectedMethodForBooking(booking) && !isMethodSupported(selectedMethodForBooking(booking))"
                                                class="text-xs text-rose-700"
                                            >
                                                Способ не поддерживается. Выберите другой метод.
                                            </p>
                                        </div>
                                        <div class="mt-3 flex flex-wrap items-center gap-2">
                                            <button
                                                v-if="booking.can_request_payment_confirmation"
                                                class="rounded-full border border-emerald-600 bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                                type="button"
                                                :disabled="!isMethodSupported(selectedMethodForBooking(booking))"
                                                @click="openPaymentConfirm(booking)"
                                            >
                                                Оплатить
                                            </button>
                                            <p v-else class="text-xs text-slate-500">
                                                {{ organizerPaymentNotice(booking) }}
                                            </p>
                                        </div>
                                    </div>
                                    <div
                                        v-if="booking.status === 'awaiting_payment' && !booking.payment_methods?.length"
                                        class="mt-3 text-sm text-rose-700"
                                    >
                                        Оплата не настроена. Обратитесь к ответственному или в техподдержку сайта.
                                    </div>
                                    <div class="mt-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Подтверждение оплаты</span>
                                            <span class="font-semibold">{{ paymentConfirmCardStatusLabel(booking) }}</span>
                                        </div>
                                        <div v-if="booking.payment_confirmation" class="mt-2 text-xs text-slate-500">
                                            <p v-if="booking.payment_confirmation.payment_method_snapshot?.label" class="mt-1">
                                                Метод: {{ booking.payment_confirmation.payment_method_snapshot.label }}
                                            </p>
                                            <p v-if="booking.payment_confirmation.evidence_comment" class="mt-1 text-slate-600">
                                                Комментарий: {{ booking.payment_confirmation.evidence_comment }}
                                            </p>
                                            <p v-if="isPaymentOverdue(booking)" class="mt-1 text-rose-700">
                                                Срок оплаты истёк. Подтверждение недоступно.
                                            </p>
                                            <a
                                                v-if="booking.payment_confirmation.evidence_media_url"
                                                class="mt-1 inline-flex items-center gap-1 text-xs font-semibold text-slate-700 underline decoration-dotted hover:text-slate-900"
                                                :href="booking.payment_confirmation.evidence_media_url"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                Смотреть скриншот
                                            </a>
                                            <p v-if="booking.payment_confirmation.decision_comment" class="mt-1 text-slate-600">
                                                Комментарий администратора: {{ booking.payment_confirmation.decision_comment }}
                                            </p>
                                        </div>
                                    </div>
                                    <div v-if="booking.payment_code" class="mt-3 text-sm text-slate-700">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Платеж №</span>
                                        <p class="mt-1">{{ booking.payment_code }}</p>
                                    </div>
                                    <div v-if="resolvePaymentAmount(booking)" class="mt-3 text-sm text-slate-700">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">
                                            {{ paymentAmountLabel(booking) }}
                                        </span>
                                        <p class="mt-1">{{ formatAmount(resolvePaymentAmount(booking)) }}</p>
                                    </div>
                                    <div v-if="booking.status === 'awaiting_payment'" class="mt-3 text-sm text-slate-700">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Оплатить до</span>
                                        <p class="mt-1">
                                            {{ booking.payment_due_at ? formatDateTime(booking.payment_due_at) : 'Бессрочно' }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
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
    </div>

    <div v-if="bookingOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: bookingForm.processing }" @submit.prevent="submitBooking">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Новое бронирование</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeBooking"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <div class="relative">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Площадка
                                <input
                                    v-model="venueQuery"
                                    class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    :class="[{ 'is-loading': venueSuggestLoading }, venueQuery ? 'pr-20' : '']"
                                    type="text"
                                    placeholder="Начните вводить название, метро или адрес"
                                    @input="scheduleVenueSuggestions($event.target.value)"
                                />
                                <button
                                    v-if="venueQuery && !venueSuggestLoading"
                                    class="absolute right-3 top-[2.05rem] text-slate-400 transition hover:text-slate-600"
                                    type="button"
                                    aria-label="Очистить площадку"
                                    @click="clearVenueSelection"
                                >
                                    <i class="pi pi-times"></i>
                                </button>
                            </label>
                            <div
                                v-if="venueSuggestError"
                                class="text-xs text-rose-700"
                            >
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
                                    {{ suggestion.label }}
                                </button>
                            </div>
                            <div v-if="bookingForm.venue_id" class="flex items-center justify-between text-xs text-slate-500">
                                <span>Площадка выбрана.</span>
                                <button
                                    class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                    type="button"
                                    @click="clearVenueSelection"
                                >
                                    Очистить
                                </button>
                            </div>
                            <div v-if="bookingForm.errors.venue_id" class="text-xs text-rose-700">
                                {{ bookingForm.errors.venue_id }}
                            </div>
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Дата
                            <input
                                v-model="bookingForm.date"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="date"
                            />
                        </label>
                        <p class="text-xs text-slate-500">
                            Допустимое время до начала: {{ venueSettings.leadMinutes }} мин.
                        </p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Начало
                                <input
                                    v-model="bookingForm.starts_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Окончание
                                <input
                                    v-model="bookingForm.ends_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                        </div>
                        <p class="text-xs text-slate-500">
                            Минимальная длительность: {{ venueSettings.minIntervalMinutes }} мин.
                        </p>
                        <div v-if="bookingForm.errors.starts_at || bookingForm.errors.ends_at" class="text-xs text-rose-700">
                            {{ bookingForm.errors.starts_at || bookingForm.errors.ends_at }}
                        </div>
                        <div v-else-if="bookingClientError" class="text-xs text-rose-700">
                            {{ bookingClientError }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="bookingForm.processing"
                        @click="closeBooking"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="isBookingDisabled"
                    >
                        Создать
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="deleteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: deleteForm.processing }" @submit.prevent="submitDelete">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Удалить событие</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeDelete"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        Вы уверены, что хотите удалить событие «{{ event?.title || 'Событие' }}»?
                    </p>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="deleteForm.processing"
                        @click="closeDelete"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-rose-500 bg-rose-500 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-600 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="deleteForm.processing"
                    >
                        Удалить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="paymentConfirmOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: paymentConfirmForm.processing }" @submit.prevent="submitPaymentConfirm">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Подтверждение оплаты</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closePaymentConfirm"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[520px] overflow-y-auto px-6 pt-4">
                    <div class="grid gap-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <p class="font-semibold text-slate-700">
                                {{ paymentConfirmBooking?.venue?.name || 'Площадка' }}
                            </p>
                            <p class="mt-1">
                                {{ formatDateRange(paymentConfirmBooking?.starts_at, paymentConfirmBooking?.ends_at) }}
                            </p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-600">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Метод оплаты</span>
                                <span class="font-semibold text-slate-700">
                                    {{ methodTypeLabel(resolveSelectedMethod(paymentConfirmBooking)?.type) }}
                                    — {{ resolveSelectedMethod(paymentConfirmBooking)?.label || '—' }}
                                </span>
                            </div>
                            <p
                                v-if="resolveSelectedMethod(paymentConfirmBooking)?.type === 'sbp'"
                                class="mt-2 text-xs text-slate-500"
                            >
                                Для оплаты по СБП переведите сумму на указанный номер/карту и затем
                                прикрепите скриншот операции или опишите детали платежа в комментарии
                                (например, дата и время, сумма, банк/способ перевода).
                            </p>
                            <div
                                v-if="resolveSelectedMethod(paymentConfirmBooking)?.type === 'sbp'"
                                class="mt-2 grid gap-1 text-sm text-slate-600"
                            >
                                <p>
                                    Сумма к оплате:
                                    <span class="font-semibold text-slate-900">
                                        {{ formatAmount(resolvePaymentAmount(paymentConfirmBooking)) }}
                                    </span>
                                </p>
                                <p>
                                    Метод: <span class="font-semibold text-slate-900">СБП</span>
                                </p>
                                <button
                                    class="flex items-center gap-2 text-left text-sm text-slate-600 transition hover:text-slate-900"
                                    type="button"
                                    @click="copyToClipboard(resolveSelectedMethod(paymentConfirmBooking)?.phone)"
                                >
                                    <span>
                                        Номер:
                                        <span class="font-semibold text-slate-900">
                                            {{ resolveSelectedMethod(paymentConfirmBooking)?.phone || '—' }}
                                        </span>
                                    </span>
                                    <svg
                                        class="h-4 w-4 text-slate-500"
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        aria-hidden="true"
                                    >
                                        <rect x="9" y="9" width="13" height="13" rx="2"></rect>
                                        <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                    </svg>
                                </button>
                                <p>
                                    Получатель:
                                    <span class="font-semibold text-slate-900">
                                        {{ resolveSelectedMethod(paymentConfirmBooking)?.display_name || '—' }}
                                    </span>
                                </p>
                            </div>
                        </div>
                        <p
                            v-if="resolveSelectedMethod(paymentConfirmBooking) && !isMethodSupported(resolveSelectedMethod(paymentConfirmBooking))"
                            class="text-xs text-rose-700"
                        >
                            Способ не поддерживается. Выберите другой метод.
                        </p>
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Скриншот оплаты
                            <input
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="file"
                                accept="image/*"
                                @change="paymentConfirmForm.evidence_media = $event.target.files?.[0] ?? null"
                            />
                        </label>
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Комментарий
                            <textarea
                                v-model="paymentConfirmForm.comment"
                                class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                placeholder="Комментарий или детали оплаты"
                            ></textarea>
                        </label>
                        <div v-if="paymentConfirmClientError" class="text-xs text-rose-700">
                            {{ paymentConfirmClientError }}
                        </div>
                        <div v-if="paymentConfirmForm.errors.payment_method_id" class="text-xs text-rose-700">
                            {{ paymentConfirmForm.errors.payment_method_id }}
                        </div>
                        <div v-if="paymentConfirmForm.errors.comment" class="text-xs text-rose-700">
                            {{ paymentConfirmForm.errors.comment }}
                        </div>
                        <div v-if="paymentConfirmForm.errors.evidence_media" class="text-xs text-rose-700">
                            {{ paymentConfirmForm.errors.evidence_media }}
                        </div>
                        <div v-if="paymentConfirmForm.errors.evidence" class="text-xs text-rose-700">
                            {{ paymentConfirmForm.errors.evidence }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="paymentConfirmForm.processing"
                        @click="closePaymentConfirm"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="isPaymentConfirmDisabled"
                    >
                        Оплатить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="cancelInviteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-md rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: cancelInviteForm.processing }" @submit.prevent="submitCancelInvite">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Отменить приглашение</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        :disabled="cancelInviteForm.processing"
                        @click="closeCancelInvite"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[360px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        Вы уверены, что хотите отменить приглашение? Запись участника будет удалена.
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пользователь</span>
                            <span class="font-semibold">{{ cancelInviteTarget?.user?.login || '—' }}</span>
                        </div>
                    </div>
                    <div v-if="cancelInviteForm.errors.participant" class="mt-3 text-xs text-rose-700">
                        {{ cancelInviteForm.errors.participant }}
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="cancelInviteForm.processing"
                        @click="closeCancelInvite"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="cancelInviteForm.processing"
                    >
                        Отменить приглашение
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="statusChangeOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: participantStatusForm.processing }" @submit.prevent="submitStatusChange">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Изменить статус участника</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeStatusChange"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        Укажите причину смены статуса (необязательно).
                    </p>
                    <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пользователь</span>
                            <span class="font-semibold">{{ statusChangeTarget?.user?.login || '—' }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Новый статус</span>
                            <span>{{ participantStatusLabels[participantStatusForm.status] || participantStatusForm.status }}</span>
                        </div>
                    </div>
                    <textarea
                        v-model="participantStatusForm.reason"
                        class="mt-4 min-h-[120px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        placeholder="Причина (необязательно)"
                    ></textarea>
                    <div v-if="participantStatusForm.errors.reason" class="mt-2 text-xs text-rose-700">
                        {{ participantStatusForm.errors.reason }}
                    </div>
                    <div v-if="participantStatusForm.errors.status" class="mt-2 text-xs text-rose-700">
                        {{ participantStatusForm.errors.status }}
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="participantStatusForm.processing"
                        @click="closeStatusChange"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="participantStatusForm.processing"
                    >
                        Подтвердить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div v-if="inviteOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: inviteForm.processing }" @submit.prevent="submitInvite">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">{{ inviteTitle }}</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeInvite"
                    >
                        x
                    </button>
                </div>
                        <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                            <div class="relative">
                                <label class="relative flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Логин пользователя
                                    <input
                                        v-model="inviteForm.login"
                                        class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        :class="[{ 'is-loading': userSuggestLoading }, inviteForm.login ? 'pr-20' : '']"
                                        type="text"
                                        placeholder="login"
                                        @input="scheduleUserSuggestions($event.target.value)"
                                    />
                                    <button
                                        v-if="inviteForm.login && !userSuggestLoading"
                                        class="absolute right-3 top-[2.05rem] text-slate-400 transition hover:text-slate-600"
                                        type="button"
                                        aria-label="Очистить пользователя"
                                        @click="clearInviteUserSelection"
                                    >
                                        <i class="pi pi-times"></i>
                                    </button>
                                </label>
                                <input v-model="inviteForm.user_id" type="hidden" />
                                <input v-model="inviteForm.role" type="hidden" />
                                <div v-if="inviteForm.errors.login" class="text-xs text-rose-700">
                                    {{ inviteForm.errors.login }}
                                </div>
                                <div v-if="inviteForm.errors.user_id" class="text-xs text-rose-700">
                                    {{ inviteForm.errors.user_id }}
                                </div>
                                <div v-if="inviteForm.errors.role" class="text-xs text-rose-700">
                                    {{ inviteForm.errors.role }}
                                </div>
                                <div v-if="userSuggestError" class="text-xs text-rose-700">
                                    {{ userSuggestError }}
                                </div>
                        <div
                            v-else-if="!userSuggestLoading && userSuggestions.length"
                            class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                        >
                            <button
                                v-for="(suggestion, index) in userSuggestions"
                                :key="`${suggestion.id}-${index}`"
                                class="flex w-full items-start gap-2 border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                type="button"
                                @click="applyUserSuggestion(suggestion)"
                            >
                                <span class="font-semibold">{{ suggestion.login }}</span>
                                <span v-if="suggestion.email" class="text-xs text-slate-500">{{ suggestion.email }}</span>
                            </button>
                            </div>
                            <label class="mt-4 flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Комментарий для приглашения
                                <textarea
                                    v-model="inviteForm.reason"
                                    class="min-h-[96px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    placeholder="Комментарий (необязательно)"
                                ></textarea>
                            </label>
                            <div v-if="inviteForm.errors.reason" class="text-xs text-rose-700">
                                {{ inviteForm.errors.reason }}
                            </div>
                        </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="inviteForm.processing"
                        @click="closeInvite"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="inviteForm.processing"
                    >
                        Пригласить
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
