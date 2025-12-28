<script setup>
import { computed } from 'vue';
import MainFooter from '../../Components/MainFooter.vue';
import MainHeader from '../../Components/MainHeader.vue';
import MainSidebar from '../../Components/MainSidebar.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Разделы', items: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
});

const hasSidebar = computed(() => (props.navigation?.items?.length ?? 0) > 0);
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="true"
                :login-label="$page.props.auth?.user?.login"
            />

            <section class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :items="navigation.items"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Filament</p>
                    <h1 class="mt-2 text-3xl font-semibold text-slate-900">Панель управления</h1>
                    <p class="mt-4 text-sm text-slate-600">
                        Выберите раздел в меню слева, чтобы перейти к настройкам.
                    </p>
                    <p v-if="!hasSidebar" class="mt-4 text-sm text-slate-500">
                        Для вашей роли пока нет доступных разделов.
                    </p>
                </div>
            </section>

            <MainFooter :app-name="appName" />
        </div>
    </main>
</template>

