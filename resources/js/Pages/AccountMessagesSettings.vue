<script setup>
import { computed, ref, watch } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
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
    privacySetting: {
        type: Object,
        default: () => ({}),
    },
    privacyOptions: {
        type: Array,
        default: () => [],
    },
    allowList: {
        type: Array,
        default: () => [],
    },
    blockList: {
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

const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);

const allowListState = ref([...props.allowList]);
const blockListState = ref([...props.blockList]);

watch(
    () => props.allowList,
    (value) => {
        allowListState.value = [...(value ?? [])];
    }
);
watch(
    () => props.blockList,
    (value) => {
        blockListState.value = [...(value ?? [])];
    }
);

const { unreadCount } = useMessagePolling({
    pollUrl: '/account/messages/poll',
    params: { scope: 'counter' },
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

const settingsForm = useForm({
    mode: props.privacySetting?.mode ?? 'all',
});
const updateSettings = () => {
    settingsForm.patch('/account/settings/messages', {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['privacySetting'] }),
    });
};

const allowForm = useForm({
    user_id: '',
});
const blockForm = useForm({
    user_id: '',
});

const userSuggestLoading = ref(false);
const userSuggestError = ref('');
const userSuggestions = ref([]);
const allowQuery = ref('');
const blockQuery = ref('');
const activeSuggestTarget = ref('allow');
let userSuggestTimer = null;
let userSuggestRequestId = 0;

const scheduleUserSuggestions = (value, target) => {
    activeSuggestTarget.value = target;
    if (userSuggestTimer) {
        clearTimeout(userSuggestTimer);
    }
    userSuggestions.value = [];
    userSuggestError.value = '';
    if (!value || value.trim().length < 2) {
        userSuggestLoading.value = false;
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
        const response = await fetch(`/integrations/user-suggest?query=${encodeURIComponent(query)}`);
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

const applySuggestion = (suggestion) => {
    if (activeSuggestTarget.value === 'block') {
        blockForm.user_id = suggestion.id;
        blockQuery.value = suggestion.login || '';
    } else {
        allowForm.user_id = suggestion.id;
        allowQuery.value = suggestion.login || '';
    }
    userSuggestions.value = [];
    userSuggestError.value = '';
};

const addAllowed = () => {
    allowForm.post('/account/settings/messages/allow-list', {
        preserveScroll: true,
        onSuccess: () => {
            allowForm.reset('user_id');
            allowQuery.value = '';
            router.reload({ only: ['allowList', 'blockList'] });
        },
    });
};

const addBlocked = () => {
    blockForm.post('/account/settings/messages/block-list', {
        preserveScroll: true,
        onSuccess: () => {
            blockForm.reset('user_id');
            blockQuery.value = '';
            router.reload({ only: ['allowList', 'blockList'] });
        },
    });
};

const removeAllowed = (userId) => {
    if (!userId) {
        return;
    }
    allowForm.delete(`/account/settings/messages/allow-list/${userId}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['allowList', 'blockList'] }),
    });
};

const removeBlocked = (userId) => {
    if (!userId) {
        return;
    }
    blockForm.delete(`/account/settings/messages/block-list/${userId}`, {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['allowList', 'blockList'] }),
    });
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
                    <h1 class="text-3xl font-semibold text-slate-900">Настройки сообщений</h1>

                    <div class="mt-6 grid gap-6 lg:grid-cols-2">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Приватность</p>
                            <form class="mt-4 space-y-3" @submit.prevent="updateSettings">
                                <select
                                    v-model="settingsForm.mode"
                                    class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                                >
                                    <option v-for="option in privacyOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </option>
                                </select>
                                <p class="text-xs text-slate-500">
                                    Группы (коллеги/друзья) будут доступны позже.
                                </p>
                                <div v-if="settingsForm.errors.mode" class="text-xs text-rose-700">
                                    {{ settingsForm.errors.mode }}
                                </div>
                                <button
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    type="submit"
                                    :disabled="settingsForm.processing"
                                >
                                    Сохранить
                                </button>
                            </form>
                        </div>

                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Белый список</p>
                            <div class="mt-4 space-y-2 text-sm text-slate-700">
                                <div v-if="!allowListState.length" class="text-sm text-slate-500">Список пуст.</div>
                                <div v-else class="space-y-2">
                                    <div
                                        v-for="item in allowListState"
                                        :key="item.id"
                                        class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                                    >
                                        <span>{{ item.user?.login || '—' }}</span>
                                        <button
                                            class="text-xs font-semibold text-rose-600"
                                            type="button"
                                            @click="removeAllowed(item.user?.id)"
                                        >
                                            Удалить
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="relative mt-4">
                                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    Добавить пользователя
                                    <input
                                        v-model="allowQuery"
                                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                        :class="{ 'is-loading': userSuggestLoading }"
                                        type="text"
                                        placeholder="Начните вводить логин"
                                        @input="scheduleUserSuggestions($event.target.value, 'allow')"
                                    />
                                </label>
                                <input v-model="allowForm.user_id" type="hidden" />
                                <div v-if="userSuggestError" class="text-xs text-rose-700">
                                    {{ userSuggestError }}
                                </div>
                                <div
                                    v-else-if="!userSuggestLoading && userSuggestions.length && activeSuggestTarget === 'allow'"
                                    class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                                >
                                    <button
                                        v-for="(suggestion, index) in userSuggestions"
                                        :key="`${suggestion.id}-${index}`"
                                        class="flex w-full items-start gap-2 border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                        type="button"
                                        @click="applySuggestion(suggestion)"
                                    >
                                        <span class="font-semibold">{{ suggestion.login }}</span>
                                        <span v-if="suggestion.email" class="text-xs text-slate-500">{{ suggestion.email }}</span>
                                    </button>
                                </div>
                            </div>
                            <div v-if="allowForm.errors.user_id" class="mt-2 text-xs text-rose-700">
                                {{ allowForm.errors.user_id }}
                            </div>
                            <button
                                class="mt-3 rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                type="button"
                                :disabled="allowForm.processing || !allowForm.user_id"
                                @click="addAllowed"
                            >
                                Добавить
                            </button>
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Черный список</p>
                        <div class="mt-4 space-y-2 text-sm text-slate-700">
                            <div v-if="!blockListState.length" class="text-sm text-slate-500">Список пуст.</div>
                            <div v-else class="space-y-2">
                                <div
                                    v-for="item in blockListState"
                                    :key="item.id"
                                    class="flex items-center justify-between rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2"
                                >
                                    <span>{{ item.user?.login || '—' }}</span>
                                    <button
                                        class="text-xs font-semibold text-rose-600"
                                        type="button"
                                        @click="removeBlocked(item.user?.id)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="relative mt-4">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Добавить пользователя
                                <input
                                    v-model="blockQuery"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    :class="{ 'is-loading': userSuggestLoading }"
                                    type="text"
                                    placeholder="Начните вводить логин"
                                    @input="scheduleUserSuggestions($event.target.value, 'block')"
                                />
                            </label>
                            <input v-model="blockForm.user_id" type="hidden" />
                            <div
                                v-if="!userSuggestLoading && userSuggestions.length && activeSuggestTarget === 'block'"
                                class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                            >
                                <button
                                    v-for="(suggestion, index) in userSuggestions"
                                    :key="`${suggestion.id}-${index}`"
                                    class="flex w-full items-start gap-2 border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                    type="button"
                                    @click="applySuggestion(suggestion)"
                                >
                                    <span class="font-semibold">{{ suggestion.login }}</span>
                                    <span v-if="suggestion.email" class="text-xs text-slate-500">{{ suggestion.email }}</span>
                                </button>
                            </div>
                        </div>
                        <div v-if="blockForm.errors.user_id" class="mt-2 text-xs text-rose-700">
                            {{ blockForm.errors.user_id }}
                        </div>
                        <button
                            class="mt-3 rounded-full border border-rose-600 bg-rose-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-rose-700"
                            type="button"
                            :disabled="blockForm.processing || !blockForm.user_id"
                            @click="addBlocked"
                        >
                            Добавить
                        </button>
                    </div>

                    <div v-if="actionNotice" class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                        {{ actionNotice }}
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
