<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

defineOptions({ inheritAttrs: false });

const page = usePage();
</script>

<template>
    <div v-bind="$attrs" class="flex items-center gap-2">
        <div class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground shrink-0">
            <img
                v-if="page.props.settings?.site_logo"
                :src="page.props.settings.site_logo"
                class="w-full h-full object-contain"
                alt="Logo"
            />
            <AppLogoIcon v-else class="size-5 fill-current text-white dark:text-black" />
        </div>
        <div class="ml-1 grid flex-1 text-left text-sm">
            <span class="mb-0.5 truncate leading-tight font-semibold">{{ page.props.settings?.site_name }}</span>
        </div>
    </div>
</template>
