<script setup>
import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import MainFooter from '../Components/MainFooter.vue';
import MainHeader from '../Components/MainHeader.vue';
import MainSidebar from '../Components/MainSidebar.vue';

const props = defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
    halls: {
        type: Array,
        default: () => [],
    },
    navigation: {
        type: Object,
        default: () => ({ title: '?????????', items: [] }),
    },
});

const page = usePage();
const isAuthenticated = computed(() => !!page.props.auth?.user);
const loginLabel = computed(() => page.props.auth?.user?.login || '');

const typeFilter = ref('');
const addressFilter = ref('');
const metroFilter = ref('');
const sortBy = ref('name_asc');
const groupByType = ref(false);
const pageIndex = ref(1);
const perPage = 6;

const metroOptions = [
    '',
    '????????',
    '??????????',
    '??????????',
    '????????? ????',
];

const typeOptions = computed(() => {
    const map = new Map();
    props.halls.forEach((hall) => {
        if (hall.type?.alias) {
            map.set(hall.type.alias, hall.type.name || hall.type.alias);
        }
    });
    return Array.from(map.entries()).map(([alias, name]) => ({ alias, name }));
});

const normalized = (value) => (value ?? '').toString().toLowerCase();

const filtered = computed(() => {
    const addressNeedle = normalized(addressFilter.value);
    const metroNeedle = normalized(metroFilter.value);

    return props.halls.filter((hall) => {
        if (typeFilter.value && hall.type?.alias !== typeFilter.value) {
            return false;
        }

        if (addressNeedle && !normalized(hall.address).includes(addressNeedle)) {
            return false;
        }

        if (metroNeedle && !normalized(hall.address).includes(metroNeedle)) {
            return false;
        }

        return true;
    });
});

const sorted = computed(() => {
    const list = [...filtered.value];

    list.sort((a, b) => {
        const nameA = normalized(a.name);
        const nameB = normalized(b.name);
        const dateA = a.created_at ? Date.parse(a.created_at) : 0;
        const dateB = b.created_at ? Date.parse(b.created_at) : 0;

        switch (sortBy.value) {
            case 'name_desc':
                return nameB.localeCompare(nameA);
            case 'created_asc':
                return dateA - dateB;
            case 'created_desc':
                return dateB - dateA;
            default:
                return nameA.localeCompare(nameB);
        }
    });

    return list;
});

const totalPages = computed(() => Math.max(1, Math.ceil(sorted.value.length / perPage)));

watch([typeFilter, addressFilter, metroFilter, sortBy, groupByType], () => {
    pageIndex.value = 1;
});

watch(totalPages, (value) => {
    if (pageIndex.value > value) {
        pageIndex.value = value;
    }
});

const paged = computed(() => {
    const start = (pageIndex.value - 1) * perPage;
    return sorted.value.slice(start, start + perPage);
});

const grouped = computed(() => {
    if (!groupByType.value) {
        return [{ name: '??? ????????', items: paged.value }];
    }

    const groups = new Map();
    paged.value.forEach((hall) => {
        const key = hall.type?.name || '??? ????';
        if (!groups.has(key)) {
            groups.set(key, []);
        }
        groups.get(key).push(hall);
    });

    return Array.from(groups.entries()).map(([name, items]) => ({ name, items }));
});

const syncFromQuery = () => {
    const parts = page.url.split('?');
    const params = new URLSearchParams(parts[1] || '');
    typeFilter.value = params.get('type') || '';
};

syncFromQuery();
watch(
    () => page.url,
    () => syncFromQuery()
);
</script>

<template>
    <main class="relative min-h-screen overflow-hidden bg-[#f7f1e6] text-slate-900">
        <div class="pointer-events-none absolute -left-28 top-12 h-72 w-72 rounded-full bg-emerald-200/70 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-amber-200/70 blur-3xl"></div>

        <div class="relative mx-auto flex max-w-6xl flex-col gap-8 px-6 py-8">
            <MainHeader
                :app-name="appName"
                :is-authenticated="isAuthenticated"
                :login-label="loginLabel"
            />

            <section class="grid gap-6 lg:grid-cols-[240px_1fr]">
                <MainSidebar :title="navigation.title" :items="navigation.items" />

                <div class="rounded-3xl border border-slate-200/80 bg-white/90 p-6 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-4">
                        <div>
                            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">????????</p>
                            <h1 class="mt-2 text-3xl font-semibold text-slate-900">?????? ????????</h1>
                            <p class="mt-3 max-w-2xl text-sm text-slate-600">
                                ???????? ??? ????????, ????? ??? ?????, ????? ?????? ????? ?????????? ?????.
                            </p>
                        </div>
                    </div>

                    <div class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            ???
                            <select v-model="typeFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <option value="">??? ????</option>
                                <option v-for="type in typeOptions" :key="type.alias" :value="type.alias">
                                    {{ type.name }}
                                </option>
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            ?????
                            <input
                                v-model="addressFilter"
                                class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700"
                                placeholder="????????, ????????"
                                type="text"
                            />
                        </label>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            ?????
                            <select v-model="metroFilter" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <option v-for="metro in metroOptions" :key="metro" :value="metro">
                                    {{ metro || '?????' }}
                                </option>
                            </select>
                        </label>

                        <label class="flex flex-col gap-1 text-xs uppercase tracking-[0.15em] text-slate-500">
                            ??????????
                            <select v-model="sortBy" class="rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700">
                                <option value="name_asc">???????? A-Z</option>
                                <option value="name_desc">???????? Z-A</option>
                                <option value="created_desc">???? ??????????: ?????</option>
                                <option value="created_asc">???? ??????????: ??????</option>
                            </select>
                        </label>

                        <label class="flex items-center gap-2 text-sm text-slate-600">
                            <input v-model="groupByType" class="h-4 w-4 rounded border-slate-300" type="checkbox" />
                            ???????????? ?? ????
                        </label>
                    </div>

                    <div v-if="paged.length" class="mt-6 grid gap-4">
                        <div v-for="group in grouped" :key="group.name" class="space-y-3">
                            <h2 v-if="groupByType" class="text-sm font-semibold uppercase tracking-[0.2em] text-slate-500">
                                {{ group.name }}
                            </h2>

                            <article
                                v-for="hall in group.items"
                                :key="hall.id"
                                class="rounded-2xl border border-slate-200 bg-slate-50 p-4"
                            >
                                <div class="flex flex-wrap items-start justify-between gap-4">
                                    <div>
                                        <h3 class="text-lg font-semibold text-slate-900">{{ hall.name }}</h3>
                                        <p class="mt-1 text-sm text-slate-600">
                                            {{ hall.type?.name || '??? ?? ??????' }}
                                        </p>
                                        <p v-if="hall.address" class="mt-2 text-sm text-slate-600">
                                            {{ hall.address }}
                                        </p>
                                    </div>
                                    <span class="rounded-full bg-white px-3 py-1 text-xs font-medium uppercase text-slate-500">
                                        {{ hall.alias }}
                                    </span>
                                </div>
                            </article>
                        </div>
                    </div>

                    <div v-else class="mt-6 rounded-2xl border border-dashed border-slate-200 bg-white p-6 text-sm text-slate-600">
                        ?? ??????? ???????? ???????? ?? ???????.
                    </div>

                    <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-sm text-slate-600">
                        <div>???????? {{ pageIndex }} ?? {{ totalPages }}</div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                class="rounded-full border border-slate-300 px-3 py-1 text-sm transition disabled:opacity-40"
                                :disabled="pageIndex === 1"
                                type="button"
                                @click="pageIndex = Math.max(1, pageIndex - 1)"
                            >
                                ?????
                            </button>
                            <button
                                v-for="pageNumber in totalPages"
                                :key="pageNumber"
                                class="h-9 w-9 rounded-full border text-sm transition"
                                :class="
                                    pageNumber === pageIndex
                                        ? 'border-slate-900 bg-slate-900 text-white'
                                        : 'border-slate-300 text-slate-700 hover:border-slate-400'
                                "
                                type="button"
                                @click="pageIndex = pageNumber"
                            >
                                {{ pageNumber }}
                            </button>
                            <button
                                class="rounded-full border border-slate-300 px-3 py-1 text-sm transition disabled:opacity-40"
                                :disabled="pageIndex === totalPages"
                                type="button"
                                @click="pageIndex = Math.min(totalPages, pageIndex + 1)"
                            >
                                ??????
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <MainFooter />
        </div>
    </main>
</template>
