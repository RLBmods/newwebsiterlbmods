<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { 
    CheckCircle2, 
    ShieldCheck, 
    Monitor, 
    Zap, 
    ShoppingCart, 
    ChevronRight, 
    Eye, 
    Cpu, 
    Gamepad2,
    Settings,
    Share2,
    Play,
    Shield,
    Activity
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { ref, computed } from 'vue';
import { useCart } from '@/composables/useCart';
import { useToast } from 'vue-toastification';

const props = defineProps<{
    product: any;
}>();

const { addToCart } = useCart();
const toast = useToast();

const iconMap: Record<string, any> = {
    'Monitor': Monitor,
    'Cpu': Cpu,
    'Shield': Shield,
    'Gamepad2': Gamepad2,
    'Settings': Settings,
    'Share2': Share2,
    'Activity': Activity,
    'Zap': Zap,
    'Play': Play,
};

const getIcon = (name: string) => iconMap[name] || Monitor;

const p = computed(() => props.product?.data || props.product || {});

// Use carousel images if available, otherwise fallback to main image
const allImages = computed<string[]>(() => {
    if (p.value.carousel_images && p.value.carousel_images.length > 0) {
        return p.value.carousel_images;
    }
    return [p.value.image];
});

const currentImageIndex = ref<number>(0);
const selectedPrice = ref(p.value.prices?.[0] || null);

const formatDuration = (duration: number, type: string) => {
    if (duration === 99999) return 'Lifetime';
    const typeLabel = type.toLowerCase().endsWith('s') ? type : type + (duration === 1 ? '' : 's');
    return `${duration} ${typeLabel}`;
};

const handleAddToCart = () => {
    if (!selectedPrice.value) {
        toast.error('Please select a plan first');
        return;
    }

    addToCart({
        productId: p.value.id,
        priceId: selectedPrice.value.id,
        name: `${p.value.name} - ${selectedPrice.value.name}`,
        productName: p.value.name,
        optionName: selectedPrice.value.name,
        price: selectedPrice.value.price,
        image: p.value.image,
        game: p.value.game
    });

    toast.success(`${p.value.name} added to cart!`);
};

const activePreviewTab = ref('0');

const getCurrentMenuImage = computed(() => {
    const images = p.value.menu_images;
    if (images && images.length > 0) {
        const index = parseInt(activePreviewTab.value);
        return images[index]?.url || images[index] || p.value.image;
    }
    return p.value.image;
});
</script>

<template>
    <Head :title="`${p.name} - RLBmods Cheats`" />

    <FrontLayout>
        <!-- Product Section -->
        <section class="relative pt-10 pb-20 px-6">
            <div class="max-w-7xl mx-auto">
                
                <!-- Game Badge & Title -->
                <div class="mb-10">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-brand-primary/10 border border-brand-primary/20 text-brand-primary font-bold uppercase text-[10px] tracking-widest mb-4">
                        <Activity class="size-3" />
                        {{ p.game }}
                    </div>
                    <h1 class="text-5xl font-black text-white tracking-tight">
                        {{ p.name }}
                    </h1>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
                    
                    <!-- Left: Carousel -->
                    <div class="lg:col-span-8 space-y-6">
                        <div class="relative aspect-video w-full rounded-2xl overflow-hidden border border-white/5 shadow-2xl bg-black/40">
                            <Transition
                                enter-active-class="transition duration-500 ease-out"
                                enter-from-class="opacity-0 scale-105"
                                enter-to-class="opacity-100 scale-100"
                                leave-active-class="transition duration-500 ease-in absolute inset-0"
                                leave-from-class="opacity-100 scale-100"
                                leave-to-class="opacity-0 scale-95"
                            >
                                <img :key="currentImageIndex" :src="allImages[currentImageIndex]" class="w-full h-full object-cover" />
                            </Transition>
                            
                            <!-- Internal Overlays if needed -->
                            <div class="absolute bottom-6 left-6 flex items-center gap-4">
                                <div class="px-4 py-2 bg-black/60 backdrop-blur-md rounded-xl border border-white/10 flex items-center gap-3">
                                    <div class="size-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                    <span class="text-[10px] font-black uppercase tracking-widest">{{ p.status }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Thumbnails -->
                        <div class="grid grid-cols-4 sm:grid-cols-6 gap-4">
                            <button 
                                v-for="(img, idx) in allImages" 
                                :key="idx"
                                @click="currentImageIndex = idx"
                                :class="[
                                    'aspect-video rounded-xl overflow-hidden border-2 transition-all',
                                    currentImageIndex === idx ? 'border-brand-primary scale-105' : 'border-white/5 opacity-50 hover:opacity-100'
                                ]"
                            >
                                <img :src="img" class="w-full h-full object-cover" />
                            </button>
                        </div>

                        <!-- Display Videos Button -->
                        <button class="w-full py-4 rounded-xl bg-white/5 border border-white/5 text-brand-primary font-black uppercase text-xs tracking-widest flex items-center justify-center gap-3 hover:bg-white/10 transition-all">
                            <Play class="size-4" />
                            Display Videos
                        </button>
                    </div>

                    <!-- Right: Pricing/Purchase -->
                    <div class="lg:col-span-4 space-y-6">
                        <div class="bg-[#0D0D12] border border-white/5 rounded-2xl p-6 space-y-4">
                            <template v-if="((p.prices as any)?.data || p.prices || []).length > 0">
                                <div 
                                    v-for="price in ((p.prices as any)?.data || p.prices || [])" 
                                    :key="price.id"
                                    @click="selectedPrice = price"
                                    :class="[
                                        'w-full p-6 rounded-2xl border transition-all flex items-center justify-between cursor-pointer group active:scale-[0.98]',
                                        selectedPrice?.id === price.id 
                                            ? 'bg-brand-primary border-brand-primary text-white shadow-xl shadow-brand-primary/20' 
                                            : 'bg-white/5 border-white/5 text-muted-foreground hover:bg-white/10 hover:border-white/10'
                                    ]"
                                >
                                    <div class="flex flex-col gap-1">
                                        <span :class="['text-sm font-black uppercase tracking-widest', selectedPrice?.id === price.id ? 'text-white' : 'text-white']">{{ formatDuration(price.duration, price.duration_type) }}</span>
                                        <span :class="['text-[10px] font-bold uppercase tracking-widest', selectedPrice?.id === price.id ? 'text-white/60' : 'text-muted-foreground/40']">{{ price.stock }} in stock</span>
                                    </div>
                                    <div :class="['text-xl font-black italic', selectedPrice?.id === price.id ? 'text-white' : 'text-white']">
                                        ${{ (price.price || 0).toFixed(2) }}
                                    </div>
                                </div>

                                <Button 
                                    @click="handleAddToCart"
                                    class="w-full h-16 rounded-xl bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest transition-all flex items-center justify-center gap-3 mt-4"
                                >
                                    <ShoppingCart class="size-5" />
                                    Purchase Now
                                </Button>
                            </template>
                            <div v-else class="py-12 text-center space-y-6">
                                <div class="size-20 rounded-3xl bg-brand-primary/10 flex items-center justify-center mx-auto">
                                    <Zap class="size-10 text-brand-primary" />
                                </div>
                                <div>
                                    <h3 class="text-xl font-black text-white uppercase italic tracking-tight mb-2">Pricing Unavailable</h3>
                                    <p class="text-sm text-muted-foreground max-w-[200px] mx-auto">This product currently has no active plans. Contact us on Discord for pricing.</p>
                                </div>
                                <a href="https://discord.gg/rlbmods" target="_blank" class="block">
                                    <Button variant="outline" class="w-full h-14 rounded-xl border-white/10 hover:bg-white/5 text-white font-black uppercase text-xs tracking-widest">
                                        Support Discord
                                    </Button>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Requirements Section -->
        <section class="py-20 px-6">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-3xl font-black text-white mb-10 tracking-tight italic uppercase">Requirements</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Dynamic Requirements -->
                    <template v-if="p.requirements && p.requirements.length > 0">
                        <div v-for="(req, idx) in p.requirements" :key="idx" class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <component :is="getIcon(req.icon || 'Monitor')" class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">{{ req.label }}</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">{{ req.value }}</p>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <!-- Fallback Hardcoded dynamic values -->
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Monitor class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Operating System</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">Windows 10 & 11</p>
                            </div>
                        </div>
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Cpu class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Processors</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">AMD & Intel</p>
                            </div>
                        </div>
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Shield class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Anti-Cheat</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">{{ p.anti_cheat }}</p>
                            </div>
                        </div>
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Gamepad2 class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Game Mode</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">Windowed/Borderless</p>
                            </div>
                        </div>
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Settings class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Spoofer included</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">{{ p.spoofer_included ? 'Yes' : 'No' }}</p>
                            </div>
                        </div>
                        <div class="p-8 rounded-2xl bg-[#0D0D12] border border-white/5 space-y-4">
                            <Share2 class="size-8 text-brand-primary" />
                            <div>
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/40 mb-1">Platform</p>
                                <p class="text-sm font-black text-white uppercase italic tracking-tight">{{ p.game }}</p>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </section>

        <!-- Menu Preview Section -->
        <section class="py-20 px-6">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-3xl font-black text-white mb-10 tracking-tight italic uppercase">Menu Preview</h2>
                
                <div class="bg-[#0D0D12] border border-white/5 rounded-2xl overflow-hidden">
                    <!-- Tabs Header -->
                    <div class="flex items-center gap-2 p-3 bg-white/2 border-b border-white/5 overflow-x-auto no-scrollbar">
                        <button 
                            v-for="(img, idx) in (p.menu_images && p.menu_images.length > 0 ? p.menu_images : [{url: p.image, label: 'Default Preview'}])" 
                            :key="idx"
                            @click="activePreviewTab = idx.toString()"
                            :class="[
                                'px-6 py-2 rounded-lg text-xs font-black uppercase tracking-widest transition-all whitespace-nowrap',
                                activePreviewTab === idx.toString()
                                    ? 'bg-brand-primary text-white' 
                                    : 'text-muted-foreground hover:text-white'
                            ]"
                        >
                            {{ img.label || `Preview ${Number(idx) + 1}` }}
                        </button>
                    </div>

                    <!-- Tab Content (Menu Screenshot) -->
                    <div class="p-6">
                        <div class="relative rounded-xl overflow-hidden border border-white/5 shadow-2xl group cursor-zoom-in">
                            <img :src="getCurrentMenuImage" class="w-full h-auto" />
                            <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                <Eye class="size-12 text-white" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section class="py-40 px-6 border-t border-white/5 relative overflow-hidden">
            <!-- Background Glow -->
            <div class="absolute -top-40 -left-40 size-[40rem] bg-brand-primary/5 blur-[120px] rounded-full"></div>

            <div class="max-w-7xl mx-auto relative z-10">
                <h2 class="text-4xl md:text-5xl font-black text-white mb-20 tracking-tighter italic uppercase">UNMATCHED <span class="text-brand-primary">FEATURES</span></h2>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-16">
                    <div v-for="(features, category) in p.features" :key="category" class="space-y-8">
                        <div>
                            <h3 class="text-xs font-black text-brand-primary uppercase tracking-[0.3em] mb-4">{{ category }}</h3>
                            <div class="h-1 w-12 bg-brand-primary/50 rounded-full"></div>
                        </div>
                        <ul class="space-y-4">
                            <li v-for="feature in features" :key="feature" class="flex items-start gap-3 group">
                                <CheckCircle2 class="size-4 text-brand-primary shrink-0 mt-0.5 group-hover:scale-110 transition-transform" />
                                <span class="text-xs font-bold text-muted-foreground group-hover:text-white transition-colors leading-relaxed uppercase tracking-tight">
                                    {{ feature }}
                                </span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
