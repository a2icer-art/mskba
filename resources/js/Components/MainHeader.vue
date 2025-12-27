<script setup>
import { Link } from '@inertiajs/vue3';
import BrandLogo from './BrandLogo.vue';

defineProps({
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

        <nav class="flex flex-wrap items-center gap-3 text-sm font-medium text-slate-700">
            <Link
                class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300"
                href="/venues"
            >
                Площадки
            </Link>
            <a class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300" href="#">
                Матчи
            </a>
            <a class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300" href="#">
                Турниры
            </a>
            <a class="rounded-full border border-slate-200 bg-white/80 px-4 py-2 transition hover:-translate-y-0.5 hover:border-slate-300" href="#">
                Сообщество
            </a>
        </nav>

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
