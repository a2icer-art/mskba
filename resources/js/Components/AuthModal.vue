<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    appName: {
        type: String,
        required: true,
    },
});

const emit = defineEmits(['close']);
const page = usePage();
const form = useForm({
    login: '',
    password: '',
    remember: false,
});

const errorMessage = computed(() => {
    return form.errors.login || page.props.errors?.login;
});

const submit = () => {
    form.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('password');
            emit('close');
        },
    });
};
</script>

<template>
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center px-4 py-10">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="emit('close')"></div>
        <div class="relative w-full max-w-md rounded-3xl border border-slate-200 bg-white p-6 shadow-xl">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs uppercase tracking-[0.2em] text-slate-500">Авторизация</p>
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">Войти в {{ appName }}</h2>
                </div>
                <button class="rounded-full border border-slate-200 px-3 py-1 text-sm text-slate-500" @click="emit('close')">
                    Закрыть
                </button>
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Логин</label>
                    <input
                        v-model="form.login"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="text"
                        autocomplete="username"
                        required
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Пароль</label>
                    <input
                        v-model="form.password"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" class="h-4 w-4 rounded border-slate-300" type="checkbox" />
                    Запомнить меня
                </label>

                <div v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ errorMessage }}
                </div>

                <button
                    class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                    type="submit"
                    :disabled="form.processing"
                >
                    Войти
                </button>
            </form>
        </div>
    </div>
</template>
