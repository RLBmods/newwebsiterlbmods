<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="relative flex min-h-svh flex-col items-center justify-center gap-6 bg-[#0a0a0a] p-6 md:p-10 overflow-hidden"
    >
        <!-- Background Glow -->
        <div class="absolute top-[-10%] left-[-10%] w-[50%] h-[50%] bg-brand-primary/20 blur-[120px] rounded-full pointer-events-none opacity-50"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-primary/10 blur-[100px] rounded-full pointer-events-none"></div>

        <div class="relative z-10 w-full max-w-sm">
            <div class="flex flex-col gap-8">
                <div class="flex flex-col items-center gap-4">
                    <Link
                        :href="home()"
                        class="flex flex-col items-center gap-2 font-medium"
                    >
                        <div
                            class="mb-2 flex h-12 w-12 items-center justify-center rounded-2xl bg-brand-primary/10 border border-brand-primary/20 shadow-[0_0_20px_rgba(178,0,3,0.2)]"
                        >
                            <AppLogoIcon
                                class="size-7 fill-current text-brand-primary"
                            />
                        </div>
                        <span class="sr-only">{{ title }}</span>
                    </Link>
                    <div class="space-y-1.5 text-center">
                        <h1 class="text-2xl font-bold tracking-tight text-white">{{ title }}</h1>
                        <p class="text-center text-sm text-zinc-500">
                            {{ description }}
                        </p>
                    </div>
                </div>
                <slot />
            </div>
        </div>
    </div>
</template>
