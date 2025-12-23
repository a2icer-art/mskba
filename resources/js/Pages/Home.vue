<script setup>
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
});
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader :app-name="appName" />

            <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
                <MainSidebar />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Главная</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">Добро пожаловать в {{ appName }}</h1>
                            <p class="mt-3 max-w-2xl text-sm text-slate-600">
                                Основной контейнер для контента: расписание игр, новости сообщества и быстрые действия.
                            </p>
                        </div>
                        <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-emerald-500">
                            Создать игру
                        </button>
                    </div>

                    <div class="mt-6 grid gap-4 lg:grid-cols-3">
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Ближайшее</p>
                            <p class="mt-3 text-lg font-semibold">Тренировка #12</p>
                            <p class="mt-2 text-sm text-slate-600">Сегодня, 19:00 • Лужники</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Команды</p>
                            <p class="mt-3 text-lg font-semibold">5 активных</p>
                            <p class="mt-2 text-sm text-slate-600">2 набора открыто</p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Рейтинг</p>
                            <p class="mt-3 text-lg font-semibold">Топ-16</p>
                            <p class="mt-2 text-sm text-slate-600">Лига выходного дня</p>
                        </div>
                    </div>
                </div>
            </section>

            <MainFooter />
        </div>
    </main>
</template>
