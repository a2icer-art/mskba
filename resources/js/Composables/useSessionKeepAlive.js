const MIN_PING_INTERVAL_MS = 30 * 1000;

const calculateInterval = (lifetimeMinutes) => {
    const lifetimeMs = Number(lifetimeMinutes || 0) * 60 * 1000;
    const target = Math.floor(lifetimeMs * 0.6);
    if (!Number.isFinite(target) || target <= 0) {
        return MIN_PING_INTERVAL_MS;
    }
    return Math.max(target, MIN_PING_INTERVAL_MS);
};

export const initSessionKeepAlive = () => {
    let timerId = null;
    let enabled = false;
    let intervalMs = MIN_PING_INTERVAL_MS;
    let lastPingAt = 0;

    const stop = () => {
        if (timerId) {
            clearInterval(timerId);
            timerId = null;
        }
    };

    const ping = () => {
        if (!enabled || document.hidden) {
            return;
        }

        const now = Date.now();
        if (now - lastPingAt < MIN_PING_INTERVAL_MS) {
            return;
        }
        lastPingAt = now;

        if (window.axios) {
            window.axios.get('/session/ping').catch(() => {});
        }
    };

    const start = () => {
        stop();
        if (!enabled) {
            return;
        }
        timerId = setInterval(ping, intervalMs);
        ping();
    };

    const update = ({ authenticated, lifetimeMinutes }) => {
        intervalMs = calculateInterval(lifetimeMinutes);
        enabled = Boolean(authenticated);
        start();
    };

    const handleVisibilityChange = () => {
        if (!enabled) {
            return;
        }
        if (document.hidden) {
            stop();
            return;
        }
        start();
    };

    document.addEventListener('visibilitychange', handleVisibilityChange);

    return {
        update,
    };
};
