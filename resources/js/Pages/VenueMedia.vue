<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../Components/Breadcrumbs.vue';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';
import SystemNoticeStack from '../Components/SystemNoticeStack.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    venue: {
        type: Object,
        default: null,
    },
    media: {
        type: Array,
        default: () => [],
    },
    mediaUrls: {
        type: Object,
        default: () => ({ upload: '', base: '' }),
    },
    navigation: {
        type: Object,
        default: () => ({ title: 'Площадки', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    activeTypeSlug: {
        type: String,
        default: '',
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
    canManageMedia: {
        type: Boolean,
        default: false,
    },
});

const navigationData = computed(() => props.navigation?.data ?? props.navigation?.items ?? []);
const adminNavigationData = computed(() => props.navigation?.admin ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasAdminSidebar = computed(() => (adminNavigationData.value?.length ?? 0) > 0);
const hasAnySidebar = computed(() => hasSidebar.value || hasAdminSidebar.value);
const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionError = computed(() => page.props?.errors ?? {});
const errorNotice = computed(() => {
    if (!actionError.value || !Object.keys(actionError.value).length) {
        return '';
    }
    return 'Не удалось выполнить действие. Проверьте данные.';
});

const uploading = ref(false);
const uploadProgress = ref(0);
const uploadFileName = ref('');
const mediaItems = ref(props.media.map(normalizeItem));

const fileInputKey = ref(0);

function normalizeItem(item) {
    return {
        ...item,
        _editTitle: item.title ?? '',
        _editDescription: item.description ?? '',
        _editCollection: item.collection ?? 'gallery',
        _editIsAvatar: Boolean(item.is_avatar),
        _editIsFeatured: Boolean(item.is_featured),
        _saving: false,
        _working: false,
        _message: '',
    };
}

const onFilesSelected = async (event) => {
    if (!props.canManageMedia) {
        return;
    }

    const files = Array.from(event.target.files || []);
    if (!files.length) {
        return;
    }

    for (const file of files) {
        await uploadFile(file);
    }

    fileInputKey.value += 1;
};

const uploadFile = async (file) => {
    if (!props.mediaUrls?.upload) {
        return;
    }

    const formData = new FormData();
    formData.append('file', file);
    formData.append('collection', 'gallery');

    uploading.value = true;
    uploadProgress.value = 0;
    uploadFileName.value = file.name;

    try {
        const response = await window.axios.post(props.mediaUrls.upload, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
            onUploadProgress: (event) => {
                if (event.lengthComputable) {
                    uploadProgress.value = Math.round((event.loaded * 100) / event.total);
                }
            },
        });

        if (response?.data?.media) {
            mediaItems.value.unshift(normalizeItem(response.data.media));
        }
    } catch (error) {
        console.error(error);
        alert('Ошибка при загрузке файла');
    } finally {
        uploading.value = false;
        uploadProgress.value = 0;
        uploadFileName.value = '';
    }
};

const saveMeta = async (item) => {
    if (!props.canManageMedia) {
        return;
    }

    item._saving = true;
    item._message = '';

    try {
        const response = await window.axios.patch(`${props.mediaUrls.base}/${item.id}`, {
            title: item._editTitle || null,
            description: item._editDescription || null,
            collection: item._editCollection || null,
            is_avatar: item._editIsAvatar,
            is_featured: item._editIsFeatured,
        });

        if (response?.data?.media) {
            Object.assign(item, normalizeItem(response.data.media));
        }

        item._message = 'Сохранено';
    } catch (error) {
        console.error(error);
        if (error?.response?.status === 409 && error?.response?.data?.error === 'last_avatar') {
            item._message = 'Нельзя убрать последний аватар';
            item._editIsAvatar = true;
        } else {
            item._message = 'Ошибка сохранения';
        }
    } finally {
        item._saving = false;
    }
};

const softDelete = async (item) => {
    if (!props.canManageMedia) {
        return;
    }

    if (!confirm('Удалить медиа (мягко)?')) {
        return;
    }

    item._working = true;
    item._message = '';

    try {
        await window.axios.delete(`${props.mediaUrls.base}/${item.id}`);
        item.deleted_at = new Date().toISOString();
        item._message = 'Удалено';
    } catch (error) {
        console.error(error);
        item._message = 'Ошибка удаления';
    } finally {
        item._working = false;
    }
};

const restoreItem = async (item) => {
    if (!props.canManageMedia) {
        return;
    }

    if (!confirm('Восстановить медиа?')) {
        return;
    }

    item._working = true;
    item._message = '';

    try {
        await window.axios.post(`${props.mediaUrls.base}/${item.id}/restore`);
        item.deleted_at = null;
        item._message = 'Восстановлено';
    } catch (error) {
        if (error?.response?.status === 409) {
            item._message = 'Файл отсутствует на диске';
        } else {
            item._message = 'Ошибка восстановления';
        }
        console.error(error);
    } finally {
        item._working = false;
    }
};

const forceDelete = async (item) => {
    if (!props.canManageMedia) {
        return;
    }

    if (!confirm('Удалить медиа полностью? Это необратимо.')) {
        return;
    }

    item._working = true;
    item._message = '';

    try {
        await window.axios.delete(`${props.mediaUrls.base}/${item.id}/force`);
        mediaItems.value = mediaItems.value.filter((current) => current.id !== item.id);
    } catch (error) {
        console.error(error);
        item._message = 'Ошибка удаления';
    } finally {
        item._working = false;
    }
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

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[280px_1fr]': hasAnySidebar }">
                <div v-if="hasAnySidebar" class="flex flex-col gap-4">
                    <MainSidebar
                        v-if="hasSidebar"
                        :data="navigationData"
                        :active-href="activeHref"
                    />
                    <MainSidebar
                        v-if="hasAdminSidebar"
                        :data="adminNavigationData"
                        :active-href="activeHref"
                    />
                </div>

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />

                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <h1 class="text-3xl font-semibold text-slate-900">Медиа площадки</h1>
                            <p class="mt-2 text-sm text-slate-600">
                                Управляйте медиафайлами: загрузка, редактирование и восстановление.
                            </p>
                        </div>
                    </div>

                    <div v-if="!canManageMedia" class="mt-6 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        У вас нет прав на управление медиа.
                    </div>

                    <div v-else class="mt-6 space-y-6">
                        <div class="rounded-2xl border border-slate-200 p-4">
                            <label class="block text-sm font-medium text-slate-700">
                                Загрузить файлы
                                <input
                                    :key="fileInputKey"
                                    type="file"
                                    multiple
                                    class="mt-2 block w-full rounded-xl border border-slate-200 bg-white p-2 text-sm"
                                    @change="onFilesSelected"
                                />
                            </label>

                            <div v-if="uploading" class="mt-4">
                                <div class="text-xs text-slate-600">Загрузка: {{ uploadFileName }}</div>
                                <div class="mt-2 h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-2 rounded-full bg-emerald-500" :style="{ width: uploadProgress + '%' }"></div>
                                </div>
                                <div class="mt-1 text-xs text-slate-500">{{ uploadProgress }}%</div>
                            </div>
                        </div>

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <h2 class="text-xl font-semibold text-slate-900">Файлы</h2>
                                <div class="text-xs text-slate-500">Всего: {{ mediaItems.length }}</div>
                            </div>

                            <div v-if="mediaItems.length === 0" class="rounded-2xl border border-dashed border-slate-200 p-6 text-center text-sm text-slate-500">
                                Пока нет загруженных файлов.
                            </div>

                            <div v-for="item in mediaItems" :key="item.id" class="rounded-2xl border border-slate-200 p-4">
                                <div class="flex flex-col gap-4 md:flex-row">
                                    <div class="h-28 w-28 overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                                        <img
                                            v-if="item.url && item.mime && item.mime.startsWith('image/')"
                                            :src="item.url"
                                            class="h-full w-full object-cover"
                                            alt=""
                                        />
                                        <div v-else class="flex h-full w-full items-center justify-center text-xs text-slate-400">
                                            Нет превью
                                        </div>
                                    </div>

                                    <div class="flex-1 space-y-3">
                                        <div class="flex items-center justify-between text-sm text-slate-600">
                                            <span>#{{ item.id }}</span>
                                            <span v-if="item.deleted_at" class="rounded-full bg-rose-100 px-2 py-1 text-xs text-rose-700">Удалено</span>
                                        </div>

                                        <div class="grid gap-2 md:grid-cols-3">
                                            <input
                                                v-model="item._editTitle"
                                                type="text"
                                                placeholder="Название"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                            />
                                            <input
                                                v-model="item._editDescription"
                                                type="text"
                                                placeholder="Описание"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                            />
                                            <input
                                                v-model="item._editCollection"
                                                type="text"
                                                placeholder="Коллекция"
                                                class="rounded-xl border border-slate-200 px-3 py-2 text-sm"
                                            />
                                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                                <input
                                                    v-model="item._editIsAvatar"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                                />
                                                Аватар
                                            </label>
                                            <label class="flex items-center gap-2 text-sm text-slate-600">
                                                <input
                                                    v-model="item._editIsFeatured"
                                                    type="checkbox"
                                                    class="h-4 w-4 rounded border-slate-300 text-emerald-600"
                                                />
                                                Избранное
                                            </label>
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2 text-sm">
                                            <button
                                                class="rounded-xl bg-emerald-600 px-4 py-2 text-white transition hover:bg-emerald-700"
                                                :disabled="item._saving"
                                                @click="saveMeta(item)"
                                            >
                                                Сохранить
                                            </button>

                                            <button
                                                v-if="!item.deleted_at"
                                                class="rounded-xl bg-amber-500 px-4 py-2 text-white transition hover:bg-amber-600"
                                                :disabled="item._working"
                                                @click="softDelete(item)"
                                            >
                                                Удалить
                                            </button>

                                            <button
                                                v-else
                                                class="rounded-xl bg-blue-500 px-4 py-2 text-white transition hover:bg-blue-600"
                                                :disabled="item._working"
                                                @click="restoreItem(item)"
                                            >
                                                Восстановить
                                            </button>

                                            <button
                                                class="rounded-xl bg-rose-600 px-4 py-2 text-white transition hover:bg-rose-700"
                                                :disabled="item._working"
                                                @click="forceDelete(item)"
                                            >
                                                Удалить навсегда
                                            </button>

                                            <span v-if="item._message" class="text-xs text-slate-500">
                                                {{ item._message }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>

        <MainFooter :app-name="appName" />
    </div>
</template>
