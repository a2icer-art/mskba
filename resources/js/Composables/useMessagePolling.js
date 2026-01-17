import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const unreadCount = ref(0);

export const useMessagePolling = (options = {}) => {
    const page = usePage();
    const intervalMs = options.intervalMs ?? 5000;
    const pollUrl = options.pollUrl ?? '/account/messages/poll';
    const params = options.params ?? {};
    const enabled = options.enabled ?? true;
    const onData = options.onData;
    let timer = null;

    const syncFromPage = () => {
        const value = page.props?.messageCounters?.unread_messages;
        if (Number.isFinite(Number(value))) {
            unreadCount.value = Number(value);
        }
    };

    const poll = async (extraParams = {}) => {
        if (!enabled) {
            return null;
        }
        const query = new URLSearchParams({ ...params, ...extraParams }).toString();
        const url = query ? `${pollUrl}?${query}` : pollUrl;
        try {
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) {
                return null;
            }
            const data = await response.json();
            if (Number.isFinite(Number(data?.unread_messages))) {
                unreadCount.value = Number(data.unread_messages);
            }
            if (onData) {
                onData(data);
            }
            return data;
        } catch (error) {
            return null;
        }
    };

    onMounted(() => {
        syncFromPage();
        if (!enabled) {
            return;
        }
        poll();
        timer = setInterval(poll, intervalMs);
    });

    onUnmounted(() => {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    });

    return { unreadCount, poll };
};
