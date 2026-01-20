<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';
import MainHeaderNav from './MainHeaderNav.vue';
import { useMessageRealtime } from '../Composables/useMessageRealtime';

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
    sidebarTitle: {
        type: String,
        default: '',
    },
    sidebarItems: {
        type: Array,
        default: () => [],
    },
    sidebarActiveHref: {
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
const navItems = computed(() => {
    const items = [
        { label: 'Площадки', href: '/venues' },
        { label: 'События', href: '/events' },
        { label: 'Турниры', href: '#' },
        { label: 'Сообщество', href: '#' },
    ];
    if (canSeeControlPanel.value) {
        items.push({ label: 'Панель управления', href: '/admin', variant: 'admin' });
    }
    return items;
});
const isMenuOpen = ref(false);
const isSidebarOpen = ref(false);
const toggleMenu = () => {
    isMenuOpen.value = !isMenuOpen.value;
    if (!isMenuOpen.value) {
        isSidebarOpen.value = false;
    }
};
const closeMenu = () => {
    isMenuOpen.value = false;
    isSidebarOpen.value = false;
};
const { unreadCount } = useMessageRealtime({
    enabled: props.isAuthenticated,
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

const sidebarItemsSource = computed(() => {
    if (Array.isArray(props.sidebarItems) && props.sidebarItems.length > 0) {
        return props.sidebarItems;
    }
    const navigation = page.props?.navigation;
    if (Array.isArray(navigation?.data)) {
        return navigation.data;
    }
    if (Array.isArray(navigation?.items)) {
        return navigation.items;
    }
    return [];
});

const sidebarGroups = computed(() => {
    const items = sidebarItemsSource.value;
    if (!items.length) {
        return [];
    }
    const isGrouped = items.some((item) => Array.isArray(item?.items));
    if (!isGrouped) {
        return [
            {
                title: '',
                items,
            },
        ];
    }
    return items
        .filter((group) => Array.isArray(group?.items) && group.items.length > 0)
        .map((group) => ({
            title: group?.title ?? '',
            items: group.items,
        }));
});
const hasSidebarItems = computed(() => sidebarGroups.value.length > 0);
const sidebarTitleLabel = computed(() => props.sidebarTitle || page.props?.navigation?.title || 'Навигация');
const formatSidebarBadge = (value) => {
    if (value === null || value === undefined || value === '') {
        return '';
    }
    const numeric = Number(value);
    if (Number.isFinite(numeric)) {
        if (numeric <= 0) {
            return '';
        }
        return numeric > 9 ? '…' : String(numeric);
    }
    return String(value);
};

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
                    class="flex flex-col gap-4 rounded-3xl px-6 py-5"
                    :style="{ width: `${headerWidth}px`, marginLeft: `${headerLeft}px` }"
                >
                    <div class="flex w-full items-center justify-between gap-4">
                        <Link class="flex items-center gap-3" href="/" aria-label="Home">
                            <BrandLogo />
                            <div>
                                <p class="hidden text-xs uppercase tracking-[0.25em] text-slate-500 sm:block">Баскетбольный портал</p>
                                <p class="text-lg font-semibold">{{ appName }}</p>
                            </div>
                        </Link>

                        <div class="flex items-center gap-3">
                            <MainHeaderNav class="hidden sm:flex" :show-control-panel="canSeeControlPanel" />
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
                            <button
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300 sm:hidden"
                                type="button"
                                aria-label="Открыть меню"
                                @click="toggleMenu"
                            >
                                <span class="flex flex-col gap-1">
                                    <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                                    <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                                    <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div v-if="isMenuOpen" class="mt-4 flex flex-col gap-2 sm:hidden">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                            :class="item.variant === 'admin' ? 'border-blue-200 bg-blue-50 text-blue-700' : ''"
                            @click="closeMenu"
                        >
                            {{ item.label }}
                        </Link>
                        <div v-if="hasSidebarItems" class="mt-3 border-t border-slate-200/80 pt-3">
                            <button
                                class="flex w-full items-center justify-between gap-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400"
                                type="button"
                                :aria-expanded="isSidebarOpen"
                                @click="isSidebarOpen = !isSidebarOpen"
                            >
                                <span>{{ sidebarTitleLabel }}</span>
                                <span class="text-xs transition" :class="isSidebarOpen ? 'rotate-180' : 'rotate-0'">▾</span>
                            </button>
                            <div v-if="isSidebarOpen" class="mt-2 space-y-3">
                                <div
                                    v-for="group in sidebarGroups"
                                    :key="group.title || group.items[0]?.href"
                                    class="space-y-2"
                                >
                                    <p v-if="group.title" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                                        {{ group.title }}
                                    </p>
                                    <div class="flex flex-col gap-2">
                                        <Link
                                            v-for="item in group.items"
                                            :key="item.href"
                                            :href="item.href"
                                            class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-2 text-sm font-medium transition"
                                            :class="
                                                item.href === (sidebarActiveHref || page.url)
                                                    ? 'border-slate-900 bg-slate-900 text-white'
                                                    : 'border-slate-200 bg-white/80 text-slate-700 hover:border-slate-300'
                                            "
                                            @click="closeMenu"
                                        >
                                            <span>{{ item.label }}</span>
                                            <span
                                                v-if="formatSidebarBadge(item.badge)"
                                                class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                            >
                                                {{ formatSidebarBadge(item.badge) }}
                                            </span>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <template v-else>
        <div class="flex w-full items-center justify-between gap-4">
            <Link class="flex items-center gap-3" href="/" aria-label="Home">
                <BrandLogo />
                <div>
                    <p class="hidden text-xs uppercase tracking-[0.25em] text-slate-500 sm:block">Баскетбольный портал</p>
                    <p class="text-lg font-semibold">{{ appName }}</p>
                </div>
            </Link>

            <div class="flex items-center gap-3">
                <MainHeaderNav class="hidden sm:flex" :show-control-panel="canSeeControlPanel" />
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
                <button
                    class="flex h-10 w-10 items-center justify-center rounded-full border border-slate-200 bg-white/80 text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300 sm:hidden"
                    type="button"
                    aria-label="Открыть меню"
                    @click="toggleMenu"
                >
                    <span class="flex flex-col gap-1">
                        <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                        <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                        <span class="h-0.5 w-4 rounded-full bg-slate-600"></span>
                    </span>
                </button>
            </div>
        </div>
        <div v-if="isMenuOpen" class="mt-4 flex flex-col gap-2 sm:hidden">
            <Link
                v-for="item in navItems"
                :key="item.href"
                :href="item.href"
                class="rounded-2xl border border-slate-200 bg-white/80 px-4 py-2 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                :class="item.variant === 'admin' ? 'border-blue-200 bg-blue-50 text-blue-700' : ''"
                @click="closeMenu"
            >
                {{ item.label }}
            </Link>
            <div v-if="hasSidebarItems" class="mt-3 border-t border-slate-200/80 pt-3">
                <button
                    class="flex w-full items-center justify-between gap-3 text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400"
                    type="button"
                    :aria-expanded="isSidebarOpen"
                    @click="isSidebarOpen = !isSidebarOpen"
                >
                    <span>{{ sidebarTitleLabel }}</span>
                    <span class="text-xs transition" :class="isSidebarOpen ? 'rotate-180' : 'rotate-0'">▾</span>
                </button>
                <div v-if="isSidebarOpen" class="mt-2 space-y-3">
                    <div
                        v-for="group in sidebarGroups"
                        :key="group.title || group.items[0]?.href"
                        class="space-y-2"
                    >
                        <p v-if="group.title" class="text-[10px] font-semibold uppercase tracking-[0.2em] text-slate-400">
                            {{ group.title }}
                        </p>
                        <div class="flex flex-col gap-2">
                            <Link
                                v-for="item in group.items"
                                :key="item.href"
                                :href="item.href"
                                class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-2 text-sm font-medium transition"
                                :class="
                                    item.href === (sidebarActiveHref || page.url)
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-200 bg-white/80 text-slate-700 hover:border-slate-300'
                                "
                                @click="closeMenu"
                            >
                                <span>{{ item.label }}</span>
                                <span
                                    v-if="formatSidebarBadge(item.badge)"
                                    class="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-semibold text-white"
                                >
                                    {{ formatSidebarBadge(item.badge) }}
                                </span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
            </template>
        </header>
    </div>
</template>
