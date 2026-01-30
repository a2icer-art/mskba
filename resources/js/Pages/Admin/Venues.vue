<script setup>
import { computed, ref, watch } from 'vue';
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
    amenities: {
        type: Array,
        default: () => [],
    },
});

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionErrors = computed(() => page.props?.errors ?? {});
const errorNotice = computed(() => actionErrors.value?.icon ?? '');

const amenityItems = ref([]);
watch(
    () => props.amenities,
    (items) => {
        amenityItems.value = (items ?? []).map((item) => ({ ...item }));
    },
    { immediate: true }
);

const createForm = useForm({
    name: '',
    alias: '',
    icon: null,
});
const updateForm = useForm({
    name: '',
    alias: '',
    sort_order: 0,
});
const deleteForm = useForm({});
const createIconKey = ref(0);

const iconForm = useForm({
    icon: null,
});
const inputKeys = ref({});

const uploadIcon = (amenityId, file) => {
    if (!file) {
        return;
    }
    iconForm.icon = file;
    iconForm.post(`/admin/venues/amenities/${amenityId}/icon`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            iconForm.reset('icon');
            inputKeys.value[amenityId] = (inputKeys.value[amenityId] ?? 0) + 1;
        },
    });
};

const submitCreate = () => {
    createForm.post('/admin/venues/amenities', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            createForm.reset('name', 'alias', 'icon');
            createIconKey.value += 1;
        },
    });
};

const submitUpdate = (amenity) => {
    updateForm.name = amenity.name;
    updateForm.alias = amenity.alias;
    updateForm.sort_order = amenity.sort_order ?? 0;
    updateForm.patch(`/admin/venues/amenities/${amenity.id}`, {
        preserveScroll: true,
    });
};

const confirmDelete = (amenity) => {
    if (!window.confirm(`Удалить опцию «${amenity.name}»?`)) {
        return;
    }
    deleteForm.delete(`/admin/venues/amenities/${amenity.id}`, {
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
                    <h1 class="text-3xl font-semibold text-slate-900">Настройка площадок</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Управление стандартными опциями и иконками площадок.
                    </p>

                    <div class="mt-6 space-y-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h2 class="text-lg font-semibold text-slate-900">Стандартные опции</h2>
                            <p class="text-xs text-slate-500">Иконки поддерживают svg, png, jpg, webp</p>
                        </div>

                        <form class="grid gap-4 rounded-2xl border border-slate-200/80 bg-slate-50 px-4 py-4" @submit.prevent="submitCreate">
                            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-[2fr_2fr_1.2fr]">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Название
                                    </label>
                                    <input
                                        v-model="createForm.name"
                                        type="text"
                                        maxlength="120"
                                        class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="Например: Парковка"
                                    />
                                    <p v-if="actionErrors.name" class="text-xs text-rose-700">
                                        {{ actionErrors.name }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Alias (опционально)
                                    </label>
                                    <input
                                        v-model="createForm.alias"
                                        type="text"
                                        maxlength="120"
                                        class="h-11 w-full rounded-2xl border border-slate-200 bg-white px-4 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="parking"
                                    />
                                    <p v-if="actionErrors.alias" class="text-xs text-rose-700">
                                        {{ actionErrors.alias }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Иконка (опционально)
                                    </label>
                                    <input
                                        :key="createIconKey"
                                        type="file"
                                        accept="image/svg+xml,image/png,image/jpeg,image/webp"
                                        class="text-sm text-slate-600"
                                        @change="createForm.icon = $event.target.files[0]"
                                    />
                                    <p v-if="actionErrors.icon" class="text-xs text-rose-700">
                                        {{ actionErrors.icon }}
                                    </p>
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button
                                    type="submit"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    :disabled="createForm.processing"
                                >
                                    Добавить опцию
                                </button>
                            </div>
                        </form>

                        <div v-if="amenityItems.length" class="space-y-3">
                            <div
                                v-for="amenity in amenityItems"
                                :key="amenity.id"
                                class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200/80 px-3 py-3"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-slate-50">
                                        <img
                                            v-if="amenity.icon_url"
                                            :src="amenity.icon_url"
                                            :alt="amenity.name"
                                            class="h-8 w-8 object-contain"
                                        />
                                        <span v-else class="text-xs text-slate-400">нет</span>
                                    </div>
                                    <div class="grid gap-2">
                                        <input
                                            v-model="amenity.name"
                                            type="text"
                                            maxlength="120"
                                            class="h-10 w-56 rounded-2xl border border-slate-200 bg-white px-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        />
                                        <input
                                            v-model="amenity.alias"
                                            type="text"
                                            maxlength="120"
                                            class="h-9 w-56 rounded-2xl border border-slate-200 bg-white px-3 text-xs text-slate-600 outline-none transition focus:border-slate-400"
                                            placeholder="alias"
                                        />
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <input
                                        v-model.number="amenity.sort_order"
                                        type="number"
                                        min="0"
                                        max="9999"
                                        class="h-9 w-24 rounded-2xl border border-slate-200 bg-white px-3 text-xs text-slate-700 outline-none transition focus:border-slate-400"
                                        title="Сортировка"
                                    />
                                    <input
                                        :key="inputKeys[amenity.id] || 0"
                                        type="file"
                                        accept="image/svg+xml,image/png,image/jpeg,image/webp"
                                        class="text-sm text-slate-600"
                                        @change="uploadIcon(amenity.id, $event.target.files[0])"
                                    />
                                    <button
                                        type="button"
                                        class="rounded-full border border-slate-200 px-3 py-1 text-xs font-semibold text-slate-600 transition hover:border-slate-300"
                                        :disabled="updateForm.processing"
                                        @click="submitUpdate(amenity)"
                                    >
                                        Сохранить
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-full border border-rose-200 px-3 py-1 text-xs font-semibold text-rose-600 transition hover:border-rose-300"
                                        :disabled="deleteForm.processing"
                                        @click="confirmDelete(amenity)"
                                    >
                                        Удалить
                                    </button>
                                </div>
                            </div>
                        </div>

                        <p v-else class="text-sm text-slate-500">
                            Стандартные опции не найдены.
                        </p>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
