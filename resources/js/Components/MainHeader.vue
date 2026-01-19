<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
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

const wrapperRef = ref(null);
const headerRef = ref(null);
const headerHeight = ref(0);
const headerLeft = ref(0);
const headerWidth = ref(0);
const headerInitialOffset = ref(0);
const headerTopOffset = 0;
const isFixed = ref(false);

const updateHeaderMetrics = () => {
    if (!headerRef.value || !wrapperRef.value) {
        return;
    }
    const rect = wrapperRef.value.getBoundingClientRect();
    const headerRect = headerRef.value.getBoundingClientRect();
    headerHeight.value = headerRect.height || 0;
    headerLeft.value = rect.left || 0;
    headerWidth.value = rect.width || 0;
    if (!isFixed.value) {
        headerInitialOffset.value = window.scrollY + rect.top;
    }
    document.documentElement.style.setProperty('--app-header-height', `${headerHeight.value}px`);
    document.documentElement.style.setProperty('--app-header-offset', `${headerHeight.value + headerTopOffset}px`);
};

const updateHeaderFixed = () => {
    if (!headerRef.value) {
        return;
    }
    isFixed.value = window.scrollY >= headerInitialOffset.value - headerTopOffset;
};

onMounted(() => {
    nextTick(() => {
        updateHeaderMetrics();
        updateHeaderFixed();
    });
    window.addEventListener('resize', updateHeaderMetrics);
    window.addEventListener('resize', updateHeaderFixed);
    window.addEventListener('scroll', updateHeaderFixed, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateHeaderMetrics);
    window.removeEventListener('resize', updateHeaderFixed);
    window.removeEventListener('scroll', updateHeaderFixed);
});
</script>

<template>
    <div ref="wrapperRef">
        <div v-if="isFixed" :style="{ height: `${headerHeight}px` }"></div>
        <header
            ref="headerRef"
            class="flex flex-col gap-6 rounded-3xl border border-amber-200/80 bg-white/70 backdrop-blur sm:flex-row sm:items-center sm:justify-between"
            :class="isFixed ? 'fixed z-40 w-full rounded-none border-transparent bg-white/70 backdrop-blur shadow-[0_6px_18px_rgba(15,23,42,0.08)]' : 'relative px-6 py-5'"
            :style="
                isFixed
                    ? {
                          top: `${headerTopOffset}px`,
                          left: '0px',
                          right: '0px',
                      }
                    : {}
            "
        >
            <div v-if="isFixed" class="w-full">
                <div
                    class="flex flex-col gap-6 rounded-3xl px-6 py-5 sm:flex-row sm:items-center sm:justify-between"
                    :style="{ width: `${headerWidth}px`, marginLeft: `${headerLeft}px` }"
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
                </div>
            </div>
            <template v-else>
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
            </template>
        </header>
    </div>
</template>
