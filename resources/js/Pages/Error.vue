<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AuthModal from '../Components/AuthModal.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    status: {
        type: Number,
        required: true,
    },
    message: {
        type: String,
        default: '',
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
const showAuthModal = ref(false);
const authMode = ref('login');

const errorMessage = computed(() => {
    if (props.message) {
        return props.message;
    }

    if (props.status === 403) {
        return 'Доступ запрещен.';
    }

    if (props.status === 500) {
        return 'Внутренняя ошибка сервера.';
    }

    return 'Страница не найдена.';
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
                @open-login="authMode = 'login'; showAuthModal = true"
            />

            <main class="grid gap-6">
                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <div class="flex flex-col gap-4 text-center">
                        <p class="text-6xl font-black tracking-[0.3em] text-slate-900">
                            {{ status }}
                        </p>
                        <p class="text-sm uppercase tracking-[0.2em] text-slate-500">
                            {{ errorMessage }}
                        </p>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <AuthModal
            :app-name="appName"
            :is-open="showAuthModal"
            :participant-roles="page.props.participantRoles || []"
            :initial-mode="authMode"
            @close="showAuthModal = false"
        />
    </div>
</template>

