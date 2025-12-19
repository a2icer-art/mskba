<script setup>
defineProps({
    appName: {
        type: String,
        default: 'Laravel',
    },
});
</script>

<template>
    <main class="min-h-screen bg-zinc-950 text-zinc-100">
        <div class="mx-auto max-w-4xl px-6 py-16">
            <div class="inline-flex items-center gap-3 rounded-full border border-zinc-800 bg-zinc-900/70 px-5 py-2 text-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                Inertia + Vue is ready
            </div>

            <h1 class="mt-8 text-4xl font-semibold tracking-tight sm:text-5xl">
                {{ appName }} starts with Laravel 12
            </h1>
            <p class="mt-4 max-w-2xl text-lg text-zinc-300">
                Inertia and Vue 3 are wired up. Add pages and components without full reloads.
            </p>

            <div class="mt-10 grid gap-4 sm:grid-cols-2">
                <div class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-6">
                    <p class="text-sm text-zinc-400">Quick start</p>
                    <p class="mt-2 text-lg font-medium">`resources/js/Pages`</p>
                    <p class="mt-2 text-sm text-zinc-400">
                        Add page components and render them through Inertia.
                    </p>
                </div>
                <div class="rounded-2xl border border-zinc-800 bg-zinc-900/60 p-6">
                    <p class="text-sm text-zinc-400">Vite dev server</p>
                    <p class="mt-2 text-lg font-medium">`npm run dev`</p>
                    <p class="mt-2 text-sm text-zinc-400">
                        Fast refresh and hot module reloads.
                    </p>
                </div>
            </div>
        </div>
    </main>
</template>