<script setup>
import { ref, watch } from 'vue';

const props = defineProps({
    success: {
        type: [String, Array],
        default: '',
    },
    error: {
        type: [String, Array],
        default: '',
    },
    info: {
        type: [String, Array],
        default: '',
    },
});

const notices = ref([]);

const normalizeMessages = (value) => {
    if (!value) {
        return [];
    }
    if (Array.isArray(value)) {
        return value.filter(Boolean).map((item) => String(item));
    }
    return [String(value)];
};

const removeNotice = (id) => {
    notices.value = notices.value.filter((notice) => notice.id !== id);
};

const pushNotice = (type, message) => {
    const id = `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    notices.value.push({ id, type, message });
    window.setTimeout(() => removeNotice(id), 5000);
};

const bindNotice = (getter, type) => {
    watch(
        getter,
        (value) => {
            normalizeMessages(value).forEach((message) => pushNotice(type, message));
        },
        { immediate: true }
    );
};

bindNotice(() => props.success, 'success');
bindNotice(() => props.error, 'error');
bindNotice(() => props.info, 'info');
</script>

<template>
    <div
        class="pointer-events-none fixed right-6 z-50 flex flex-col gap-3"
        :style="{ top: 'calc(var(--app-header-height, 0px) + 15px)' }"
    >
        <div
            v-for="notice in notices"
            :key="notice.id"
            class="pointer-events-auto flex min-w-[280px] max-w-[640px] items-start gap-4 rounded-2xl border px-4 py-3 text-sm shadow-lg"
            :class="{
                'border-emerald-200 bg-emerald-50 text-emerald-800': notice.type === 'success',
                'border-rose-200 bg-rose-50 text-rose-800': notice.type === 'error',
                'border-slate-200 bg-white text-slate-700': notice.type === 'info',
            }"
        >
            <span class="flex-1">{{ notice.message }}</span>
            <button
                type="button"
                class="rounded-full text-sm text-slate-500 transition hover:text-slate-700"
                aria-label="Закрыть уведомление"
                @click="removeNotice(notice.id)"
            >
                ×
            </button>
        </div>
    </div>
</template>
