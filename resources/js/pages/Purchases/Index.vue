<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { ShoppingBag, Calendar, Package, ArrowRight, ExternalLink, Search, ArrowUpDown, Filter } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';

interface Purchase {
    id: number;
    order_id: string;
    amount_paid: string;
    payment_method: string;
    status: string;
    created_at: string;
    product: {
        id: number;
        name: string;
        image_url: string | null;
    };
}

const props = defineProps<{
    purchases: Purchase[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'My Purchases',
        href: '/purchases',
    },
];

// Filtering & Sorting State
const searchQuery = ref('');
const sortOrder = ref<'latest' | 'oldest'>('latest');
const selectedProduct = ref('all');

// Unique products for filtering
const uniqueProducts = computed(() => {
    const products = props.purchases.map(p => p.product.name);
    return ['all', ...new Set(products)];
});

const filteredAndSortedPurchases = computed(() => {
    let result = [...props.purchases];

    // Filter by search query
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(p => 
            p.product.name.toLowerCase().includes(query) ||
            p.order_id.toLowerCase().includes(query) ||
            p.payment_method.toLowerCase().includes(query)
        );
    }

    // Filter by product
    if (selectedProduct.value !== 'all') {
        result = result.filter(p => p.product.name === selectedProduct.value);
    }

    // Sort by date
    result.sort((a, b) => {
        const dateA = new Date(a.created_at).getTime();
        const dateB = new Date(b.created_at).getTime();
        return sortOrder.value === 'latest' ? dateB - dateA : dateA - dateB;
    });

    return result;
});

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', { 
        month: 'long', 
        day: 'numeric', 
        year: 'numeric' 
    }).format(date);
};

const formatPrice = (amount: string) => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(parseFloat(amount));
};
</script>

<template>
    <Head title="My Purchases" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-8 bg-brand-bg/30">
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-end justify-between gap-6">
                <div class="flex flex-col gap-2">
                    <h1 class="text-3xl font-bold tracking-tight text-foreground flex items-center gap-3">
                        <ShoppingBag class="h-8 w-8 text-brand-primary" />
                        My Purchases
                    </h1>
                    <p class="text-muted-foreground max-w-2xl text-lg">
                        Manage and view all your software licenses and transaction history.
                    </p>
                </div>

                <!-- Filters -->
                <div v-if="purchases.length > 0" class="flex flex-wrap items-center gap-3">
                    <!-- Search -->
                    <div class="relative group">
                        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-brand-primary transition-colors" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search purchases..." 
                            class="bg-sidebar/40 border border-white/5 rounded-2xl py-2.5 pl-10 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/50 focus:border-brand-primary/50 w-full md:w-64 transition-all"
                        />
                    </div>

                    <!-- Product Filter -->
                    <div class="relative group">
                        <Filter class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground group-focus-within:text-brand-primary transition-colors" />
                        <select 
                            v-model="selectedProduct"
                            class="appearance-none bg-sidebar/40 border border-white/5 rounded-2xl py-2.5 pl-10 pr-10 text-sm focus:outline-none focus:ring-2 focus:ring-brand-primary/50 focus:border-brand-primary/50 transition-all cursor-pointer"
                        >
                            <option value="all">All Products</option>
                            <option v-for="product in uniqueProducts.filter(p => p !== 'all')" :key="product" :value="product">
                                {{ product }}
                            </option>
                        </select>
                        <div class="absolute right-3 top-1/2 -translate-y-1/2 pointer-events-none">
                            <ArrowUpDown class="h-3 w-3 text-muted-foreground" />
                        </div>
                    </div>

                    <!-- Sort -->
                    <Button 
                        variant="ghost" 
                        class="rounded-2xl bg-sidebar/40 border border-white/5 px-4 py-2.5 h-auto text-sm font-medium hover:bg-brand-primary/10 hover:text-brand-primary transition-all"
                        @click="sortOrder = sortOrder === 'latest' ? 'oldest' : 'latest'"
                    >
                        <ArrowUpDown class="h-4 w-4 mr-2" />
                        {{ sortOrder === 'latest' ? 'Latest' : 'Oldest' }}
                    </Button>
                </div>
            </div>

            <!-- Content -->
            <div v-if="filteredAndSortedPurchases.length > 0" class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                <div 
                    v-for="purchase in filteredAndSortedPurchases" 
                    :key="purchase.id"
                    class="group relative overflow-hidden rounded-3xl bg-sidebar/40 border border-white/5 backdrop-blur-xl transition-all duration-300 hover:border-brand-primary/30 hover:shadow-2xl hover:shadow-brand-primary/10 hover:-translate-y-1 animate-in fade-in slide-in-from-bottom-4"
                >
                    <!-- Status Badge -->
                    <div class="absolute top-4 right-4 z-10">
                        <span 
                            class="px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-widest border"
                            :class="purchase.status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border-amber-500/20'"
                        >
                            {{ purchase.status }}
                        </span>
                    </div>

                    <div class="p-6 space-y-5">
                        <!-- Product Info -->
                        <div class="flex items-center gap-4">
                            <div class="h-14 w-14 rounded-2xl bg-brand-primary/10 flex items-center justify-center shrink-0 border border-brand-primary/20 group-hover:bg-brand-primary group-hover:text-white transition-colors duration-300">
                                <Package class="h-7 w-7 text-brand-primary group-hover:text-white" />
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-bold text-foreground text-lg truncate group-hover:text-brand-primary transition-colors">
                                    {{ purchase.product.name }}
                                </h3>
                                <div class="flex flex-col gap-1 mt-1">
                                    <p class="text-[10px] text-brand-primary font-bold uppercase tracking-wider">
                                        ID: {{ purchase.order_id }}
                                    </p>
                                    <p class="text-xs text-muted-foreground flex items-center gap-1.5">
                                        <Calendar class="h-3 w-3" />
                                        {{ formatDate(purchase.created_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Price & Method -->
                        <div class="flex items-center justify-between py-4 border-y border-white/5">
                            <div class="space-y-1">
                                <p class="text-[10px] uppercase tracking-widest text-muted-foreground font-bold">Amount Paid</p>
                                <p class="text-xl font-bold text-foreground">{{ formatPrice(purchase.amount_paid) }}</p>
                            </div>
                            <div class="text-right space-y-1">
                                <p class="text-[10px] uppercase tracking-widest text-muted-foreground font-bold">Method</p>
                                <p class="text-xs font-medium text-foreground bg-white/5 px-2 py-1 rounded-lg border border-white/5 uppercase">{{ purchase.payment_method }}</p>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-3 pt-2">
                             <Button as-child variant="ghost" class="flex-1 rounded-2xl bg-white/5 hover:bg-brand-primary hover:text-white border border-white/5 transition-all duration-300 font-bold group/btn">
                                <Link href="/downloads" class="flex items-center gap-2">
                                    Downloads
                                    <ArrowRight class="h-4 w-4 transform group-hover/btn:translate-x-1 transition-transform" />
                                </Link>
                            </Button>
                        </div>
                    </div>

                    <!-- Ambient Glow -->
                    <div class="absolute -bottom-10 -right-10 h-32 w-32 bg-brand-primary/10 blur-[60px] rounded-full opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                </div>
            </div>

            <!-- Empty State / No Results -->
            <div v-else class="flex-1 flex flex-col items-center justify-center min-h-[400px] text-center space-y-6 animate-in fade-in zoom-in duration-700">
                <div class="relative">
                    <div class="h-24 w-24 rounded-full bg-brand-primary/10 flex items-center justify-center animate-pulse">
                        <ShoppingBag class="h-10 w-10 text-brand-primary/50" />
                    </div>
                </div>
                
                <div class="space-y-2">
                    <h2 class="text-2xl font-bold text-foreground">
                        {{ purchases.length === 0 ? 'No purchases yet' : 'No results found' }}
                    </h2>
                    <p class="text-muted-foreground max-w-sm mx-auto">
                        {{ purchases.length === 0 
                            ? 'Your purchase history is currently empty. Head over to our store to explore our latest game enhancements!'
                            : 'Adjust your filters or search query to find what you\'re looking for.' }}
                    </p>
                </div>

                <Button v-if="purchases.length === 0" as-child class="rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white px-8 py-6 h-auto text-lg font-bold shadow-xl shadow-brand-primary/20 hover:shadow-brand-primary/30 transition-all hover:scale-105">
                    <Link href="/downloads" class="flex items-center gap-3">
                        Browse Products
                        <ExternalLink class="h-5 w-5" />
                    </Link>
                </Button>
                <Button v-else variant="ghost" @click="searchQuery = ''; selectedProduct = 'all'; sortOrder = 'latest'" class="rounded-2xl bg-white/5 hover:bg-brand-primary hover:text-white border border-white/5 px-8 py-2.5 transition-all font-bold">
                    Clear Filters
                </Button>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
</style>
