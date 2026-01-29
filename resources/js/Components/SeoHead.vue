<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

const page = usePage();
const meta = computed(() => page.props?.meta ?? {});
const appName = computed(() => page.props?.appName ?? 'MSKBA');
const includeSiteTitle = computed(() => Boolean(page.props?.metaSettings?.include_site_title ?? false));
const title = computed(() => {
    const rawTitle = meta.value?.title || '';
    if (!rawTitle) {
        return appName.value;
    }
    if (includeSiteTitle.value) {
        return `${appName.value} — ${rawTitle}`;
    }
    return rawTitle;
});
const description = computed(() => meta.value?.description || '');
const keywords = computed(() => meta.value?.keywords || '');
const faviconUrl = computed(() => page.props?.faviconUrl || '');
</script>

<template>
    <Head :title="title">
        <meta v-if="description" name="description" :content="description" />
        <meta v-if="keywords" name="keywords" :content="keywords" />
        <link v-if="faviconUrl" rel="icon" :href="faviconUrl" />
    </Head>
</template>
