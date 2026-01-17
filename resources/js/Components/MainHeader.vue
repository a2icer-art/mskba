<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';
import MainHeaderNav from './MainHeaderNav.vue';
import { useMessagePolling } from '../Composables/useMessagePolling';

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
const roleLevel = computed(() => Number(page.props.auth?.user?.role_level ?? 0));
const canSeeControlPanel = computed(
    () => props.isAuthenticated && roleLevel.value > 10
);
const { unreadCount } = useMessagePolling({
    enabled: props.isAuthenticated,
    pollUrl: '/account/messages/poll',
    params: { scope: 'counter' },
});
const unreadBadge = computed(() => {
    if (!props.isAuthenticated) {
        return '';
    }
    const count = Number(unreadCount.value ?? 0);
    if (count <= 0) {
        return '';
    }
    return count > 9 ? '…' : String(count);
});
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
                class="relative rounded-full bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                href="/account"
            >
                {{ loginLabel || 'login' }}
                <span
                    v-if="unreadBadge"
                    class="absolute -right-1 -top-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                >
                    {{ unreadBadge }}
                </span>
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
