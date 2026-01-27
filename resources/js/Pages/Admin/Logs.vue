<script setup>
import { computed, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Breadcrumbs from '../../Components/Breadcrumbs.vue';
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
        default: () => ({ title: 'Разделы', data: [] }),
    },
    activeHref: {
        type: String,
        default: '',
    },
    entities: {
        type: Array,
        default: () => [],
    },
    activeEntity: {
        type: Object,
        default: null,
    },
    logs: {
        type: Object,
        default: () => ({ data: [], links: [] }),
    },
    breadcrumbs: {
        type: Array,
        default: () => [],
    },
});

const page = usePage();
const navigationData = computed(() => props.navigation?.data ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const hasEntities = computed(() => props.entities.length > 0);
const hasLogs = computed(() => props.logs?.data?.length > 0);
const actionLabels = {
    created: 'Создание',
    updated: 'Редактирование',
    deleted: 'Удаление',
};
const actionClasses = {
    created: 'text-emerald-600',
    updated: 'text-amber-600',
    deleted: 'text-rose-600',
};
const selectedLog = ref(null);
const logOpen = ref(false);
const exportFormat = ref('json');
const csrfToken = computed(() => document.querySelector('meta[name=\"csrf-token\"]')?.getAttribute('content') ?? '');
const currentEntityKey = computed(() => props.activeEntity?.key || 'all');
const userPermissions = computed(() => page.props.auth?.user?.permissions || []);
const canManageLogs = computed(
    () => userPermissions.value.includes('admin.access') && userPermissions.value.includes('logs.view')
);
const formatFields = (fields) => {
    if (!Array.isArray(fields) || fields.length === 0) {
        return '—';
    }
    return fields.join(', ');
};
const openLog = (log) => {
    selectedLog.value = log;
    logOpen.value = true;
};
const closeLog = () => {
    logOpen.value = false;
    selectedLog.value = null;
};
const formatValue = (value) => {
    if (value === null || value === undefined || value === '') {
        return '—';
    }
    if (typeof value === 'object') {
        return JSON.stringify(value);
    }
    return String(value);
};
const downloadDump = () => {
    if (!canManageLogs.value) {
        return;
    }
    const params = new URLSearchParams({
        format: exportFormat.value,
        entity: currentEntityKey.value,
    });
    window.location.href = `/admin/logs/export?${params.toString()}`;
};
const submitForm = (action, fields = {}) => {
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.style.display = 'none';
    const token = document.createElement('input');
    token.type = 'hidden';
    token.name = '_token';
    token.value = csrfToken.value;
    form.appendChild(token);
    Object.entries(fields).forEach(([key, value]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = key;
        input.value = String(value);
        form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
    form.remove();
};
const deleteLogs = () => {
    if (!canManageLogs.value) {
        return;
    }
    if (!window.confirm('Вы уверены что хотите удалить логи?')) {
        return;
    }
    submitForm('/admin/logs', {
        _method: 'DELETE',
        entity: currentEntityKey.value,
    });
};
const downloadAndDelete = () => {
    if (!canManageLogs.value) {
        return;
    }
    if (!window.confirm('Подтвердите действие \"скачать и удалить\"')) {
        return;
    }
    submitForm('/admin/logs/export-delete', {
        format: exportFormat.value,
        entity: currentEntityKey.value,
    });
};
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

            <main class="grid gap-6" :class="{ 'lg:grid-cols-[240px_1fr]': hasSidebar }">
                <MainSidebar
                    v-if="hasSidebar"
                    :title="navigation.title"
                    :data="navigationData"
                    :active-href="activeHref"
                />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm page-content-wrapper">
                    <Breadcrumbs :items="breadcrumbs" />
                    <h1 class="text-3xl font-semibold text-slate-900">Логи</h1>
                    <p class="mt-4 text-sm text-slate-600">
                        Выберите сущность, чтобы просмотреть журнал изменений.
                    </p>

                    <div v-if="canManageLogs" class="mt-4 flex flex-wrap items-center gap-3">
                        <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-400">Формат</span>
                            <select
                                v-model="exportFormat"
                                class="rounded-full border border-slate-200 bg-white px-3 py-1 text-sm text-slate-700"
                            >
                                <option value="json">JSON</option>
                                <option value="mysql">MySQL</option>
                            </select>
                            <button
                                class="rounded-full border border-slate-200 bg-slate-900 px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                type="button"
                                @click="downloadDump"
                            >
                                Скачать дамп
                            </button>
                        </div>
                        <button
                            class="rounded-full border border-slate-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-700 transition hover:-translate-y-0.5 hover:border-amber-200"
                            type="button"
                            @click="downloadAndDelete"
                        >
                            Скачать и удалить
                        </button>
                        <button
                            class="rounded-full border border-rose-200 bg-rose-50 px-4 py-2 text-sm font-medium text-rose-700 transition hover:-translate-y-0.5 hover:border-rose-300"
                            type="button"
                            @click="deleteLogs"
                        >
                            Удалить логи
                        </button>
                    </div>

                    <div v-if="hasEntities" class="mt-6 flex flex-wrap gap-3">
                        <Link
                            v-for="entity in entities"
                            :key="entity.key"
                            class="rounded-full border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-700 transition hover:-translate-y-0.5 hover:border-slate-300"
                            :class="entity.href === activeHref ? 'border-slate-900 bg-slate-900 text-white' : ''"
                            :href="entity.href"
                        >
                            {{ entity.label }}
                        </Link>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-600">
                        Для вашей роли пока нет доступных журналов.
                    </div>

                    <div v-if="activeEntity" class="mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Выбранная сущность</p>
                        <p class="mt-2 text-lg font-semibold text-slate-900">{{ activeEntity.label }}</p>
                        <div v-if="hasLogs" class="mt-4 space-y-3">
                            <div class="grid grid-cols-[160px_140px_1fr_140px] gap-4 border-b border-slate-200 pb-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                <div>Сущность</div>
                                <div>Действие</div>
                                <div>Поля</div>
                                <div>Дата</div>
                            </div>
                            <div
                                v-for="log in logs.data"
                                :key="log.id"
                                class="grid grid-cols-[160px_140px_1fr_140px] gap-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                                @dblclick="openLog(log)"
                            >
                                <div class="font-semibold text-slate-800">{{ log.entity_label || '—' }}</div>
                                <div
                                    class="font-semibold"
                                    :class="actionClasses[log.action] || 'text-slate-600'"
                                >
                                    {{ actionLabels[log.action] || log.action || '—' }}
                                </div>
                                <div class="text-slate-600">{{ formatFields(log.fields) }}</div>
                                <div>{{ log.created_at || '—' }}</div>
                            </div>
                        </div>
                        <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-sm text-slate-600">
                            Логи пока не найдены.
                        </div>

                        <div v-if="logs.links?.length" class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                            <Link
                                v-for="link in logs.links"
                                :key="link.label"
                                class="rounded-full border border-slate-200 px-3 py-1 text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                                :class="{
                                    'bg-slate-900 text-white': link.active,
                                    'pointer-events-none opacity-50': !link.url,
                                }"
                                :href="link.url || ''"
                                v-html="link.label"
                            />
                        </div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>

        <div v-if="logOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
            <div class="w-full max-w-2xl rounded-3xl border border-slate-200 bg-white shadow-xl">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Детали лога</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeLog"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <p class="text-sm text-slate-600">
                        {{ selectedLog?.entity_label || '—' }} • #{{ selectedLog?.entity_id || '—' }}
                    </p>

                    <div class="mt-4 grid gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Действие</span>
                            <span class="font-semibold text-slate-800">{{ actionLabels[selectedLog?.action] || selectedLog?.action || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Пользователь</span>
                            <span>{{ selectedLog?.actor?.login || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">IP</span>
                            <span>{{ selectedLog?.ip || '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-xs uppercase tracking-[0.15em] text-slate-500">Дата</span>
                            <span>{{ selectedLog?.created_at || '—' }}</span>
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="text-xs uppercase tracking-[0.15em] text-slate-500">Изменения</p>
                        <div v-if="selectedLog?.changes" class="mt-2 space-y-2">
                            <div
                                v-for="field in selectedLog.fields || []"
                                :key="field"
                                class="rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700"
                            >
                                <div class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">{{ field }}</div>
                                <div class="mt-1 grid gap-2 text-sm">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-400">Было</span>
                                        <span class="text-right">{{ formatValue(selectedLog.changes?.before?.[field]) }}</span>
                                    </div>
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="text-xs uppercase tracking-[0.15em] text-slate-400">Стало</span>
                                        <span class="text-right">{{ formatValue(selectedLog.changes?.after?.[field]) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="mt-2 rounded-2xl border border-dashed border-slate-200 bg-white p-4 text-sm text-slate-600">
                            Детали изменений недоступны.
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        @click="closeLog"
                    >
                        Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
