<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/components/AppLogo.vue';
import { 
    Home, 
    ShoppingCart, 
    Activity, 
    Youtube, 
    MessageSquare, 
    Menu, 
    X,
    ChevronRight,
    Shield,
    Globe,
    Zap,
    Trash2,
    Plus,
    Minus,
    ArrowRight
} from 'lucide-vue-next';
import { ref, onMounted, onUnmounted } from 'vue';
import { Button } from '@/components/ui/button';
import { 
    Sheet, 
    SheetContent, 
    SheetHeader, 
    SheetTitle, 
    SheetTrigger,
    SheetFooter
} from '@/components/ui/sheet';
import { useCart } from '@/composables/useCart';

const isMenuOpen = ref(false);
const isScrolled = ref(false);
const { cart, removeFromCart, updateQuantity, cartCount, cartTotal } = useCart();

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});

const navLinks = [
    { name: 'Home', href: '/', icon: Home },
    { name: 'Shop', href: '/shop', icon: ShoppingCart },
    { name: 'Status', href: '/status', icon: Activity },
];
</script>

<template>
    <div class="min-h-screen bg-[#060606] text-white selection:bg-brand-primary/30 selection:text-white font-sans overflow-x-hidden">
        
        <!-- Navigation -->
        <nav v-if="!$page.props.settings.maintenance_mode" :class="[
            'fixed top-0 left-0 right-0 z-[100] transition-all duration-500 border-b',
            isScrolled 
                ? 'bg-black/60 backdrop-blur-xl border-white/5 py-3' 
                : 'bg-transparent border-transparent py-5'
        ]">
            <div class="max-w-7xl mx-auto px-6 flex items-center justify-between">
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-3 group transition-transform hover:scale-105 active:scale-95">
                    <div class="relative">
                        <div class="absolute -inset-2 bg-brand-primary/20 blur-xl rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></div>
                        <img
                            v-if="$page.props.settings.site_logo"
                            :src="$page.props.settings.site_logo"
                            class="h-10 w-auto object-contain relative z-10"
                            :alt="$page.props.settings.site_name"
                        />
                        <AppLogo v-else class="h-10 w-auto fill-brand-primary relative z-10" />
                    </div>
                </Link>

                <!-- Desktop Nav -->
                <div class="hidden md:flex items-center gap-8">
                    <div class="flex items-center gap-1 bg-white/5 border border-white/5 p-1 rounded-2xl backdrop-blur-md">
                        <Link v-for="link in navLinks" :key="link.name" :href="link.href"
                            :class="[
                                'px-4 py-2 rounded-xl text-sm font-black uppercase tracking-widest transition-all flex items-center gap-2',
                                $page.url === link.href 
                                    ? 'bg-brand-primary text-white shadow-lg shadow-brand-primary/20' 
                                    : 'text-muted-foreground hover:text-white hover:bg-white/5'
                            ]"
                        >
                            <component :is="link.icon" class="size-3.5" />
                            {{ link.name }}
                        </Link>
                    </div>

                    <div class="h-6 w-px bg-white/10"></div>

                    <div class="flex items-center gap-3">
                        <!-- Cart Trigger -->
                        <Sheet>
                            <SheetTrigger as-child>
                                <Button variant="ghost" class="relative size-11 rounded-xl bg-white/5 border border-white/10 hover:bg-white/10 transition-all p-0">
                                    <ShoppingCart class="size-5 text-white" />
                                    <span v-if="cartCount > 0" class="absolute -top-2 -right-2 size-5 rounded-full bg-brand-primary text-[10px] font-black flex items-center justify-center border-2 border-[#060606] animate-in zoom-in duration-300">
                                        {{ cartCount }}
                                    </span>
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="right" class="w-full sm:max-w-md bg-[#0A0A0A] border-l border-white/10 p-0 flex flex-col">
                                <SheetHeader class="p-8 border-b border-white/5">
                                    <SheetTitle class="text-2xl font-black uppercase tracking-tighter italic text-white flex items-center gap-3">
                                        <ShoppingCart class="size-6 text-brand-primary" />
                                        Your Cart
                                    </SheetTitle>
                                </SheetHeader>

                                <div class="flex-1 overflow-y-auto p-8 space-y-6">
                                    <div v-if="cart.length === 0" class="h-full flex flex-col items-center justify-center text-center space-y-6">
                                        <div class="size-24 rounded-[2.5rem] bg-white/5 border border-dashed border-white/10 flex items-center justify-center text-muted-foreground/30">
                                            <ShoppingCart class="size-10" />
                                        </div>
                                        <div>
                                            <h3 class="text-xl font-black text-white uppercase tracking-tighter mb-2">Cart is empty</h3>
                                            <p class="text-sm text-muted-foreground font-medium">Looks like you haven't added anything yet.</p>
                                        </div>
                                        <Link href="/shop">
                                            <Button variant="outline" @click="() => {}" class="rounded-2xl border-white/10 hover:bg-white/5 font-black uppercase text-xs tracking-widest px-8">
                                                Go to Shop
                                            </Button>
                                        </Link>
                                    </div>

                                    <div v-else class="space-y-4">
                                        <div v-for="item in cart" :key="item.id" class="group relative flex items-center gap-4 p-4 rounded-3xl bg-white/2 border border-white/5 hover:bg-white/5 transition-all">
                                            <div class="size-20 rounded-2xl overflow-hidden bg-black/40 border border-white/5 shrink-0">
                                                <img :src="item.image" class="w-full h-full object-cover" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between gap-2 mb-1">
                                                    <h4 class="text-sm font-black text-white truncate uppercase tracking-tight">{{ item.productName }}</h4>
                                                    <button @click="removeFromCart(item.id)" class="text-muted-foreground/30 hover:text-red-500 transition-colors">
                                                        <Trash2 class="size-3.5" />
                                                    </button>
                                                </div>
                                                <p class="text-[10px] font-black uppercase tracking-widest text-brand-primary/60 mb-3">{{ item.optionName }}</p>
                                                
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center gap-2 bg-black/40 border border-white/10 rounded-xl p-1">
                                                        <button @click="updateQuantity(item.id, item.quantity - 1)" class="size-6 flex items-center justify-center rounded-lg hover:bg-white/10 text-muted-foreground">
                                                            <Minus class="size-3" />
                                                        </button>
                                                        <span class="text-xs font-black w-6 text-center">{{ item.quantity }}</span>
                                                        <button @click="updateQuantity(item.id, item.quantity + 1)" class="size-6 flex items-center justify-center rounded-lg hover:bg-white/10 text-muted-foreground">
                                                            <Plus class="size-3" />
                                                        </button>
                                                    </div>
                                                    <span class="text-sm font-black text-white">${{ (item.price * item.quantity).toFixed(2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div v-if="cart.length > 0" class="p-8 bg-white/2 border-t border-white/10 space-y-6">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground">Subtotal</span>
                                        <span class="text-3xl font-black italic text-white">${{ cartTotal.toFixed(2) }}</span>
                                    </div>

                                    <Link href="/checkout">
                                        <Button class="w-full h-16 rounded-[2rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest border-b-4 border-brand-primary/50 transition-all flex items-center justify-center gap-3">
                                            Checkout Now
                                            <ArrowRight class="size-4" />
                                        </Button>
                                    </Link>
                                </div>
                            </SheetContent>
                        </Sheet>

                        <div class="h-6 w-px bg-white/10"></div>

                        <Link v-if="!$page.props.auth.user" href="/login">
                            <Button variant="ghost" class="font-black text-xs uppercase tracking-widest hover:bg-white/5 rounded-xl px-6">
                                Sign In
                            </Button>
                        </Link>
                        <Link :href="$page.props.auth.user ? '/dashboard' : '/register'">
                            <Button class="bg-brand-primary hover:bg-brand-primary/80 text-white font-black text-xs uppercase tracking-widest px-8 rounded-xl h-11 border-b-4 border-brand-primary/50 active:border-b-0 active:translate-y-[2px] transition-all">
                                {{ $page.props.auth.user ? 'Dashboard' : 'Get Started' }}
                            </Button>
                        </Link>
                    </div>
                </div>

                <!-- Mobile Trigger -->
                <button @click="isMenuOpen = !isMenuOpen" class="md:hidden size-10 flex items-center justify-center rounded-xl bg-white/5 border border-white/10 active:scale-95 transition-all">
                    <component :is="isMenuOpen ? X : Menu" class="size-5 text-white" />
                </button>
            </div>
        </nav>

        <!-- Mobile Menu -->
        <Transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="opacity-0 translate-y-4"
            enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="opacity-100 translate-y-0"
            leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="isMenuOpen && !$page.props.settings.maintenance_mode" class="fixed inset-0 z-[90] bg-black/95 backdrop-blur-2xl md:hidden pt-24 px-6 flex flex-col gap-6">
                <Link v-for="link in navLinks" :key="link.name" :href="link.href" @click="isMenuOpen = false"
                    :class="[
                        'flex items-center justify-between p-6 rounded-3xl border transition-all active:scale-[0.98]',
                        $page.url === link.href 
                            ? 'bg-brand-primary/10 border-brand-primary/30 text-white shadow-2xl shadow-brand-primary/10' 
                            : 'bg-white/5 border-white/5 text-muted-foreground'
                    ]"
                >
                    <div class="flex items-center gap-4">
                        <div :class="['p-3 rounded-2xl', $page.url === link.href ? 'bg-brand-primary text-white' : 'bg-white/5 text-muted-foreground']">
                            <component :is="link.icon" class="size-6" />
                        </div>
                        <span class="text-xl font-black uppercase tracking-widest">{{ link.name }}</span>
                    </div>
                    <ChevronRight class="size-5 opacity-30" />
                </Link>

                <div class="mt-auto mb-10 space-y-4">
                    <Link href="/login" class="block w-full" @click="isMenuOpen = false">
                        <Button variant="outline" class="w-full h-16 rounded-3xl border-white/10 hover:bg-white/5 font-black uppercase text-sm tracking-widest transition-all">
                            Sign In
                        </Button>
                    </Link>
                    <Link :href="$page.props.auth.user ? '/dashboard' : '/register'" class="block w-full" @click="isMenuOpen = false">
                        <Button class="w-full h-16 rounded-3xl bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest border-b-4 border-brand-primary/50 transition-all">
                            {{ $page.props.auth.user ? 'Dashboard' : 'Get Started' }}
                        </Button>
                    </Link>
                </div>
            </div>
        </Transition>

        <!-- Main Content -->
        <main v-if="!$page.props.settings.maintenance_mode" class="relative z-10 pt-20">
            <slot />
        </main>

        <!-- Footer -->
        <footer v-if="!$page.props.settings.maintenance_mode" class="mt-20 border-t border-white/5 bg-[#080808] relative z-10">
            <div class="max-w-7xl mx-auto px-6 py-20">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12">
                    <!-- Brand Section -->
                    <div class="md:col-span-1 space-y-6">
                        <Link href="/" class="flex items-center gap-3">
                            <img
                                v-if="$page.props.settings.site_logo"
                                :src="$page.props.settings.site_logo"
                                class="h-8 w-auto object-contain"
                                :alt="$page.props.settings.site_name"
                            />
                            <AppLogo v-else class="h-8 w-auto fill-brand-primary" />
                            <span class="text-xl font-black tracking-tighter uppercase italic">{{ $page.props.settings.site_name }}</span>
                        </Link>
                        <p class="text-muted-foreground text-sm font-medium leading-relaxed">
                            The industry's most reputable and longstanding software modification provider. Powering thousands of gamers worldwide with cutting-edge tech.
                        </p>
                        <div class="flex items-center gap-4">
                            <a href="https://youtube.com/@rlbmods" target="_blank" class="size-10 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-muted-foreground hover:text-white hover:bg-red-500/10 hover:border-red-500/30 transition-all active:scale-95 shadow-lg shadow-black/40">
                                <Youtube class="size-5" />
                            </a>
                            <a href="https://discord.gg/rlbmods" target="_blank" class="size-10 rounded-xl bg-white/5 border border-white/5 flex items-center justify-center text-muted-foreground hover:text-white hover:bg-indigo-500/10 hover:border-indigo-500/30 transition-all active:scale-95 shadow-lg shadow-black/40">
                                <MessageSquare class="size-5" />
                            </a>
                        </div>
                    </div>

                    <!-- Navigation -->
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-[0.2em] text-white mb-8 border-l-2 border-brand-primary pl-4">Navigation</h4>
                        <ul class="space-y-4">
                            <li v-for="link in navLinks" :key="link.name">
                                <Link :href="link.href" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 group transition-all">
                                    <ChevronRight class="size-3 opacity-0 group-hover:opacity-100 -translate-x-2 group-hover:translate-x-0 transition-all" />
                                    {{ link.name }}
                                </Link>
                            </li>
                        </ul>
                    </div>

                    <!-- Community -->
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-[0.2em] text-white mb-8 border-l-2 border-brand-primary pl-4">Community</h4>
                        <ul class="space-y-4">
                            <li><a href="#" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Forum</a></li>
                            <li><a href="#" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Discord Portal</a></li>
                            <li><a href="#" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Media Assets</a></li>
                        </ul>
                    </div>

                    <!-- Legal -->
                    <div>
                        <h4 class="text-xs font-black uppercase tracking-[0.2em] text-white mb-8 border-l-2 border-brand-primary pl-4">Legal</h4>
                        <ul class="space-y-4">
                            <li><Link href="/terms" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Terms of Service</Link></li>
                            <li><Link href="/privacy" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Privacy Policy</Link></li>
                            <li><Link href="/refunds" class="text-muted-foreground hover:text-brand-primary text-sm font-bold tracking-wide flex items-center gap-2 transition-all">Refund Policy</Link></li>
                        </ul>
                    </div>
                </div>

                <div class="mt-20 pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-6">
                    <p class="text-muted-foreground/30 text-[10px] font-black uppercase tracking-widest">
                        {{ ($page.props.settings.copyright_text || '').toUpperCase() }}. NOT AFFILIATED WITH ANY GAME DEVELOPER.
                    </p>
                    <div class="flex items-center gap-2 grayscale opacity-30 hover:grayscale-0 hover:opacity-100 transition-all cursor-crosshair">
                        <div class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></div>
                        <span class="text-[10px] font-black uppercase tracking-widest text-[#EDEDED]">All systems operational</span>
                    </div>
                </div>
            </div>
        </footer>

        <!-- Maintenance Mode Overlay -->
        <div v-if="$page.props.settings.maintenance_mode" class="fixed inset-0 z-[200] bg-[#060606] flex flex-col items-center justify-center p-6 text-center">
            <div class="relative mb-12 animate-in fade-in zoom-in duration-700">
                <div class="absolute -inset-8 bg-brand-primary/20 blur-[60px] rounded-full"></div>
                <img
                    v-if="$page.props.settings.site_logo"
                    :src="$page.props.settings.site_logo"
                    class="h-24 w-auto object-contain relative z-10"
                    :alt="$page.props.settings.site_name"
                />
                <AppLogo v-else class="h-24 w-auto fill-brand-primary relative z-10" />
            </div>
            
            <h1 class="text-4xl md:text-6xl font-black text-white uppercase italic tracking-tighter mb-6 animate-in slide-in-from-bottom-4 duration-700 delay-100">
                Under Maintenance
            </h1>
            
            <p class="text-muted-foreground max-w-md text-lg font-medium leading-relaxed mb-12 animate-in slide-in-from-bottom-4 duration-700 delay-200">
                We're currently performing some scheduled maintenance to improve your experience. We'll be back online shortly!
            </p>
            
            <div class="flex flex-col sm:flex-row items-center gap-4 animate-in slide-in-from-bottom-4 duration-700 delay-300">
                <a href="https://discord.gg/rlbmods" target="_blank">
                    <Button class="bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest px-10 h-16 rounded-2xl border-b-4 border-brand-primary/50 flex items-center gap-3 active:border-b-0 active:translate-y-[2px] transition-all">
                        <MessageSquare class="size-5" />
                        Join Discord
                    </Button>
                </a>
            </div>

            <!-- Background Decoration -->
            <div class="absolute inset-0 pointer-events-none z-0 overflow-hidden">
                <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(circle_at_center,_#f5300305_0%,_transparent_70%)]"></div>
            </div>
        </div>

        <!-- Background Decorations -->
        <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
            <div class="absolute top-0 right-0 w-1/2 h-1/2 bg-brand-primary/5 blur-[120px] rounded-full translate-x-1/2 -translate-y-1/2"></div>
            <div class="absolute bottom-0 left-0 w-1/2 h-1/2 bg-brand-primary/5 blur-[120px] rounded-full -translate-x-1/2 translate-y-1/2"></div>
            <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full h-full bg-[radial-gradient(circle_at_center,_transparent_0%,_#060606_80%)] opacity-50"></div>
        </div>
    </div>
</template>

<style>
/* Smooth scroll behavior */
html {
    scroll-behavior: smooth;
}

/* Custom scrollbar */
::-webkit-scrollbar {
    width: 6px;
}

::-webkit-scrollbar-track {
    background: #060606;
}

::-webkit-scrollbar-thumb {
    background: #222;
    border-radius: 10px;
}

::-webkit-scrollbar-thumb:hover {
    background: #f53003;
}
</style>
