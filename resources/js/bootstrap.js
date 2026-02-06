import axios from 'axios';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const token = document.head.querySelector('meta[name="csrf-token"]');

const applyCsrfToken = (value) => {
    if (!value) {
        return;
    }
    if (token) {
        token.setAttribute('content', value);
    }
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = value;
};

const csrfToken = token?.content || '';
applyCsrfToken(csrfToken);

let csrfRefreshPromise = null;
const refreshCsrfToken = async () => {
    if (csrfRefreshPromise) {
        return csrfRefreshPromise;
    }
    csrfRefreshPromise = fetch('/csrf-token', {
        method: 'GET',
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
    })
        .then((response) => {
            if (!response.ok) {
                throw new Error('csrf_refresh_failed');
            }
            return response.json();
        })
        .then((data) => {
            const tokenValue = data?.csrfToken || data?.token || '';
            applyCsrfToken(tokenValue);
            return tokenValue;
        })
        .finally(() => {
            csrfRefreshPromise = null;
        });
    return csrfRefreshPromise;
};

let csrfRetryActiveCount = 0;
const setCsrfRetryActive = (active) => {
    if (active) {
        csrfRetryActiveCount += 1;
        if (csrfRetryActiveCount === 1) {
            document.documentElement.classList.add('csrf-retry');
        }
        return;
    }

    csrfRetryActiveCount = Math.max(0, csrfRetryActiveCount - 1);
    if (csrfRetryActiveCount === 0) {
        document.documentElement.classList.remove('csrf-retry');
    }
};

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const status = error?.response?.status;
        const config = error?.config;

        if (!config || status !== 419 || config.__skipCsrfRefresh) {
            return Promise.reject(error);
        }

        if (config.__csrfRetried) {
            window.location.reload();
            return Promise.reject(error);
        }

        setCsrfRetryActive(true);
        try {
            const tokenValue = await refreshCsrfToken();
            config.__csrfRetried = true;
            if (config.headers && tokenValue) {
                config.headers['X-CSRF-TOKEN'] = tokenValue;
            }
            return window.axios(config);
        } catch (retryError) {
            window.location.reload();
            return Promise.reject(retryError);
        } finally {
            setCsrfRetryActive(false);
        }
    }
);

window.Pusher = Pusher;

const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    const host = import.meta.env.VITE_REVERB_HOST || window.location.hostname;
    const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        auth: {
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
            },
        },
        withCredentials: true,
    });
}
