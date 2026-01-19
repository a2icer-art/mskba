<script setup>
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    embedded: {
        type: Boolean,
        default: false,
    },
    prefill: {
        type: Object,
        default: () => ({}),
    },
    canBookFallback: {
        type: Boolean,
        default: false,
    },
    hideSubmit: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['close', 'loaded', 'bookingConflict']);

const createForm = useForm({
    event_type_id: '',
    title: '',
    venue_id: '',
    date: '',
    starts_time: '',
    ends_time: '',
    starts_at: '',
    ends_at: '',
});

const venueQuery = ref('');
const venueSuggestions = ref([]);
const venueSuggestLoading = ref(false);
const venueSuggestError = ref('');
let venueSuggestTimer = null;
let venueSuggestRequestId = 0;

const modalLoading = ref(false);
const modalError = ref('');
const eventTypes = ref([]);
const canBook = ref(false);
const typeSelectRef = ref(null);

const gameTypeCodes = new Set(['game', 'training', 'game_training']);
const selectedType = computed(() => {
    if (!createForm.event_type_id) {
        return null;
    }
    return eventTypes.value.find((type) => String(type.id) === String(createForm.event_type_id)) || null;
});
const isGameTypeSelected = computed(() => selectedType.value && gameTypeCodes.has(selectedType.value.code));
const canSelectVenue = computed(() => (canBook.value || props.canBookFallback) && isGameTypeSelected.value);
const hasPrefilledVenue = computed(() => Boolean(props.prefill?.venue));

const combineDateTime = (date, time) => {
    if (!date || !time) {
        return '';
    }
    return `${date}T${time}`;
};

const loadModalData = async () => {
    modalLoading.value = true;
    modalError.value = '';
    if (props.prefill?.date) {
        createForm.date = props.prefill.date;
    }
    const params = new URLSearchParams();
    if (props.prefill?.venue) {
        params.set('venue', props.prefill.venue);
    }
    if (props.prefill?.date) {
        params.set('date', props.prefill.date);
    }
    if (props.prefill?.starts_time) {
        params.set('starts_time', props.prefill.starts_time);
    }
    if (props.prefill?.ends_time) {
        params.set('ends_time', props.prefill.ends_time);
    }
    if (props.embedded) {
        params.set('context', 'embedded');
    }

    try {
        const response = await fetch(`/events/create-modal?${params.toString()}`);
        if (!response.ok) {
            throw new Error('modal_failed');
        }
        const data = await response.json();
        eventTypes.value = data?.eventTypes ?? [];
        canBook.value = Boolean(data?.canBook);

        const prefill = data?.prefill ?? {};
        createForm.event_type_id = '';
        createForm.title = '';
        createForm.venue_id = prefill?.venue?.id ?? '';
        venueQuery.value = prefill?.venue?.label ?? '';
        createForm.date = prefill?.date ?? '';
        createForm.starts_time = prefill?.starts_time ?? '';
        createForm.ends_time = prefill?.ends_time ?? '';
    } catch (error) {
        modalError.value = 'Не удалось загрузить форму бронирования.';
    } finally {
        modalLoading.value = false;
        if (props.embedded) {
            await nextTick();
            emit('loaded');
        }
    }
};

const resetState = () => {
    createForm.reset('event_type_id', 'title', 'venue_id', 'date', 'starts_time', 'ends_time', 'starts_at', 'ends_at');
    createForm.clearErrors();
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
    modalError.value = '';
};

watch(
    () => props.isOpen || props.embedded,
    (value) => {
        if (value) {
            createForm.clearErrors();
            loadModalData();
        } else {
            resetState();
        }
    }
);

watch(
    () => props.prefill,
    () => {
        if (props.embedded) {
            loadModalData();
        }
    },
    { deep: true }
);

onMounted(() => {
    if (props.isOpen || props.embedded) {
        loadModalData();
    }
});

const closeCreate = () => {
    emit('close');
};

const submitCreate = () => {
    if (props.embedded && hasPrefilledVenue.value && !createForm.venue_id) {
        createForm.setError('venue_id', 'Площадка не выбрана.');
        return;
    }
    createForm.starts_at = combineDateTime(createForm.date, createForm.starts_time);
    createForm.ends_at = combineDateTime(createForm.date, createForm.ends_time);
    createForm.post('/events', {
        preserveScroll: true,
        onSuccess: closeCreate,
        onError: (errors) => {
            const message = String(errors?.starts_at || errors?.ends_at || '');
            if (props.embedded && message.includes('занят')) {
                emit('bookingConflict');
            }
        },
    });
};

const isCreateDisabled = computed(() => {
    if (createForm.processing || modalLoading.value) {
        return true;
    }
    if (props.embedded && hasPrefilledVenue.value && !createForm.venue_id) {
        return true;
    }
    return !createForm.event_type_id || !createForm.date || !createForm.starts_time || !createForm.ends_time;
});

const hasPrefilledDate = computed(() => Boolean(props.prefill?.date));

defineExpose({
    submit: submitCreate,
    isDisabled: isCreateDisabled,
    focusType: async () => {
        await nextTick();
        typeSelectRef.value?.focus();
    },
});

const handleTypeChange = () => {
    if (!isGameTypeSelected.value && !hasPrefilledVenue.value) {
        createForm.venue_id = '';
        venueQuery.value = '';
        venueSuggestions.value = [];
        venueSuggestError.value = '';
    }
};

const scheduleVenueSuggestions = (value) => {
    createForm.venue_id = '';
    venueSuggestError.value = '';
    if (venueSuggestTimer) {
        clearTimeout(venueSuggestTimer);
    }
    if (!value || value.trim().length < 2) {
        venueSuggestLoading.value = false;
        venueSuggestions.value = [];
        return;
    }
    venueSuggestTimer = setTimeout(() => {
        fetchVenueSuggestions(value.trim());
    }, 250);
};

const fetchVenueSuggestions = async (query) => {
    const requestId = ++venueSuggestRequestId;
    venueSuggestLoading.value = true;
    try {
        const response = await fetch(`/integrations/venue-suggest?query=${encodeURIComponent(query)}`);
        if (!response.ok) {
            throw new Error('suggest_failed');
        }
        const data = await response.json();
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = data?.suggestions ?? [];
    } catch (error) {
        if (requestId !== venueSuggestRequestId) {
            return;
        }
        venueSuggestions.value = [];
        venueSuggestError.value = 'Не удалось получить подсказки площадок.';
    } finally {
        if (requestId === venueSuggestRequestId) {
            venueSuggestLoading.value = false;
        }
    }
};

const applyVenueSuggestion = (suggestion) => {
    createForm.venue_id = suggestion.id;
    venueQuery.value = suggestion.label || suggestion.name || '';
    venueSuggestions.value = [];
};

const clearVenueSelection = () => {
    createForm.venue_id = '';
    venueQuery.value = '';
    venueSuggestions.value = [];
    venueSuggestError.value = '';
};
</script>

<template>
    <div v-if="embedded">
        <form :class="{ loading: createForm.processing }" @submit.prevent="submitCreate">
            <div v-if="modalError" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                {{ modalError }}
            </div>
            <div v-else class="grid gap-3" :class="{ loading: modalLoading }">
                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                    Тип события
                    <select
                        v-model="createForm.event_type_id"
                        ref="typeSelectRef"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        @change="handleTypeChange"
                    >
                        <option value="">Выберите тип</option>
                        <option v-for="type in eventTypes" :key="type.id" :value="type.id">
                            {{ type.label }}
                        </option>
                    </select>
                </label>
                <div v-if="createForm.errors.event_type_id" class="text-xs text-rose-700">
                    {{ createForm.errors.event_type_id }}
                </div>

                <input v-if="hasPrefilledVenue" v-model="createForm.venue_id" type="hidden" />
                <div v-else-if="canSelectVenue" class="relative">
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Площадка (опционально)
                        <input
                            v-model="venueQuery"
                            class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            :class="{ 'is-loading': venueSuggestLoading }"
                            type="text"
                            placeholder="Начните вводить название, метро или адрес"
                            @input="scheduleVenueSuggestions($event.target.value)"
                        />
                    </label>
                    <div v-if="venueSuggestError" class="text-xs text-rose-700">
                        {{ venueSuggestError }}
                    </div>
                    <div
                        v-else-if="!venueSuggestLoading && venueSuggestions.length"
                        class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                    >
                        <button
                            v-for="(suggestion, index) in venueSuggestions"
                            :key="`${suggestion.id}-${index}`"
                            class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                            type="button"
                            @click="applyVenueSuggestion(suggestion)"
                        >
                            {{ suggestion.label }}
                        </button>
                    </div>
                    <div v-if="createForm.venue_id" class="flex items-center justify-between text-xs text-slate-500">
                        <span>Площадка выбрана.</span>
                        <button
                            class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                            type="button"
                            @click="clearVenueSelection"
                        >
                            Очистить
                        </button>
                    </div>
                    <div v-if="createForm.errors.venue_id" class="text-xs text-rose-700">
                        {{ createForm.errors.venue_id }}
                    </div>
                </div>

                <input v-if="hasPrefilledDate" v-model="createForm.date" type="hidden" />
                <label v-else class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                    Дата
                    <input
                        v-model="createForm.date"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        type="date"
                    />
                </label>
                <div class="grid gap-3 md:grid-cols-2">
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Начало
                        <input
                            v-model="createForm.starts_time"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="time"
                        />
                    </label>
                    <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                        Окончание
                        <input
                            v-model="createForm.ends_time"
                            class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                            type="time"
                        />
                    </label>
                </div>
                <div v-if="createForm.errors.starts_at || createForm.errors.ends_at" class="text-xs text-rose-700">
                    {{ createForm.errors.starts_at || createForm.errors.ends_at }}
                </div>

                <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                    Название (опционально)
                    <input
                        v-model="createForm.title"
                        class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                        type="text"
                    />
                </label>
                <div v-if="createForm.errors.title" class="text-xs text-rose-700">
                    {{ createForm.errors.title }}
                </div>
            </div>
            <div v-if="!hideSubmit" class="mt-4 flex flex-wrap justify-end gap-3">
                <button
                    class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                    type="submit"
                    :disabled="isCreateDisabled"
                >
                    Создать
                </button>
            </div>
        </form>
    </div>

    <div v-else-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 px-4">
        <div class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white shadow-xl">
            <form :class="{ loading: createForm.processing }" @submit.prevent="submitCreate">
                <div class="popup-header flex items-center justify-between border-b border-slate-200/80 px-6 py-4">
                    <h2 class="text-lg font-semibold text-slate-900">Новое событие</h2>
                    <button
                        class="rounded-full border border-slate-200 px-2.5 py-1 text-sm text-slate-500 transition hover:border-slate-300 hover:text-slate-700"
                        type="button"
                        aria-label="Закрыть"
                        @click="closeCreate"
                    >
                        x
                    </button>
                </div>
                <div class="popup-body max-h-[500px] overflow-y-auto px-6 pt-4">
                    <div v-if="modalError" class="rounded-2xl border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700">
                        {{ modalError }}
                    </div>
                    <div v-else class="grid gap-3" :class="{ loading: modalLoading }">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Название (опционально)
                            <input
                                v-model="createForm.title"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="text"
                            />
                        </label>
                        <div v-if="createForm.errors.title" class="text-xs text-rose-700">
                            {{ createForm.errors.title }}
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Тип события
                            <select
                                v-model="createForm.event_type_id"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                @change="handleTypeChange"
                            >
                                <option value="">Выберите тип</option>
                                <option v-for="type in eventTypes" :key="type.id" :value="type.id">
                                    {{ type.label }}
                                </option>
                            </select>
                        </label>
                        <div v-if="createForm.errors.event_type_id" class="text-xs text-rose-700">
                            {{ createForm.errors.event_type_id }}
                        </div>

                        <div v-if="canSelectVenue" class="relative">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Площадка (опционально)
                                <input
                                    v-model="venueQuery"
                                    class="input-predictive rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    :class="{ 'is-loading': venueSuggestLoading }"
                                    type="text"
                                    placeholder="Начните вводить название, метро или адрес"
                                    @input="scheduleVenueSuggestions($event.target.value)"
                                />
                            </label>
                            <div v-if="venueSuggestError" class="text-xs text-rose-700">
                                {{ venueSuggestError }}
                            </div>
                            <div
                                v-else-if="!venueSuggestLoading && venueSuggestions.length"
                                class="absolute left-0 right-0 z-10 mt-2 w-full rounded-2xl border border-slate-200 bg-white text-sm text-slate-700"
                            >
                                <button
                                    v-for="(suggestion, index) in venueSuggestions"
                                    :key="`${suggestion.id}-${index}`"
                                    class="block w-full border-b border-slate-100 px-3 py-2 text-left last:border-b-0 hover:bg-slate-50"
                                    type="button"
                                    @click="applyVenueSuggestion(suggestion)"
                                >
                                    {{ suggestion.label }}
                                </button>
                            </div>
                            <div v-if="createForm.venue_id" class="flex items-center justify-between text-xs text-slate-500">
                                <span>Площадка выбрана.</span>
                                <button
                                    class="text-xs font-semibold text-slate-600 transition hover:text-slate-900"
                                    type="button"
                                    @click="clearVenueSelection"
                                >
                                    Очистить
                                </button>
                            </div>
                            <div v-if="createForm.errors.venue_id" class="text-xs text-rose-700">
                                {{ createForm.errors.venue_id }}
                            </div>
                        </div>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            Дата
                            <input
                                v-model="createForm.date"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                type="date"
                            />
                        </label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Начало
                                <input
                                    v-model="createForm.starts_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                            <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                                Окончание
                                <input
                                    v-model="createForm.ends_time"
                                    class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                    type="time"
                                />
                            </label>
                        </div>
                        <div v-if="createForm.errors.starts_at || createForm.errors.ends_at" class="text-xs text-rose-700">
                            {{ createForm.errors.starts_at || createForm.errors.ends_at }}
                        </div>
                    </div>
                </div>
                <div class="popup-footer flex flex-wrap justify-end gap-3 border-t border-slate-200/80 px-6 py-4">
                    <button
                        class="rounded-full border border-slate-200 px-4 py-2 text-sm text-slate-600 transition hover:-translate-y-0.5 hover:border-slate-300"
                        type="button"
                        :disabled="createForm.processing"
                        @click="closeCreate"
                    >
                        Закрыть
                    </button>
                    <button
                        class="rounded-full border border-emerald-600 bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition hover:-translate-y-0.5 hover:bg-emerald-700 disabled:cursor-not-allowed disabled:border-slate-300 disabled:bg-slate-200 disabled:text-slate-500 disabled:hover:translate-y-0"
                        type="submit"
                        :disabled="isCreateDisabled"
                    >
                        Создать
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>
