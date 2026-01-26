<script setup>
import { computed } from 'vue';
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
    defaults: {
        type: Object,
        default: () => ({ lead_time_minutes: 15, min_duration_minutes: 15 }),
    },
    contactDelivery: {
        type: Object,
        default: () => ({ email: { enabled: false, smtp: {} } }),
    },
});

const navigationData = computed(() => props.navigation?.data ?? []);
const hasSidebar = computed(() => (navigationData.value?.length ?? 0) > 0);
const page = usePage();
const actionNotice = computed(() => page.props?.flash?.notice ?? '');
const actionErrors = computed(() => page.props?.errors ?? {});
const settingsFields = [
    'lead_time_minutes',
    'min_duration_minutes',
    'email_enabled',
    'smtp_host',
    'smtp_port',
    'smtp_username',
    'smtp_password',
    'smtp_encryption',
    'smtp_from_address',
    'smtp_from_name',
];
const hasSettingsErrors = computed(() =>
    settingsFields.some((key) => Boolean(actionErrors.value?.[key]))
);
const formErrorNotice = computed(() => {
    if (!hasSettingsErrors.value) {
        return '';
    }
    return 'Не удалось сохранить изменения. Проверьте значения.';
});

const form = useForm({
    lead_time_minutes: props.defaults?.lead_time_minutes ?? 15,
    min_duration_minutes: props.defaults?.min_duration_minutes ?? 15,
    email_enabled: Boolean(props.contactDelivery?.email?.enabled ?? false),
    smtp_host: props.contactDelivery?.email?.smtp?.host ?? '',
    smtp_port: props.contactDelivery?.email?.smtp?.port ?? 587,
    smtp_username: props.contactDelivery?.email?.smtp?.username ?? '',
    smtp_password: '',
    smtp_encryption: props.contactDelivery?.email?.smtp?.encryption ?? 'tls',
    smtp_from_address: props.contactDelivery?.email?.smtp?.from_address ?? '',
    smtp_from_name: props.contactDelivery?.email?.smtp?.from_name ?? props.appName ?? '',
});
const testForm = useForm({
    test_email: '',
    test_body: '',
});

const submit = () => {
    form.patch('/admin/settings', {
        preserveScroll: true,
    });
};
const submitTest = () => {
    testForm.post('/admin/settings/test-email', {
        preserveScroll: true,
        onSuccess: () => {
            testForm.reset('test_email', 'test_body');
        },
    });
};
</script>

<template>
    <div class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>
        <SystemNoticeStack :success="actionNotice" :error="formErrorNotice" />

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
                    <h1 class="text-3xl font-semibold text-slate-900">Настройки</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Дефолтные параметры времени событий.
                    </p>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <form class="space-y-6" @submit.prevent="submit">
                            <div class="grid gap-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Допустимое время до начала события (мин.)
                                </label>
                                <input
                                    v-model="form.lead_time_minutes"
                                    type="number"
                                    min="0"
                                    max="1440"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                />
                                <p v-if="actionErrors.lead_time_minutes" class="text-xs text-rose-700">
                                    {{ actionErrors.lead_time_minutes }}
                                </p>
                            </div>

                            <div class="grid gap-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                    Минимальная длительность события (мин.)
                                </label>
                                <input
                                    v-model="form.min_duration_minutes"
                                    type="number"
                                    min="1"
                                    max="1440"
                                    class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                />
                                <p v-if="actionErrors.min_duration_minutes" class="text-xs text-rose-700">
                                    {{ actionErrors.min_duration_minutes }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="submit"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    :disabled="form.processing"
                                >
                                    Сохранить
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-200 bg-white px-4 py-6">
                        <h2 class="text-lg font-semibold text-slate-900">Отправка сообщений</h2>
                        <p class="mt-2 text-sm text-slate-600">
                            Настройки SMTP используются для отправки писем сайта (например: коды подтверждения и уведомления).
                        </p>

                        <form class="mt-6 space-y-6" @submit.prevent="submit">
                            <label class="flex items-center gap-3 text-sm text-slate-700">
                                <input
                                    v-model="form.email_enabled"
                                    type="checkbox"
                                    class="h-4 w-4 rounded border-slate-300 text-slate-900"
                                />
                                Включить email‑доставку
                            </label>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        SMTP Host
                                    </label>
                                    <input
                                        v-model="form.smtp_host"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="smtp.example.com"
                                    />
                                    <p v-if="actionErrors.smtp_host" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_host }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        SMTP Port
                                    </label>
                                    <input
                                        v-model="form.smtp_port"
                                        type="number"
                                        min="1"
                                        max="65535"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="587"
                                    />
                                    <p v-if="actionErrors.smtp_port" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_port }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        SMTP Логин
                                    </label>
                                    <input
                                        v-model="form.smtp_username"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                    />
                                    <p v-if="actionErrors.smtp_username" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_username }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        SMTP Пароль
                                    </label>
                                    <input
                                        v-model="form.smtp_password"
                                        type="password"
                                        autocomplete="new-password"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="••••••••"
                                    />
                                    <p class="text-xs text-slate-500">
                                        Оставьте пустым, чтобы не менять текущий пароль.
                                    </p>
                                    <p v-if="actionErrors.smtp_password" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_password }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Шифрование
                                    </label>
                                    <select
                                        v-model="form.smtp_encryption"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                    >
                                        <option value="tls">TLS</option>
                                        <option value="ssl">SSL</option>
                                        <option value="none">Без шифрования</option>
                                    </select>
                                    <p v-if="actionErrors.smtp_encryption" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_encryption }}
                                    </p>
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Email отправителя
                                    </label>
                                    <input
                                        v-model="form.smtp_from_address"
                                        type="email"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="no-reply@example.com"
                                    />
                                    <p v-if="actionErrors.smtp_from_address" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_from_address }}
                                    </p>
                                </div>
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Имя отправителя
                                    </label>
                                    <input
                                        v-model="form.smtp_from_name"
                                        type="text"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="MSKBA"
                                    />
                                    <p v-if="actionErrors.smtp_from_name" class="text-xs text-rose-700">
                                        {{ actionErrors.smtp_from_name }}
                                    </p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                <button
                                    type="submit"
                                    class="rounded-full border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                    :disabled="form.processing"
                                >
                                    Сохранить
                                </button>
                            </div>
                        </form>

                        <div class="mt-8 border-t border-slate-200 pt-6">
                            <h3 class="text-base font-semibold text-slate-900">Тестовое письмо</h3>
                            <p class="mt-2 text-sm text-slate-600">
                                Укажите адрес и текст, чтобы проверить SMTP.
                            </p>

                            <form class="mt-4 space-y-4" @submit.prevent="submitTest">
                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Email получателя
                                    </label>
                                    <input
                                        v-model="testForm.test_email"
                                        type="email"
                                        class="w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="test@example.com"
                                    />
                                    <p v-if="actionErrors.test_email" class="text-xs text-rose-700">
                                        {{ actionErrors.test_email }}
                                    </p>
                                </div>

                                <div class="grid gap-2">
                                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">
                                        Текст сообщения
                                    </label>
                                    <textarea
                                        v-model="testForm.test_body"
                                        class="min-h-[120px] w-full rounded-2xl border border-slate-200 px-4 py-3 text-sm text-slate-800 outline-none transition focus:border-slate-400"
                                        placeholder="Проверка SMTP"
                                    ></textarea>
                                    <p v-if="actionErrors.test_body" class="text-xs text-rose-700">
                                        {{ actionErrors.test_body }}
                                    </p>
                                </div>

                                <div class="flex flex-wrap items-center justify-end gap-3">
                                    <button
                                        type="submit"
                                        class="rounded-full border border-slate-900 bg-slate-900 px-5 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                                        :disabled="testForm.processing"
                                    >
                                        Отправить тест
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </main>

            <MainFooter :app-name="appName" />
        </div>
    </div>
</template>
