<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue';
import { Link } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import { useMessagePolling } from '../Composables/useMessagePolling';
import { useMessageRealtime } from '../Composables/useMessageRealtime';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    user: {
        type: Object,
        default: null,
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    activeConversation: {
        type: Object,
        default: null,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    messagesMeta: {
        type: Object,
        default: () => ({ has_more: false, oldest_id: null }),
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Аккаунт', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const conversationsState = ref([...props.conversations]);
const activeConversationState = ref(props.activeConversation);
const messagesState = ref([...props.messages]);
const messagesContainer = ref(null);
const selectedConversationId = ref(props.activeConversation?.id ?? null);
const messagesMetaState = ref({ ...props.messagesMeta });

const parseTimestamp = (value) => {
    if (!value) {
        return 0;
    }
    const normalized = value.includes('T') ? value : value.replace(' ', 'T');
    const ts = Date.parse(normalized);
    return Number.isNaN(ts) ? 0 : ts;
};

const sortedConversations = computed(() => {
    return [...conversationsState.value].sort((a, b) => {
        const leftDate = parseTimestamp(a.last_message?.created_at || a.updated_at);
        const rightDate = parseTimestamp(b.last_message?.created_at || b.updated_at);
        if (leftDate !== rightDate) {
            return rightDate - leftDate;
        }
        return (b.id ?? 0) - (a.id ?? 0);
    });
});

onMounted(() => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
});

watch(
    () => props.conversations,
    (value) => {
        conversationsState.value = [...(value ?? [])];
    }
);
watch(
    () => props.activeConversation,
    (value) => {
        activeConversationState.value = value;
        selectedConversationId.value = value?.id ?? null;
    }
);
watch(
    () => activeConversationState.value?.id,
    () => {
        selectedContact.value = null;
        directMessageBody.value = '';
        directMessageError.value = '';
    }
);
watch(
    () => props.messages,
    (value) => {
        messagesState.value = [...(value ?? [])];
    }
);
watch(
    () => props.messagesMeta,
    (value) => {
        messagesMetaState.value = { ...(value ?? {}) };
    }
);

const pollParams = reactive({
    include_conversations: 1,
    conversation_id: props.activeConversation?.id ?? '',
    messages_after_id: props.messages?.length ? props.messages[props.messages.length - 1]?.id : '',
});
const isLoading = ref(false);
const isMarkingRead = ref(false);

const mergeMessages = (current, incoming, mode) => {
    const seen = new Set();
    const result = [];
    const pushUnique = (message) => {
        if (!message || seen.has(message.id)) {
            return;
        }
        seen.add(message.id);
        result.push(message);
    };

    if (mode === 'prepend') {
        incoming.forEach(pushUnique);
        current.forEach(pushUnique);
        return result;
    }
    if (mode === 'append') {
        current.forEach(pushUnique);
        incoming.forEach(pushUnique);
        return result;
    }
    incoming.forEach(pushUnique);
    return result;
};

const handlePoll = (data) => {
    if (Array.isArray(data?.conversations)) {
        conversationsState.value = data.conversations;
    }
    if (Array.isArray(data?.messages)) {
        const shouldStickToBottom = messagesContainer.value
            ? (messagesContainer.value.scrollHeight - messagesContainer.value.scrollTop - messagesContainer.value.clientHeight) < 40
            : true;
        if (data?.messages_mode === 'prepend') {
            messagesState.value = mergeMessages(messagesState.value, data.messages, 'prepend');
        } else if (data?.messages_mode === 'append') {
            messagesState.value = mergeMessages(messagesState.value, data.messages, 'append');
        } else {
            messagesState.value = mergeMessages([], data.messages, 'replace');
        }
        const lastMessage = messagesState.value[messagesState.value.length - 1];
        pollParams.messages_after_id = lastMessage?.id ?? '';
        if (data?.messages_mode === 'append' && shouldStickToBottom) {
            nextTick(() => {
                if (messagesContainer.value) {
                    messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
                }
            });
        }
    }
    if (Array.isArray(data?.messages_refresh) && data.messages_refresh.length) {
        const refreshMap = new Map(data.messages_refresh.map((item) => [item.id, item]));
        messagesState.value = messagesState.value.map((message) => refreshMap.get(message.id) ?? message);
    }
    if (data?.active_conversation) {
        activeConversationState.value = data.active_conversation;
    }
    if (Array.isArray(data?.messages)) {
        isLoading.value = false;
    }
    if (data?.messages_meta) {
        messagesMetaState.value = data.messages_meta;
    }
    if (activeConversationState.value?.id) {
        markRead(activeConversationState.value.id);
    }
    if (Array.isArray(data?.messages) && data?.messages_mode !== 'prepend' && data?.messages_mode !== 'append') {
        nextTick(() => {
            if (messagesContainer.value) {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
        });
    }
};

const upsertConversation = (incoming) => {
    if (!incoming?.id) {
        return;
    }
    const list = conversationsState.value;
    const index = list.findIndex((item) => item.id === incoming.id);
    if (index >= 0) {
        conversationsState.value = list.map((item) => (
            item.id === incoming.id ? { ...item, ...incoming } : item
        ));
        return;
    }
    conversationsState.value = [incoming, ...list];
};

const handleRealtimeEvent = (payload) => {
    if (!payload?.event) {
        return;
    }
    if (payload.event === 'messages.created') {
        if (payload.conversation) {
            upsertConversation(payload.conversation);
        }
        const activeId = activeConversationState.value?.id;
        if (payload.conversation?.id && payload.conversation.id === activeId && payload.message) {
            const shouldStickToBottom = messagesContainer.value
                ? (messagesContainer.value.scrollHeight - messagesContainer.value.scrollTop - messagesContainer.value.clientHeight) < 40
                : true;
            messagesState.value = mergeMessages(messagesState.value, [payload.message], 'append');
            pollParams.messages_after_id = payload.message?.id ?? pollParams.messages_after_id;
            if (shouldStickToBottom) {
                nextTick(() => {
                    if (messagesContainer.value) {
                        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
                    }
                });
            }
            if (!payload.message.is_outgoing) {
                markRead(activeId);
            }
        }
        return;
    }
    if (payload.event === 'messages.read') {
        if (payload.conversation) {
            upsertConversation(payload.conversation);
        }
        const activeId = activeConversationState.value?.id;
        if (payload.conversation_id && payload.conversation_id === activeId && payload.reader_id !== props.user?.id) {
            messagesState.value = messagesState.value.map((message) => {
                if (!message.is_outgoing || message.read_by_others_at) {
                    return message;
                }
                return { ...message, read_by_others_at: payload.read_at };
            });
        }
    }
};

const { unreadCount } = useMessageRealtime({
    enabled: Boolean(props.user?.id),
    onEvent: handleRealtimeEvent,
});

const { poll, pause, resume } = useMessagePolling({
    pollUrl: '/account/messages/poll',
    params: pollParams,
    onData: handlePoll,
    autoStart: false,
    intervalMs: 0,
});

const applyBadge = (item) => (item.key === 'messages'
    ? {
        ...item,
        badge: unreadCount.value,
    }
    : item);

const sidebarData = computed(() =>
    navigationData.value.map((item) => {
        if (Array.isArray(item?.items)) {
            return {
                ...item,
                items: item.items.map(applyBadge),
            };
        }
        return applyBadge(item);
    })
);

const markRead = async (conversationId) => {
    if (!conversationId) {
        return;
    }
    if (isMarkingRead.value) {
        return;
    }
    isMarkingRead.value = true;
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        await fetch(`/account/messages/conversations/${conversationId}/read`, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
        });
        conversationsState.value = conversationsState.value.map((conversation) => (
            conversation.id === conversationId
                ? { ...conversation, unread_count: 0 }
                : conversation
        ));
    } finally {
        isMarkingRead.value = false;
    }
};

const openConversation = async (conversation) => {
    isLoading.value = true;
    messagesState.value = [];
    messagesMetaState.value = { has_more: false, oldest_id: null };
    selectedConversationId.value = conversation?.id ?? null;
    activeConversationState.value = conversation;
    pollParams.conversation_id = conversation?.id ?? '';
    pollParams.messages_after_id = '';
    await markRead(conversation?.id);
    pause();
    try {
        await poll({ conversation_id: conversation?.id, include_conversations: 0 }, { force: true });
    } finally {
        resume();
        isLoading.value = false;
        nextTick(() => {
            if (messagesContainer.value) {
                messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
            }
        });
    }
};

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

const contactOptions = computed(() => {
    const contacts = (activeConversationState.value?.contacts || []).filter((item) => item.id !== props.user?.id);
    if (!contacts.length) {
        return [];
    }
    const primary = messagesState.value?.[messagesState.value.length - 1]?.contact_user;
    if (!primary?.id) {
        return contacts;
    }
    const ordered = [
        ...contacts.filter((item) => item.id === primary.id),
        ...contacts.filter((item) => item.id !== primary.id),
    ];
    const unique = [];
    const seen = new Set();
    for (const item of ordered) {
        if (!seen.has(item.id)) {
            seen.add(item.id);
            unique.push(item);
        }
    }
    return unique;
});

const isLoadingOlder = ref(false);
const loadOlderMessages = async () => {
    if (isLoadingOlder.value || isLoading.value) {
        return;
    }
    const oldestId = messagesMetaState.value?.oldest_id;
    if (!oldestId) {
        return;
    }
    isLoadingOlder.value = true;
    const previousHeight = messagesContainer.value?.scrollHeight ?? 0;
    pause();
    try {
        await poll(
            {
                conversation_id: activeConversationState.value?.id ?? '',
                include_conversations: 0,
                messages_before_id: oldestId,
                messages_limit: 10,
            },
            { force: true }
        );
    } finally {
        resume();
        nextTick(() => {
            if (messagesContainer.value) {
                const newHeight = messagesContainer.value.scrollHeight;
                messagesContainer.value.scrollTop = newHeight - previousHeight;
            }
            isLoadingOlder.value = false;
        });
    }
};

const handleMessagesScroll = () => {
    if (!messagesContainer.value) {
        return;
    }
    if (messagesContainer.value.scrollTop <= 0 && messagesMetaState.value?.has_more) {
        loadOlderMessages();
    }
};

const selectedContact = ref(null);
const directMessageBody = ref('');
const directMessageError = ref('');
const isSendingDirect = ref(false);

const selectContact = (contact) => {
    selectedContact.value = contact;
    directMessageBody.value = '';
    directMessageError.value = '';
};

const sendDirectMessage = async () => {
    const contact = selectedContact.value;
    if (!contact?.id) {
        return;
    }
    if (!directMessageBody.value) {
        return;
    }
    if (isSendingDirect.value) {
        return;
    }

    directMessageError.value = '';
    isSendingDirect.value = true;
    const draft = directMessageBody.value;
    directMessageBody.value = '';
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    try {
        const response = await fetch('/account/messages/direct', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({ user_id: contact.id, body: draft }),
        });
        if (!response.ok) {
            const data = await response.json();
            directMessageError.value = data?.errors?.body?.[0]
                || data?.errors?.recipient_id?.[0]
                || data?.errors?.user_id?.[0]
                || data?.message
                || 'Не удалось отправить сообщение.';
            return;
        }
        const data = await response.json();
        const newId = data?.conversation_id;
        selectedContact.value = null;
        await poll({ include_conversations: 1 });
        if (newId) {
            pollParams.conversation_id = newId;
            await poll({ conversation_id: newId, include_conversations: 1 });
            const target = conversationsState.value.find((item) => item.id === newId);
            if (target) {
                activeConversationState.value = target;
            }
        }
    } catch (error) {
        directMessageError.value = 'Не удалось отправить сообщение.';
        directMessageBody.value = draft;
    } finally {
        isSendingDirect.value = false;
    }
};

const handleDirectKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        sendDirectMessage();
    }
};

const messageBody = ref('');
const messageError = ref('');
const isSendingMessage = ref(false);
const sendMessage = async () => {
    const conversationId = activeConversationState.value?.id;
    if (!conversationId) {
        return;
    }
    if (isSendingMessage.value) {
        return;
    }
    messageError.value = '';
    isSendingMessage.value = true;
    const draft = messageBody.value;
    messageBody.value = '';
    try {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const response = await fetch(`/account/messages/conversations/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(token ? { 'X-CSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({ body: draft }),
        });
        if (!response.ok) {
            const data = await response.json();
            messageError.value = data?.errors?.recipient_id?.[0]
                || data?.errors?.message?.[0]
                || data?.errors?.body?.[0]
                || 'Не удалось отправить сообщение.';
            return;
        }
    } catch (error) {
        messageError.value = 'Не удалось отправить сообщение.';
        messageBody.value = draft;
    } finally {
        isSendingMessage.value = false;
    }
};

const handleMessageKeydown = (event) => {
    if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
        event.preventDefault();
        sendMessage();
    }
};

const deleteMessage = async (messageId) => {
    if (!messageId) {
        return;
    }
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    await fetch(`/account/messages/messages/${messageId}/delete`, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            ...(token ? { 'X-CSRF-TOKEN': token } : {}),
        },
    });
    await poll({ conversation_id: activeConversationState.value?.id });
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
                :sidebar-title="navigation.title"
                :sidebar-items="sidebarData"
                :sidebar-active-href="activeHref"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="sidebarData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <h1 class="text-3xl font-semibold text-slate-900">Сообщения</h1>

                    <div class="mt-6 grid gap-4 lg:grid-cols-[280px_1fr]">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Диалоги</p>
                            <div v-if="!conversationsState.length" class="mt-4 text-sm text-slate-500">
                                Диалогов пока нет.
                            </div>
                            <div v-else class="mt-4 space-y-3">
                                <button
                                    v-for="conversation in sortedConversations"
                                    :key="conversation.id"
                                    type="button"
                                    class="w-full rounded-2xl border px-4 py-3 text-left transition"
                                    :class="conversation.id === selectedConversationId
                                        ? 'border-[#444b5b] bg-[#444b5b] text-[#99aecc]'
                                        : conversation.type === 'system'
                                            ? 'border-sky-200 bg-white text-slate-700 hover:border-sky-300'
                                            : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300'"
                                    @click="openConversation(conversation)"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span :class="conversation.id === selectedConversationId ? 'font-semibold text-white' : 'font-semibold'">
                                            {{ conversation.title }}
                                        </span>
                                        <span
                                            v-if="conversation.unread_count"
                                            class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                        >
                                            {{ conversation.unread_count > 9 ? '…' : conversation.unread_count }}
                                        </span>
                                    </div>
                                    <p
                                        v-if="conversation.last_message"
                                        class="mt-1 text-xs"
                                        :class="conversation.id === selectedConversationId ? 'text-[#99aecc]' : 'text-slate-500'"
                                    >
                                        {{ conversation.last_message.body }}
                                    </p>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div v-if="!activeConversationState" class="text-sm text-slate-500">
                                Выберите диалог слева, чтобы увидеть переписку.
                            </div>
                            <div v-else class="flex h-[600px] flex-col gap-4 overflow-hidden">
                                <div class="border-b border-slate-100 pb-3">
                                    <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Диалог</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">
                                        {{ activeConversationState.title }}
                                    </p>
                                </div>
                                <div
                                    ref="messagesContainer"
                                    class="flex-1 space-y-3 overflow-y-auto pr-1"
                                    @scroll="handleMessagesScroll"
                                >
                                    <template v-if="isLoading">
                                        <div class="flex min-h-[180px] items-center justify-center">
                                            <div class="h-6 w-6 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div v-if="isLoadingOlder" class="flex justify-center py-2">
                                            <div class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-slate-600"></div>
                                        </div>
                                        <p
                                            v-else-if="messagesState.length && !messagesMetaState?.has_more"
                                            class="py-2 text-center text-xs text-slate-400"
                                        >
                                            Начало диалога
                                        </p>
                                        <div
                                            v-for="message in messagesState"
                                            :key="message.id"
                                            class="flex"
                                            :class="message.is_outgoing ? 'justify-end' : 'justify-start'"
                                        >
                                            <div
                                                class="max-w-[80%] rounded-2xl px-4 py-3 text-sm"
                                                :class="message.is_outgoing
                                                    ? 'bg-[#444b5b] text-white'
                                                    : 'bg-slate-100 text-slate-700'"
                                            >
                                                <p v-if="message.title" class="font-semibold">
                                                    {{ message.title }}
                                                </p>
                                                <p v-if="message.body" :class="message.title ? 'mt-2' : ''">
                                                    {{ message.body }}
                                                </p>
                                                <Link
                                                    v-if="message.link_url"
                                                    class="mt-2 inline-flex text-xs font-semibold"
                                                    :class="message.is_outgoing ? 'text-amber-200 hover:text-amber-100' : 'text-slate-700 hover:text-slate-900'"
                                                    :href="message.link_url"
                                                >
                                                    Открыть
                                                </Link>
                                                <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-400" :class="message.is_outgoing ? 'text-slate-300' : ''">
                                                    <div class="flex items-center gap-2">
                                                        <span
                                                            v-if="message.is_outgoing && message.read_by_others_at"
                                                            class="text-[11px] font-semibold text-emerald-200"
                                                            :title="`Прочитано ${formatDate(message.read_by_others_at)}`"
                                                        >
                                                            ✓
                                                        </span>
                                                        <span>{{ formatDate(message.created_at) }}</span>
                                                    </div>
                                                    <button
                                                        v-if="message.is_outgoing"
                                                        type="button"
                                                        class="text-[11px] font-semibold text-rose-200 hover:text-rose-100"
                                                        @click="deleteMessage(message.id)"
                                                    >
                                                        Удалить
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </template>
                                </div>

                                <div v-if="activeConversationState?.type === 'system'" class="mt-auto space-y-3 rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3">
                                    <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Контакты для ответа</p>
                                    <div class="flex flex-wrap gap-2">
                                        <button
                                            v-for="contact in contactOptions"
                                            :key="contact.id"
                                            type="button"
                                            class="rounded-2xl border border-slate-200 bg-white px-3 py-2 text-left text-xs font-semibold text-slate-700 hover:border-slate-300 hover:text-slate-900"
                                            @click="selectContact(contact)"
                                        >
                                            <span class="block text-sm">{{ contact.login }}</span>
                                            <span v-if="contact.role" class="mt-1 block text-[10px] uppercase tracking-[0.12em] text-slate-500">
                                                {{ contact.role }}
                                            </span>
                                        </button>
                                    </div>
                                    <p v-if="!contactOptions.length" class="text-xs text-slate-500">
                                        Нет доступных контактов для ответа.
                                    </p>
                                    <div v-if="selectedContact" class="rounded-2xl border border-slate-200 bg-white px-3 py-3">
                                        <p class="text-xs font-semibold text-slate-500">
                                            Сообщение для {{ selectedContact.login }}
                                        </p>
                                        <textarea
                                            v-model="directMessageBody"
                                            class="mt-2 min-h-[96px] w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                            placeholder="Введите сообщение (Ctrl+Enter)"
                                            @keydown="handleDirectKeydown"
                                        ></textarea>
                                        <div v-if="directMessageError" class="mt-2 text-xs text-rose-700">
                                            {{ directMessageError }}
                                        </div>
                                        <div class="mt-3 flex justify-end">
                                        <button
                                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                            type="button"
                                            :disabled="!directMessageBody || isSendingDirect"
                                            @click="sendDirectMessage"
                                        >
                                            Отправить
                                        </button>
                                        </div>
                                    </div>
                                </div>
                                <form v-else class="mt-auto space-y-2" @submit.prevent="sendMessage">
                                    <textarea
                                        v-model="messageBody"
                                        class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Введите сообщение (Ctrl+Enter)"
                                        @keydown="handleMessageKeydown"
                                    ></textarea>
                                    <div v-if="messageError" class="text-xs text-rose-700">
                                        {{ messageError }}
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                            type="submit"
                                            :disabled="!messageBody || isSendingMessage"
                                        >
                                            Отправить
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
