<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import { useMessagePolling } from '../Composables/useMessagePolling';

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
    }
);
watch(
    () => props.messages,
    (value) => {
        messagesState.value = [...(value ?? [])];
    }
);

const pollParams = reactive({
    include_conversations: 1,
    conversation_id: props.activeConversation?.id ?? '',
});

const handlePoll = (data) => {
    if (Array.isArray(data?.conversations)) {
        conversationsState.value = data.conversations;
    }
    if (Array.isArray(data?.messages)) {
        messagesState.value = data.messages;
    }
    if (data?.active_conversation) {
        activeConversationState.value = data.active_conversation;
    }
    if (activeConversationState.value?.id) {
        markRead(activeConversationState.value.id);
    }
};

const { unreadCount, poll } = useMessagePolling({
    pollUrl: '/account/messages/poll',
    params: pollParams,
    onData: handlePoll,
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
    await fetch(`/account/messages/conversations/${conversationId}/read`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
};

const openConversation = async (conversation) => {
    activeConversationState.value = conversation;
    pollParams.conversation_id = conversation?.id ?? '';
    await markRead(conversation?.id);
    await poll({ conversation_id: conversation?.id });
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

const messageBody = ref('');
const messageError = ref('');
const sendMessage = async () => {
    const conversationId = activeConversationState.value?.id;
    if (!conversationId) {
        return;
    }
    messageError.value = '';
    try {
        const response = await fetch(`/account/messages/conversations/${conversationId}/messages`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body: messageBody.value }),
        });
        if (!response.ok) {
            const data = await response.json();
            messageError.value = data?.errors?.recipient_id?.[0]
                || data?.errors?.message?.[0]
                || data?.errors?.body?.[0]
                || 'Не удалось отправить сообщение.';
            return;
        }
        messageBody.value = '';
        await poll({ conversation_id: conversationId });
    } catch (error) {
        messageError.value = 'Не удалось отправить сообщение.';
    }
};

const deleteMessage = async (messageId) => {
    if (!messageId) {
        return;
    }
    await fetch(`/account/messages/messages/${messageId}/delete`, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
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
                                    v-for="conversation in conversationsState"
                                    :key="conversation.id"
                                    type="button"
                                    class="w-full rounded-2xl border px-4 py-3 text-left transition"
                                    :class="conversation.id === activeConversationState?.id
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-slate-50 text-slate-700 hover:border-slate-300'"
                                    @click="openConversation(conversation)"
                                >
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="font-semibold">{{ conversation.title }}</span>
                                        <span
                                            v-if="conversation.unread_count"
                                            class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                        >
                                            {{ conversation.unread_count > 9 ? '…' : conversation.unread_count }}
                                        </span>
                                    </div>
                                    <p v-if="conversation.last_message" class="mt-1 text-xs text-slate-500">
                                        {{ conversation.last_message.body }}
                                    </p>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div v-if="!activeConversationState" class="text-sm text-slate-500">
                                Выберите диалог слева, чтобы увидеть переписку.
                            </div>
                            <div v-else class="space-y-4">
                                <div class="border-b border-slate-100 pb-3">
                                    <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Диалог</p>
                                    <p class="mt-1 text-lg font-semibold text-slate-900">
                                        {{ activeConversationState.title }}
                                    </p>
                                </div>
                                <div class="max-h-[360px] space-y-3 overflow-y-auto pr-1">
                                    <div
                                        v-for="message in messagesState"
                                        :key="message.id"
                                        class="flex"
                                        :class="message.is_outgoing ? 'justify-end' : 'justify-start'"
                                    >
                                        <div
                                            class="max-w-[80%] rounded-2xl px-4 py-3 text-sm"
                                            :class="message.is_outgoing
                                                ? 'bg-slate-900 text-white'
                                                : 'bg-slate-100 text-slate-700'"
                                        >
                                            <p>{{ message.body }}</p>
                                            <div class="mt-2 flex items-center justify-between gap-3 text-[11px] text-slate-400" :class="message.is_outgoing ? 'text-slate-300' : ''">
                                                <span>{{ formatDate(message.created_at) }}</span>
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
                                </div>

                                <form class="mt-4 space-y-2" @submit.prevent="sendMessage">
                                    <textarea
                                        v-model="messageBody"
                                        class="min-h-[96px] w-full rounded-2xl border border-slate-200 px-3 py-2 text-sm text-slate-700"
                                        placeholder="Введите сообщение"
                                    ></textarea>
                                    <div v-if="messageError" class="text-xs text-rose-700">
                                        {{ messageError }}
                                    </div>
                                    <div class="flex justify-end">
                                        <button
                                            class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                            type="submit"
                                            :disabled="!messageBody"
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
