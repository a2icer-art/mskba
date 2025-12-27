<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';
import MainHeaderNav from './MainHeaderNav.vue';

const props = defineProps({
    appName: {
        type: String,
        required: true,
    },
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    loginLabel: {
        type: String,
        default: '',
    },
});

defineEmits(['open-login']);

const page = usePage();
const roleAliases = computed(() => page.props.auth?.user?.roles ?? []);
const canSeeControlPanel = computed(
    () => props.isAuthenticated && roleAliases.value.some((role) => role === 'admin' || role === 'moderator')
);
</script>

<template>
    <header
        class="flex flex-col gap-6 rounded-3xl border border-amber-200/80 bg-white/70 px-6 py-5 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
    >
        <Link class="flex items-center gap-3" href="/" aria-label="Home">
            <BrandLogo />
            <div>
                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">Баскетбольный портал</p>
                <p class="text-lg font-semibold">{{ appName }}</p>
            </div>
        </Link>

        <MainHeaderNav :show-control-panel="canSeeControlPanel" />

        <div class="flex items-center gap-3">
            <Link
                v-if="isAuthenticated"
                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                href="/account"
            >
                {{ loginLabel || 'login' }}
            </Link>
            <button
                v-else
                class="rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                type="button"
                @click="$emit('open-login')"
            >
                Аккаунт
            </button>
        </div>
    </header>
</template>

