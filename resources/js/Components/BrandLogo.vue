<script setup>
defineProps({
    src: {
        type: String,
        default: '/images/logo-header.png',
    },
    alt: {
        type: String,
        default: 'MSKBA',
    },
});
</script>

<template>
    <div class="flex h-11 w-11 items-center justify-center">
        <img class="object-contain" :src="src" :alt="alt" />
    </div>
</template>
