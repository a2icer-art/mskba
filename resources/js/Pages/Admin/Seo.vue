<script setup>
import { computed, ref } from 'vue';
import { useForm, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../../Components/Breadcrumbs.vue';
import MainFooter from '../../Components/MainFooter.vue';
import MainHeader from '../../Components/MainHeader.vue';
import MainSidebar from '../../Components/MainSidebar.vue';
import SystemNoticeStack from '../../Components/SystemNoticeStack.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Разделы', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    groups: {
        type: Array,
        default: () => [],
    },
    assets: {
        type: Object,
        default: () => ({}),
    },
});

const page = usePage();
const navigationData = computed(() => props.navigation?.data ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionErrors = computed(() => page.props?.errors ?? {});
const errorNotice = computed(() => {
    if (!Object.keys(actionErrors.value).length) {
        return '';
    }
    return 'Не удалось сохранить изменения. Проверьте данные.';
});

const cloneGroups = (groups) =>
    groups.map((group) => ({
        key: group.key,
        title: group.title,
        items: (group.items ?? []).map((item) => ({
            page_type: item.page_type,
            page_id: item.page_id,
            label: item.label,
            href: item.href,
            title: item.meta?.title ?? '',
            description: item.meta?.description ?? '',
            keywords: item.meta?.keywords ?? '',
        })),
    }));

const groupsState = ref(cloneGroups(props.groups));

const rowForm = useForm({
    page_type: '',
    page_id: 0,
    title: '',
    description: '',
    keywords: '',
});
const bulkForm = useForm({
    items: [],
});
const faviconForm = useForm({
    favicon: null,
});
const metaSettingsForm = useForm({
    include_site_title: Boolean(props.assets?.include_site_title ?? false),
});
const faviconFile = ref(null);
const faviconInputKey = ref(0);
const faviconInputRef = ref(null);
const savingRowKey = ref('');

const saveRow = (item) => {
    savingRowKey.value = `${item.page_type}:${item.page_id}`;
    rowForm.page_type = item.page_type;
    rowForm.page_id = item.page_id;
    rowForm.title = item.title ?? '';
    rowForm.description = item.description ?? '';
    rowForm.keywords = item.keywords ?? '';
    rowForm.patch('/admin/seo', {
        preserveScroll: true,
        onFinish: () => {
            savingRowKey.value = '';
        },
    });
};

const saveGroup = (group) => {
    bulkForm.items = group.items.map((item) => ({
        page_type: item.page_type,
        page_id: item.page_id,
        title: item.title ?? '',
        description: item.description ?? '',
        keywords: item.keywords ?? '',
    }));
    bulkForm.post('/admin/seo/bulk', {
        preserveScroll: true,
    });
};

const submitFavicon = () => {
    faviconForm.favicon = faviconFile.value;
    faviconForm.post('/admin/seo/favicon', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            faviconForm.reset('favicon');
            faviconFile.value = null;
            faviconInputKey.value += 1;
        },
    });
};

const handleFaviconChange = (event) => {
    faviconForm.clearErrors('favicon');
    faviconFile.value = event.target.files?.[0] ?? null;
};

const openFaviconPicker = () => {
    faviconInputRef.value?.click?.();
};

const submitMetaSettings = () => {
    metaSettingsForm.patch('/admin/seo/settings', {
        preserveScroll: true,
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" :error="errorNotice" />

        <div class="relative mx-auto flex max-w-[1360px] flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="Boolean($page.props.auth?.user)"
                :login-label="$page.props.auth?.user?.login"
            />

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <div class="flex items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">SEO метатеги</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Управляйте title, description и keywords для страниц и сущностей.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-col gap-6">
                        <section class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    Favicon
                                </h2>
                            </div>
                            <form class="mt-4 flex flex-wrap items-center gap-4" @submit.prevent="submitFavicon">
                                <div class="flex items-center gap-3 text-sm text-slate-600">
                                    <span>Текущий:</span>
                                    <img
                                        v-if="props.assets?.favicon_url"
                                        class="h-8 w-8 rounded border border-slate-200 bg-white"
                                        :src="props.assets.favicon_url"
                                        alt="favicon"
                                    />
                                    <span v-else class="text-slate-500">Не задан</span>
                                </div>
                                <div class="flex flex-wrap items-center gap-3">
                                    <input
                                        ref="faviconInputRef"
                                        :key="faviconInputKey"
                                        class="text-sm text-slate-600"
                                        type="file"
                                        accept=".ico,.png,.svg"
                                        @change="handleFaviconChange"
                                        @input="handleFaviconChange"
                                    />
                                    <button
                                        class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.15em] text-slate-700 transition hover:border-slate-400"
                                        type="button"
                                        @click="openFaviconPicker"
                                    >
                                        Выбрать файл
                                    </button>
                                    <span class="text-xs text-slate-500">
                                        {{ faviconFile?.name || 'Файл не выбран' }}
                                    </span>
                                    <button
                                        class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.15em] text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                        type="submit"
                                        :disabled="faviconForm.processing || !faviconFile"
                                    >
                                        Загрузить
                                    </button>
                                </div>
                                <div v-if="faviconForm.errors?.favicon" class="text-xs text-rose-700">
                                    {{ faviconForm.errors.favicon }}
                                </div>
                            </form>
                        </section>

                        <section class="rounded-2xl border border-slate-200 bg-white p-4">
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    Title
                                </h2>
                            </div>
                            <form class="mt-4 flex flex-wrap items-center justify-between gap-3" @submit.prevent="submitMetaSettings">
                                <label class="flex items-center gap-3 text-xs uppercase tracking-[0.15em] text-slate-500">
                                    <input v-model="metaSettingsForm.include_site_title" class="input-switch" type="checkbox" />
                                    Включать название сайта в title
                                </label>
                                <button
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.15em] text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                    type="submit"
                                    :disabled="metaSettingsForm.processing"
                                >
                                    Сохранить
                                </button>
                            </form>
                        </section>

                        <section
                            v-for="group in groupsState"
                            :key="group.key"
                            class="rounded-2xl border border-slate-200 bg-white p-4"
                        >
                            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 pb-3">
                                <h2 class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                    {{ group.title }}
                                </h2>
                                <button
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-xs font-semibold uppercase tracking-[0.15em] text-white transition hover:-translate-y-0.5 hover:bg-slate-800 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                                    type="button"
                                    :disabled="bulkForm.processing"
                                    @click="saveGroup(group)"
                                >
                                    Сохранить все
                                </button>
                            </div>

                            <div class="mt-4 grid gap-4">
                                <div
                                    v-for="item in group.items"
                                    :key="`${item.page_type}-${item.page_id}`"
                                    class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                                >
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <div class="text-sm font-semibold text-slate-900">
                                                {{ item.label }}
                                            </div>
                                            <a
                                                v-if="item.href"
                                                class="text-xs text-slate-500 hover:text-slate-700"
                                                :href="item.href"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ item.href }}
                                            </a>
                                        </div>
                                        <button
                                            class="rounded-full border border-slate-300 px-3 py-1 text-xs font-semibold uppercase tracking-[0.15em] text-slate-700 transition hover:border-slate-400"
                                            type="button"
                                            :disabled="rowForm.processing && savingRowKey === `${item.page_type}:${item.page_id}`"
                                            @click="saveRow(item)"
                                        >
                                            Сохранить
                                        </button>
                                    </div>

                                    <div class="mt-3 grid gap-3 lg:grid-cols-3">
                                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                            Title
                                            <input
                                                v-model="item.title"
                                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                                type="text"
                                            />
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                            Description
                                            <textarea
                                                v-model="item.description"
                                                class="min-h-[72px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                                rows="3"
                                            ></textarea>
                                        </label>
                                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                            Keywords
                                            <textarea
                                                v-model="item.keywords"
                                                class="min-h-[72px] rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                                rows="3"
                                            ></textarea>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </section>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
