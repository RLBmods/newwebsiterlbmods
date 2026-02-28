<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ShoppingCart, Shield, Activity, Zap, ArrowRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    game: string;
    status: string;
    image: string;
    prices?: Array<{
        id: number;
        duration: number;
        duration_type: string;
        price: number;
    }>;
}

defineProps<{
    product: Product;
}>();

const formatDuration = (duration: number, type: string) => {
    if (duration === 99999) return 'Lifetime';
    const typeLabel = type.toLowerCase().endsWith('s') ? type : type + (duration === 1 ? '' : 's');
    return `${duration} ${typeLabel}`;
};

const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
        case 'undetected': return 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20';
        case 'updated': return 'bg-blue-500/10 text-blue-400 border-blue-500/20';
        case 'testing': return 'bg-amber-500/10 text-amber-400 border-amber-500/20';
        case 'maintenance': return 'bg-red-500/10 text-red-400 border-red-500/20';
        default: return 'bg-muted/10 text-muted-foreground border-white/5';
    }
};
</script>

<template>
    <div class="group relative bg-[#0D0D12] border border-white/5 rounded-[2.5rem] p-4 transition-all duration-500 hover:border-brand-primary/30 hover:-translate-y-2 shadow-2xl hover:shadow-brand-primary/10 overflow-hidden">
        
        <!-- Glowing Background Effect -->
        <div class="absolute -top-24 -right-24 size-48 bg-brand-primary/5 blur-[80px] rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
        
        <!-- Product Image Container -->
        <div class="relative h-56 w-full rounded-[2rem] overflow-hidden bg-white/5 border border-white/5 mb-6">
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent z-10"></div>
            <img :src="product.image || 'https://images.unsplash.com/photo-1542751371-adc38448a05e?auto=format&fit=crop&q=80&w=800'" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
            
            <!-- Floating Badges -->
            <div class="absolute top-4 left-4 z-20 flex flex-col gap-2">
                <Badge :class="[getStatusColor(product.status), 'rounded-xl font-black uppercase text-[10px] tracking-widest px-3 py-1 border backdrop-blur-md']">
                    {{ product.status }}
                </Badge>
                <div class="flex items-center gap-1.5 bg-black/60 backdrop-blur-md border border-white/10 rounded-xl px-3 py-1 text-[10px] font-black uppercase tracking-widest text-white">
                    <Zap class="size-3 text-brand-primary" />
                    Instant Access
                </div>
            </div>
        </div>

        <!-- Product Info -->
        <div class="px-2 space-y-4">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-black uppercase tracking-widest text-brand-primary/80">{{ product.game }}</span>
                <div class="flex flex-col items-end">
                    <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 leading-none mb-1">starting at</span>
                    <div class="flex items-center gap-1 text-white font-black text-xl leading-none">
                        <span class="text-xs font-bold text-muted-foreground/50">$</span>
                        {{ product.price.toFixed(2) }}
                    </div>
                </div>
            </div>

            <h3 class="text-xl font-black text-white group-hover:text-brand-primary transition-colors line-clamp-1 uppercase italic tracking-tight">{{ product.name }}</h3>
            
            <!-- Durations / Prices -->
            <div v-if="((product.prices as any)?.data || product.prices || []).length > 0" class="flex flex-wrap gap-2">
                <div v-for="price in ((product.prices as any)?.data || product.prices || [])" :key="price.id" class="px-3 py-1.5 rounded-xl bg-white/5 border border-white/5 text-[9px] font-black uppercase tracking-widest text-white/70 group-hover:border-brand-primary/20 transition-colors">
                    {{ formatDuration(price.duration, price.duration_type) }} - ${{ (price.price || 0).toFixed(2) }}
                </div>
            </div>
            <div v-else class="inline-flex px-3 py-1 rounded-lg bg-brand-primary/10 border border-brand-primary/20 text-brand-primary font-black uppercase text-[9px] tracking-widest">
                Contact for Pricing
            </div>

            <p class="text-xs text-muted-foreground font-medium line-clamp-2 leading-relaxed h-8">{{ product.description }}</p>

            <div class="pt-2">
                <Link :href="`/products/${product.name}`" class="block w-full">
                    <Button class="w-full h-14 rounded-2xl bg-brand-primary hover:bg-brand-primary/80 text-white font-black text-xs uppercase tracking-widest transition-all border-b-4 border-brand-primary/50 active:border-b-0 active:translate-y-[2px] flex items-center justify-center gap-3">
                        Buy Now
                        <ArrowRight class="size-4" />
                    </Button>
                </Link>
            </div>
        </div>
    </div>
</template>
