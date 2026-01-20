import { onMounted, onUnmounted, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

const unreadCount = ref(0);

export const useMessagePolling = (options = {}) => {
    const page = usePage();
    const intervalMs = options.intervalMs ?? 5000;
    const autoStart = options.autoStart ?? true;
    const hasTimer = Number.isFinite(Number(intervalMs)) && Number(intervalMs) > 0;
    const pollUrl = options.pollUrl ?? '/account/messages/poll';
    const params = options.params ?? {};
    const enabled = options.enabled ?? true;
    const isEnabled = ref(Boolean(enabled));
    const onData = options.onData;
    let timer = null;

    const syncFromPage = () => {
        const value = page.props?.messageCounters?.unread_messages;
        if (Number.isFinite(Number(value))) {
            unreadCount.value = Number(value);
        }
    };

    const poll = async (extraParams = {}, options = {}) => {
        if (!isEnabled.value && !options.force) {
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

    const startTimer = () => {
        if (timer) {
            return;
        }
        if (!hasTimer) {
            return;
        }
        timer = setInterval(poll, intervalMs);
    };

    const stopTimer = () => {
        if (!timer) {
            return;
        }
        clearInterval(timer);
        timer = null;
    };

    const pause = () => {
        isEnabled.value = false;
        stopTimer();
    };

    const resume = (options = {}) => {
        isEnabled.value = true;
        if (options.pollNow) {
            poll();
        }
        startTimer();
    };

    onMounted(() => {
        syncFromPage();
        if (!isEnabled.value || !autoStart) {
            return;
        }
        resume({ pollNow: true });
    });

    onUnmounted(() => {
        stopTimer();
    });

    return { unreadCount, poll, pause, resume };
};
