<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { 
    CheckCircle2, 
    AlertCircle, 
    Clock, 
    ShieldCheck, 
    Monitor, 
    Globe,
    Zap,
    ShieldAlert,
    RefreshCw
} from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';

const props = defineProps<{
    statuses: any[];
}>();

const getStatusIcon = (status: string) => {
    switch (status.toLowerCase()) {
        case 'undetected': return CheckCircle2;
        case 'updated': return RefreshCw;
        case 'testing': return Clock;
        case 'maintenance': return ShieldAlert;
        default: return AlertCircle;
    }
};

const getStatusBadge = (status: string) => {
    switch (status.toLowerCase()) {
        case 'undetected': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30';
        case 'updated': return 'bg-blue-500/10 text-blue-400 border-blue-500/30';
        case 'testing': return 'bg-amber-500/10 text-amber-400 border-amber-500/30';
        case 'maintenance': return 'bg-red-500/10 text-red-400 border-red-500/30';
        default: return 'bg-muted/10 text-muted-foreground border-white/10';
    }
};
</script>

<template>
    <Head title="System Status - RLBMODS" />

    <FrontLayout>
        <section class="py-20 px-6">
            <div class="max-w-5xl mx-auto">
                
                <!-- Status Header -->
                <div class="text-center mb-24">
                    <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 font-black uppercase text-[10px] tracking-[0.3em] mb-8 shadow-2xl shadow-emerald-500/20">
                        <div class="size-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        All Systems Operational
                    </div>
                    
                    <h1 class="text-6xl md:text-7xl font-black text-white tracking-tighter uppercase italic mb-6">Real-Time <span class="text-brand-primary">Status</span></h1>
                    <p class="text-muted-foreground font-medium max-w-xl mx-auto">Our team monitors all software 24/7. Check here for the latest updates on detection and maintenance status.</p>
                </div>

                <!-- Status Grid -->
                <div class="space-y-12">
                    <div v-for="game in statuses" :key="game.game" class="group relative bg-[#0A0A0A] border border-white/5 rounded-[4rem] p-10 transition-all hover:bg-white/2 hover:border-white/10 shadow-2xl">
                        
                        <!-- Game Header -->
                        <div class="flex items-center gap-6 mb-10">
                            <div class="size-16 rounded-3xl bg-brand-primary/10 border border-brand-primary/20 flex items-center justify-center text-brand-primary shadow-xl group-hover:scale-110 transition-transform">
                                <Monitor v-if="game.game !== 'Tools'" class="size-8" />
                                <Globe v-else class="size-8" />
                            </div>
                            <div>
                                <h3 class="text-3xl font-black text-white uppercase tracking-tighter italic">{{ game.game }}</h3>
                                <p class="text-xs font-black uppercase tracking-widest text-muted-foreground/40">{{ game.products.length }} Products active</p>
                            </div>
                        </div>

                        <!-- Products List -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div v-for="product in game.products" :key="product.name" class="flex items-center justify-between p-6 rounded-[2.5rem] bg-white/5 border border-white/5 group/p hover:bg-white/10 hover:border-white/10 transition-all active:scale-[0.98]">
                                <div class="flex items-center gap-4">
                                    <div :class="['p-3 rounded-2xl bg-black/40 border border-white/10 shadow-lg transition-transform group-hover/p:scale-110', getStatusBadge(product.status)]">
                                        <component :is="getStatusIcon(product.status)" class="size-5" />
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-black text-white mb-0.5">{{ product.name }}</h4>
                                        <span class="text-[10px] font-bold text-muted-foreground/30 uppercase tracking-widest">{{ product.type }}</span>
                                    </div>
                                </div>
                                <Badge :class="[getStatusBadge(product.status), 'rounded-xl font-black uppercase text-[9px] tracking-widest px-3 py-1 border backdrop-blur-md transition-all group-hover/p:scale-105']">
                                    {{ product.status }}
                                </Badge>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Status Disclaimer -->
                <div class="mt-24 p-12 bg-white/2 border border-dashed border-white/10 rounded-[3rem] text-center">
                    <div class="flex items-center justify-center gap-2 text-brand-primary font-black uppercase text-[10px] tracking-widest mb-6">
                        <ShieldCheck class="size-4" />
                        RLB Detection Protection
                    </div>
                    <p class="text-muted-foreground text-sm font-medium leading-relaxed max-w-2xl mx-auto italic">
                        Please remember that while our software has an industry-leading security record, no software is 100% undetectable. We recommend using a spoofer and secondary accounts for maximum safety.
                    </p>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>
