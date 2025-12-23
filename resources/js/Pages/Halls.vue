<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    halls: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
            />

            <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
                <MainSidebar />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Залы</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Список залов</h1>
                            <p class="mt-3 max-w-2xl text-sm text-slate-600">
                                Список мест проведения игр и тренировок с базовой информацией и типом площадки.
                            </p>
                        </div>
                    </div>

                    <div v-if="halls.length" class="mt-6 grid gap-4 lg:grid-cols-2">
                        <article
                            v-for="hall in halls"
                            :key="hall.id"
                            class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                        >
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-lg font-semibold text-slate-900">{{ hall.name }}</h2>
                                    <p class="mt-1 text-sm text-slate-600">
                                        {{ hall.type?.name || 'Тип не указан' }}
                                    </p>
                                    <p v-if="hall.address" class="mt-2 text-sm text-slate-600">
                                        {{ hall.address }}
                                    </p>
                                </div>
                                <span class="rounded-full bg-white px-3 py-1 text-xs font-medium uppercase text-slate-500">
                                    {{ hall.alias }}
                                </span>
                            </div>
                        </article>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-600">
                        Пока нет залов для отображения.
                    </div>
                </div>
            </section>

            <MainFooter />
        </div>
    </main>
</template>
