<script setup>
import { computed } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import SystemNoticeStack from '../Components/SystemNoticeStack.vue';
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
    definitions: {
        type: Array,
        default: () => [],
    },
    settings: {
        type: Object,
        default: () => ({}),
    },
    channels: {
        type: Array,
        default: () => [],
    },
    contacts: {
        type: Object,
        default: () => ({}),
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

const { unreadCount } = useMessageRealtime({});
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

const form = useForm({
    notifications: JSON.parse(JSON.stringify(props.settings ?? {})),
});

const contactMap = computed(() => props.contacts ?? {});
const channelOptions = computed(() => props.channels ?? []);

const resolveNotification = (code) => {
    if (!form.notifications[code]) {
        form.notifications[code] = { enabled: false, channels: {} };
    }
    if (!form.notifications[code].channels) {
        form.notifications[code].channels = {};
    }
    return form.notifications[code];
};

const contactList = (type) => contactMap.value?.[type] || [];
const hasContacts = (type) => contactList(type).length > 0;
const contactValue = (contact) => contact?.value || '—';
const contactPending = (contact) => Boolean(contact?.value && !contact?.confirmed);
const isContactAvailable = (contact) => Boolean(contact?.value && contact?.confirmed);

const submit = () => {
    form.patch('/account/settings/notifications', {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" />

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
                    <h1 class="text-3xl font-semibold text-slate-900">Уведомления</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Настройте, какие системные уведомления получать внутри проекта и во внешних каналах.
                    </p>

                    <form class="mt-6 space-y-4" @submit.prevent="submit">
                        <div
                            v-for="definition in definitions"
                            :key="definition.code"
                            class="rounded-2xl border border-slate-200 bg-white px-4 py-5"
                        >
                            <label class="flex items-center gap-3 text-sm font-semibold text-slate-800">
                                <input
                                    v-model="resolveNotification(definition.code).enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                />
                                {{ definition.label }}
                            </label>

                            <div class="mt-4">
                                <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Также отправлять на:
                                </p>
                                <div class="mt-3 grid gap-3 md:grid-cols-2">
                                    <div
                                        v-for="channel in channelOptions"
                                        :key="`${definition.code}-${channel.type}`"
                                        class="space-y-2"
                                    >
                                        <p class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                            {{ channel.label }}
                                        </p>
                                        <label
                                            v-if="!hasContacts(channel.type)"
                                            class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700"
                                        >
                                            <span class="text-xs text-slate-500">—</span>
                                            <input
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                                disabled
                                            />
                                        </label>
                                        <label
                                            v-for="contact in contactList(channel.type)"
                                            v-else
                                            :key="`${definition.code}-${channel.type}-${contact.id}`"
                                            class="flex items-center justify-between gap-3 rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-700"
                                        >
                                            <div class="grid gap-1">
                                                <span class="font-medium text-slate-900">
                                                    {{ contactValue(contact) }}
                                                </span>
                                                <span v-if="contactPending(contact)" class="text-xs text-amber-600">
                                                    не подтвержден
                                                </span>
                                            </div>
                                            <input
                                                v-model="resolveNotification(definition.code).channels[channel.type]"
                                                type="checkbox"
                                                class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                                :value="contact.id"
                                                :disabled="!isContactAvailable(contact)"
                                            />
                                        </label>
                                    </div>
                                </div>
                                <p class="mt-3 text-xs text-slate-500">
                                    Доступны только подтвержденные контакты.
                                </p>
                            </div>
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
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
