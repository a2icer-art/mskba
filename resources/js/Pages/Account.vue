<script setup>
import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    user: {
        type: Object,
        default: null,
    },
    profile: {
        type: Object,
        default: null,
    },
    participantRoles: {
        type: Array,
        default: () => [],
    },
});

const baseTabs = [
    { key: 'user', label: 'Пользователь' },
    { key: 'profile', label: 'Профиль' },
];
const tabs = computed(() => [
    ...baseTabs,
    ...props.participantRoles.map((role) => ({
        key: `role-${role.id}`,
        label: role.label,
        alias: role.alias,
    })),
]);
const activeTab = ref(baseTabs[0].key);
const logoutForm = useForm({});

const userItems = computed(() => [
    { label: 'ID', value: props.user?.id ?? '—' },
    { label: 'Имя', value: props.user?.name ?? '—' },
    { label: 'Логин', value: props.user?.login ?? '—' },
]);

const isEmailVerified = computed(() => Boolean(props.user?.email_verified_at));
const emailValue = computed(() => props.user?.email ?? '—');
const verificationForm = useForm({});

const profileItems = computed(() => [
    { label: 'Имя', value: props.profile?.first_name ?? '—' },
    { label: 'Фамилия', value: props.profile?.last_name ?? '—' },
    { label: 'Отчество', value: props.profile?.middle_name ?? '—' },
    { label: 'Пол', value: props.profile?.gender ?? '—' },
    { label: 'Дата рождения', value: props.profile?.birth_date ?? '—' },
]);

const roleItems = (role) => [
    { label: 'Роль', value: role.label ?? '—' },
    { label: 'Alias', value: role.alias ?? '—' },
];

const activeRole = computed(() => props.participantRoles.find((role) => `role-${role.id}` === activeTab.value) ?? null);

const logout = () => {
    logoutForm.post('/logout');
};

const sendVerification = () => {
    verificationForm.post('/email/verification-notification');
};
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader :app-name="appName" :is-authenticated="true" :login-label="user?.login" />

            <section class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Аккаунт</p>
                        <h1 class="mt-2 text-3xl font-semibold text-slate-900">Профиль пользователя</h1>
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        class="rounded-full border px-4 py-2 text-sm font-medium transition"
                        :class="
                            activeTab === tab.key
                                ? 'border-slate-900 bg-slate-900 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300'
                        "
                        type="button"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2">
                    <div v-if="activeTab === 'user'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in userItems" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                        <div class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Email</span>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-medium text-slate-800">{{ emailValue }}</span>
                                <button
                                    v-if="!isEmailVerified"
                                    class="rounded-full border border-amber-300 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 transition hover:-translate-y-0.5 hover:border-amber-400"
                                    type="button"
                                    :disabled="verificationForm.processing"
                                    @click="sendVerification"
                                >
                                    Подтвердить
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-else-if="activeTab === 'profile'" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in profileItems" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>

                    <div v-else-if="activeRole" class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <div v-for="item in roleItems(activeRole)" :key="item.label" class="flex items-center justify-between border-b border-slate-100 py-3 last:border-b-0">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">{{ item.label }}</span>
                            <span class="text-sm font-medium text-slate-800">{{ item.value }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-end">
                    <button
                        class="rounded-full border border-slate-300 px-5 py-2 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-400"
                        type="button"
                        @click="logout"
                    >
                        Выйти
                    </button>
                </div>
            </section>

            <MainFooter />
        </div>
    </main>
</template>
