<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import ProductCard from '@/components/Public/ProductCard.vue';
import { Search, Filter, SlidersHorizontal, ChevronRight } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ref, computed } from 'vue';

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    game: string;
    status: string;
    image: string;
    category?: string;
}

const props = defineProps<{
    products: any;
}>();

const searchQuery = ref('');
const selectedCategory = ref('All');

const realProducts = computed<Product[]>(() => {
    const p = props.products;
    if (Array.isArray(p)) return p;
    if (p?.data && Array.isArray(p.data)) return p.data;
    return [];
});

const categories = computed<string[]>(() => {
    const p = realProducts.value;
    const cats = new Set(p.map((item: Product) => item.game));
    return ['All', ...Array.from(cats)].sort();
});

const filteredProducts = computed<Product[]>(() => {
    const p = realProducts.value;
    return p.filter((product: Product) => {
        const matchesSearch = product.name.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                              product.game.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                              (product.category?.toLowerCase() || '').includes(searchQuery.value.toLowerCase());
        const matchesCategory = selectedCategory.value === 'All' || product.game === selectedCategory.value;
        return matchesSearch && matchesCategory;
    });
});
</script>

<template>
    <Head title="Shop - RLBMODS" />

    <FrontLayout>
        <section class="py-20 px-6">
            <div class="max-w-7xl mx-auto">
                
                <!-- Shop Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-8 mb-20">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-lg bg-brand-primary/10 border border-brand-primary/20 text-brand-primary font-black uppercase text-[10px] tracking-widest mb-4">
                            Catalog
                        </div>
                        <h1 class="text-5xl md:text-6xl font-black text-white tracking-tighter uppercase italic">Our <span class="text-brand-primary">Products</span></h1>
                        <p class="text-muted-foreground font-medium mt-4">Browse our collection of high-performance modifications.</p>
                    </div>

                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <div class="relative w-full sm:w-80">
                            <Search class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-muted-foreground" />
                            <Input 
                                v-model="searchQuery"
                                placeholder="Search products..." 
                                class="h-14 pl-12 pr-4 rounded-[1.5rem] bg-white/5 border-white/10 hover:border-white/20 focus:border-brand-primary/50 transition-all font-bold text-sm"
                            />
                        </div>
                        <Button variant="outline" class="h-14 px-6 rounded-[1.5rem] border-white/10 hover:bg-white/5 text-white font-black uppercase text-xs tracking-widest">
                            <SlidersHorizontal class="size-4 mr-2" />
                            Filters
                        </Button>
                    </div>
                </div>

                <!-- Categories -->
                <div class="flex items-center gap-2 overflow-x-auto pb-8 no-scrollbar mb-12">
                    <button 
                        v-for="cat in categories" 
                        :key="cat"
                        @click="selectedCategory = cat"
                        :class="[
                            'px-8 py-3 rounded-2xl font-black uppercase text-[10px] tracking-widest transition-all whitespace-nowrap border active:scale-95',
                            selectedCategory === cat 
                                ? 'bg-brand-primary border-brand-primary text-white shadow-xl shadow-brand-primary/20' 
                                : 'bg-white/5 border-white/5 text-muted-foreground hover:bg-white/10 hover:border-white/10'
                        ]"
                    >
                        {{ cat }}
                    </button>
                </div>

                <!-- Products Grid -->
                <div v-if="filteredProducts.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
                    <ProductCard v-for="product in ((filteredProducts as any)?.data || filteredProducts || [])" :key="product.id" :product="product" />
                </div>

                <!-- Empty State -->
                <div v-else class="py-40 text-center bg-white/5 rounded-[4rem] border border-dashed border-white/10">
                    <div class="size-24 rounded-[2.5rem] bg-brand-primary/10 flex items-center justify-center text-brand-primary mx-auto mb-8 shadow-2xl shadow-brand-primary/10">
                        <Search class="size-10" />
                    </div>
                    <h2 class="text-3xl font-black text-white mb-4">No Products Found</h2>
                    <p class="text-muted-foreground font-medium">Try adjusting your search or filters to find what you're looking for.</p>
                </div>

                <!-- Trust Section -->
                <div class="mt-32 p-12 md:p-20 bg-gradient-to-br from-brand-primary/10 to-transparent border border-brand-primary/20 rounded-[4rem] flex flex-col md:flex-row items-center justify-between gap-12 overflow-hidden relative">
                    <div class="absolute -right-20 -bottom-20 size-80 bg-brand-primary/20 blur-[100px] rounded-full"></div>
                    
                    <div class="relative z-10 max-w-xl">
                        <h2 class="text-4xl font-black text-white mb-6 tracking-tighter italic uppercase">NOT FINDING WHAT YOU NEED?</h2>
                        <p class="text-muted-foreground font-medium leading-relaxed">
                            Our team is constantly developing new solutions. Join our Discord to request features or be the first to know about upcoming releases.
                        </p>
                    </div>
                    
                    <a href="https://discord.gg/rlbmods" target="_blank" class="relative z-10">
                        <Button class="h-16 px-12 rounded-[2rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-sm tracking-widest border-b-8 border-brand-primary/50 transition-all">
                            Join Community
                        </Button>
                    </a>
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
