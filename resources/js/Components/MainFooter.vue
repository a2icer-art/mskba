<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';
import BrandLogo from './BrandLogo.vue';

defineProps({
    appName: {
        type: String,
        default: '',
    },
});

const footerRef = ref(null);
const footerHeight = ref(0);

const updateFooterMetrics = () => {
    if (!footerRef.value) {
        return;
    }
    const rect = footerRef.value.getBoundingClientRect();
    footerHeight.value = rect.height || 0;
    document.documentElement.style.setProperty('--app-footer-height', `${footerHeight.value}px`);
};

onMounted(() => {
    nextTick(() => {
        updateFooterMetrics();
    });
    window.addEventListener('resize', updateFooterMetrics);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updateFooterMetrics);
});
</script>

<template>
    <footer
        ref="footerRef"
        class="flex flex-col gap-4 border-t border-slate-200/80 pt-6 sm:flex-row sm:items-center sm:justify-between"
    >
        <div class="flex items-center gap-3 text-sm text-slate-600">
            <BrandLogo />
            <span>{{ appName }} • Баскетбольный портал</span>
        </div>
        <nav class="flex flex-wrap items-center gap-4 text-sm font-medium text-slate-600">
            <a class="transition hover:text-slate-900" href="#">О проекте</a>
            <a class="transition hover:text-slate-900" href="#">Правила</a>
            <a class="transition hover:text-slate-900" href="#">Контакты</a>
        </nav>
    </footer>
</template>
