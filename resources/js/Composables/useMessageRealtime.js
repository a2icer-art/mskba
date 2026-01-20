import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const sharedUnreadCount = ref(0);

export const useMessageRealtime = (options = {}) => {
    const page = usePage();
    const unreadCount = sharedUnreadCount;
    const enabled = options.enabled ?? true;
    const userId = options.userId ?? page.props?.auth?.user?.id ?? null;
    const onEvent = options.onEvent;
    let subscribed = false;

    const syncFromPage = () => {
        const value = page.props?.messageCounters?.unread_messages;
        if (Number.isFinite(Number(value))) {
            unreadCount.value = Number(value);
        }
    };

    const handleEvent = (payload) => {
        if (Number.isFinite(Number(payload?.unread_messages))) {
            unreadCount.value = Number(payload.unread_messages);
        }
        if (onEvent) {
            onEvent(payload);
        }
    };

    const subscribe = () => {
        if (!enabled || !userId || !window.Echo || subscribed) {
            return;
        }
        subscribed = true;
        const channelName = `user.${userId}`;
        window.Echo.private(channelName)
            .listen('.messages.created', handleEvent)
            .listen('.messages.read', handleEvent);
    };

    const unsubscribe = () => {
        if (!window.Echo || !subscribed || !userId) {
            return;
        }
        window.Echo.leave(`user.${userId}`);
        subscribed = false;
    };

    onMounted(() => {
        syncFromPage();
        subscribe();
    });

    onUnmounted(() => {
        unsubscribe();
    });

    return { unreadCount, syncFromPage };
};
