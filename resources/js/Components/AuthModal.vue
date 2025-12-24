<script setup>
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps({
    isOpen: {
        type: Boolean,
        required: true,
    },
    appName: {
        type: String,
        required: true,
    },
    participantRoles: {
        type: Array,
        default: () => [],
    },
    initialMode: {
        type: String,
        default: 'login',
    },
});

const emit = defineEmits(['close']);
const page = usePage();
const mode = ref(props.initialMode);

watch(
    () => props.initialMode,
    (value) => {
        mode.value = value;
    }
);

const loginForm = useForm({
    login: '',
    password: '',
    remember: false,
});

const registerForm = useForm({
    login: '',
    email: '',
    password: '',
    participant_role_id: '',
});

const loginError = computed(() => {
    return loginForm.errors.login || loginForm.errors.password || page.props.errors?.login;
});

const registerError = computed(() => {
    return (
        registerForm.errors.login ||
        registerForm.errors.email ||
        registerForm.errors.password ||
        registerForm.errors.participant_role_id
    );
});

const submitLogin = () => {
    loginForm.post('/login', {
        preserveScroll: true,
        onSuccess: () => {
            loginForm.reset('password');
            emit('close');
        },
    });
};

const submitRegister = () => {
    registerForm.post('/register', {
        preserveScroll: true,
        onSuccess: () => {
            registerForm.reset('login', 'email', 'password', 'participant_role_id');
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
                    <h2 class="mt-2 text-2xl font-semibold text-slate-900">
                        {{ mode === 'login' ? 'Войти' : 'Регистрация' }} в {{ appName }}
                    </h2>
                </div>
                <button class="rounded-full border border-slate-200 px-3 py-1 text-sm text-slate-500" @click="emit('close')">
                    Закрыть
                </button>
            </div>

            <div class="mt-6 inline-flex rounded-full border border-slate-200 bg-slate-50 p-1 text-xs uppercase tracking-[0.2em] text-slate-500">
                <button
                    class="rounded-full px-4 py-2 transition"
                    :class="mode === 'login' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-700'"
                    type="button"
                    @click="mode = 'login'"
                >
                    Войти
                </button>
                <button
                    class="rounded-full px-4 py-2 transition"
                    :class="mode === 'register' ? 'bg-white text-slate-900 shadow-sm' : 'hover:text-slate-700'"
                    type="button"
                    @click="mode = 'register'"
                >
                    Регистрация
                </button>
            </div>

            <form v-if="mode === 'login'" class="mt-6 space-y-4" @submit.prevent="submitLogin">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Логин</label>
                    <input
                        v-model="loginForm.login"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="text"
                        autocomplete="username"
                        required
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Пароль</label>
                    <input
                        v-model="loginForm.password"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="password"
                        autocomplete="current-password"
                        required
                    />
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="loginForm.remember" class="h-4 w-4 rounded border-slate-300" type="checkbox" />
                    Запомнить меня
                </label>

                <div v-if="loginError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ loginError }}
                </div>

                <button
                    class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                    type="submit"
                    :disabled="loginForm.processing"
                >
                    Войти
                </button>
            </form>

            <form v-else class="mt-6 space-y-4" @submit.prevent="submitRegister">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Логин</label>
                    <input
                        v-model="registerForm.login"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="text"
                        autocomplete="username"
                        required
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Email</label>
                    <input
                        v-model="registerForm.email"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="email"
                        autocomplete="email"
                        required
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Пароль</label>
                    <input
                        v-model="registerForm.password"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                        type="password"
                        autocomplete="new-password"
                        required
                    />
                    <p class="mt-2 text-xs text-slate-500">Минимум 6 символов, буквы и цифры.</p>
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-[0.15em] text-slate-500">Роль</label>
                    <select
                        v-model="registerForm.participant_role_id"
                        class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 focus:border-slate-400 focus:outline-none"
                    >
                        <option value="">Без роли</option>
                        <option v-for="role in participantRoles" :key="role.id" :value="role.id">
                            {{ role.name }}
                        </option>
                    </select>
                </div>

                <div v-if="registerError" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">
                    {{ registerError }}
                </div>

                <button
                    class="w-full rounded-2xl bg-slate-900 px-4 py-3 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-slate-800"
                    type="submit"
                    :disabled="registerForm.processing"
                >
                    Зарегистрироваться
                </button>
            </form>
        </div>
    </div>
</template>
