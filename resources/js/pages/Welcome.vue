<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import ProductCard from '@/components/Public/ProductCard.vue';
import { 
    Shield, 
    Zap, 
    Activity, 
    Users, 
    MessageSquare, 
    ChevronRight,
    Star,
    Monitor,
    Globe
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

const props = defineProps<{
    featuredProducts: any[];
}>();

const features = [
    {
        title: 'Security First',
        desc: 'Advanced slot-based systems and daily monitoring to ensure maximum safety.',
        icon: Shield,
        color: 'text-emerald-400'
    },
    {
        title: 'Instant Delivery',
        desc: 'Receive your license and start gaming within seconds of purchase.',
        icon: Zap,
        color: 'text-amber-400'
    },
    {
        title: 'Active Community',
        desc: 'Join over 34,000 members for daily updates and shared experiences.',
        icon: Users,
        color: 'text-indigo-400'
    },
    {
        title: '24/7 Support',
        desc: 'Expert assistance available around the clock via Discord or Tickets.',
        icon: MessageSquare,
        color: 'text-brand-primary'
    }
];
</script>

<template>
    <Head title="Premium Gaming Mods & Cheats" />

    <FrontLayout>
        <!-- Hero Section -->
        <section class="relative min-h-[90vh] flex items-center justify-center pt-20 overflow-hidden">
            <!-- Background effects -->
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_50%_50%,_rgba(245,48,3,0.1)_0%,_transparent_50%)]"></div>
            
            <div class="max-w-7xl mx-auto px-6 text-center relative z-10">
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-white/5 border border-white/10 text-brand-primary font-black uppercase text-[10px] tracking-[0.3em] mb-8 animate-bounce transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                    <div class="size-2 rounded-full bg-brand-primary animate-pulse"></div>
                    Now Powered by RLB v4.0
                </div>
                
                <h1 class="text-6xl md:text-8xl lg:text-9xl font-black text-white tracking-tighter mb-8 italic">
                    <span class="relative">
                        DOMINATE
                        <div class="absolute -bottom-4 left-0 w-full h-2 bg-brand-primary/50 blur-lg rounded-full"></div>
                    </span> 
                    <br />
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-primary via-white to-white/20">THE FIELD</span>
                </h1>
                
                <p class="text-lg md:text-xl text-muted-foreground font-medium max-w-2xl mx-auto mb-12 leading-relaxed">
                    Take your gaming to the next level with the industry's most reputable and longstanding modification provider. Stealth, security, and success.
                </p>

                <div class="flex flex-col sm:flex-row items-center justify-center gap-6">
                    <Link href="/shop">
                        <Button class="h-16 px-12 rounded-[2rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest border-b-8 border-brand-primary/50 hover:border-b-4 active:border-b-0 active:translate-y-[4px] transition-all shadow-2xl shadow-brand-primary/20">
                            Shop Products
                        </Button>
                    </Link>
                    <a href="https://discord.gg/rlbmods" target="_blank">
                        <Button variant="outline" class="h-16 px-12 rounded-[2rem] border-white/10 hover:bg-white/5 text-white font-black uppercase text-sm tracking-widest transition-all">
                            Join Discord
                        </Button>
                    </a>
                </div>

                <!-- Trust Indicators -->
                <div class="mt-24 grid grid-cols-2 md:grid-cols-4 gap-8 opacity-50 grayscale hover:grayscale-0 hover:opacity-100 transition-all duration-700">
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl font-black text-white">34K+</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Satisfied Users</span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl font-black text-white">100%</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Safety Record</span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl font-black text-white">24/7</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Active Support</span>
                    </div>
                    <div class="flex flex-col items-center gap-2">
                        <span class="text-3xl font-black text-white">2020</span>
                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Since Established</span>
                    </div>
                </div>
            </div>

            <!-- Floating visual elements -->
            <div class="absolute top-1/4 left-10 size-32 bg-brand-primary/20 blur-[60px] rounded-full animate-pulse"></div>
            <div class="absolute bottom-1/4 right-10 size-48 bg-blue-500/10 blur-[80px] rounded-full animate-pulse delay-700"></div>
        </section>

        <!-- Features Grid -->
        <section class="py-32 bg-black/40 border-y border-white/5">
            <div class="max-w-7xl mx-auto px-6">
                <div class="flex flex-col md:flex-row md:items-end justify-between gap-8 mb-20">
                    <div>
                        <h4 class="text-brand-primary font-black uppercase text-xs tracking-[0.3em] mb-4">Why RLBMODS?</h4>
                        <h2 class="text-4xl md:text-5xl font-black text-white tracking-tight italic">UNMATCHED POWER. <br /><span class="text-muted-foreground/30">TOTAL PRIVACY.</span></h2>
                    </div>
                    <p class="text-muted-foreground font-medium max-w-sm mb-2">We build more than just software; we build the tools for total domainance while keeping your accounts secured.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                    <div v-for="feat in features" :key="feat.title" class="group bg-[#0A0A0A] border border-white/5 p-10 rounded-[3rem] transition-all hover:bg-white/5 hover:border-white/10 hover:-translate-y-2">
                        <div :class="['size-14 rounded-2xl bg-white/5 flex items-center justify-center mb-8 border border-white/10 group-hover:scale-110 transition-transform shadow-xl', feat.color]">
                            <component :is="feat.icon" class="size-7" />
                        </div>
                        <h3 class="text-xl font-black text-white mb-4 uppercase tracking-tighter">{{ feat.title }}</h3>
                        <p class="text-sm text-muted-foreground font-bold leading-relaxed">{{ feat.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Featured Products -->
        <section class="py-40 relative">
            <div class="max-w-7xl mx-auto px-6">
                <div class="text-center mb-24">
                    <h2 class="text-4xl md:text-6xl font-black text-white mb-6 uppercase tracking-tighter italic">Featured Products</h2>
                    <p class="text-muted-foreground font-medium max-w-xl mx-auto">Explore our most popular modifications used by professionals around the globe.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                    <ProductCard v-for="product in featuredProducts" :key="product.id" :product="product" />
                </div>

                <div class="mt-20 text-center">
                    <Link href="/shop">
                        <Button variant="ghost" class="group h-14 px-10 rounded-2xl text-white font-black uppercase text-xs tracking-widest border border-white/5 hover:bg-white/5 transition-all">
                            View All Products
                            <ChevronRight class="size-4 ml-2 group-hover:translate-x-1 transition-transform" />
                        </Button>
                    </Link>
                </div>
            </div>
        </section>

        <!-- Community CTA -->
        <section class="py-40 px-6">
            <div class="max-w-5xl mx-auto relative group">
                <!-- Background Glow -->
                <div class="absolute inset-0 bg-brand-primary blur-[100px] opacity-20 group-hover:opacity-30 transition-opacity"></div>
                
                <div class="relative bg-gradient-to-br from-[#111] to-[#050505] border border-white/10 rounded-[4rem] p-12 md:p-24 text-center overflow-hidden">
                    <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1550745165-9bc0b252726f?auto=format&fit=crop&q=80&w=2000')] opacity-5 mix-blend-overlay"></div>
                    
                    <h2 class="text-4xl md:text-6xl font-black text-white mb-8 tracking-tighter relative z-10">JOIN THE ELITE COMMUNITY</h2>
                    <p class="text-lg text-muted-foreground font-medium max-w-2xl mx-auto mb-12 relative z-10 leading-relaxed">
                        Become part of our thriving Discord community with over 34,000 members. Get support, daily news, and connect with other power users.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6 relative z-10">
                        <a href="https://discord.gg/rlbmods" target="_blank" class="w-full sm:w-auto">
                            <Button class="w-full sm:w-auto h-16 px-16 rounded-[2rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest border-b-8 border-brand-primary/50 transition-all">
                                Join Discord
                            </Button>
                        </a>
                        <Link href="/register" class="w-full sm:w-auto">
                            <Button variant="outline" class="w-full sm:w-auto h-16 px-16 rounded-[2rem] border-white/10 hover:bg-white/5 text-white font-black uppercase text-sm tracking-widest transition-all">
                                Register Now
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </section>
    </FrontLayout>
</template>

<style scoped>
@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}
.animate-bounce {
    animation: bounce 3s infinite;
}
</style>
