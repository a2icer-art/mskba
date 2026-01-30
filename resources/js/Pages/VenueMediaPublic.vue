<script setup>
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    venue: {
        type: Object,
        default: null,
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                <Breadcrumbs :items="breadcrumbs" />
                <h1 class="text-3xl font-semibold text-slate-900">Медиа площадки</h1>
                <p class="mt-2 text-sm text-slate-600">
                    Публичная галерея площадки «{{ venue?.name || 'Площадка' }}» находится в разработке.
                </p>
            </div>
        </div>

        <MainFooter :app-name="appName" />
    </div>
</template>
