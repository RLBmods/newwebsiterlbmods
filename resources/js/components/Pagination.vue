<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';

interface Props {
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

defineProps<Props>();
</script>

<template>
    <div class="flex items-center gap-2">
        <template v-for="(link, index) in links" :key="index">
            <Button
                v-if="link.url"
                as-child
                variant="outline"
                class="bg-white/5 border-white/10 h-10 px-4 min-w-[40px]"
                :class="{ 'bg-brand-primary border-brand-primary text-white hover:bg-brand-primary hover:text-white': link.active }"
            >
                <Link :href="link.url" v-html="link.label" />
            </Button>
            <Button
                v-else
                disabled
                variant="outline"
                class="bg-white/5 border-white/10 h-10 px-4 min-w-[40px] opacity-30"
                v-html="link.label"
            />
        </template>
    </div>
</template>
