<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { create as createLicense, show as showLicense } from '@/routes/licenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Package, 
    ChevronRight, 
    Search,
    Filter,
    ArrowUpRight
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { ref, computed } from 'vue';

const props = defineProps<{
    products: any[];
}>();

const searchQuery = ref('');

const filteredProducts = computed(() => {
    return props.products.filter(p => 
        p.name.toLowerCase().includes(searchQuery.value.toLowerCase())
    );
});

</script>

<template>
    <AppLayout title="My Licenses">
        <Head title="My Licenses" />

        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">My Licenses</h1>
                    <p class="text-zinc-500">Manage and view your generated license keys.</p>
                </div>
                
                <Link :href="createLicense().url">
                    <Button class="bg-brand-primary hover:bg-brand-primary/90 text-white font-bold rounded-xl px-6">
                        Generate New
                    </Button>
                </Link>
            </div>

            <!-- Search and Filter -->
            <div class="relative mb-6">
                <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                <input 
                    v-model="searchQuery"
                    type="text" 
                    placeholder="Search products..." 
                    class="w-full bg-[#0f0f0f] border border-white/5 rounded-2xl py-4 pl-12 pr-4 text-white focus:outline-none focus:ring-2 focus:ring-brand-primary/50 transition-all"
                />
            </div>

            <div v-if="filteredProducts.length === 0" class="py-20 flex flex-col items-center justify-center text-center space-y-4 rounded-3xl border-2 border-dashed border-white/5">
                <Package class="h-12 w-12 text-zinc-800" />
                <div class="space-y-1">
                    <h3 class="text-white font-bold">No licenses found</h3>
                    <p class="text-sm text-zinc-500">You haven't generated any licenses yet or no matches found.</p>
                </div>
                <Link :href="createLicense().url" v-if="products.length === 0">
                    <Button variant="outline" class="border-white/5 hover:bg-white/5 text-zinc-400">
                        Generate your first license
                    </Button>
                </Link>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                <Link 
                    v-for="product in filteredProducts" 
                    :key="product.id"
                    :href="showLicense(product.id).url"
                    class="group block relative p-6 rounded-3xl bg-[#0f0f0f] border border-white/5 hover:border-brand-primary/50 transition-all overflow-hidden"
                >
                    <div class="space-y-4">
                        <div class="flex items-start justify-between">
                            <div class="h-12 w-12 rounded-2xl bg-brand-primary/10 flex items-center justify-center group-hover:scale-110 transition-transform">
                                <Package class="h-6 w-6 text-brand-primary" />
                            </div>
                            <ArrowUpRight class="h-5 w-5 text-zinc-700 group-hover:text-brand-primary group-hover:translate-x-1 group-hover:-translate-y-1 transition-all" />
                        </div>

                        <div>
                            <h3 class="text-xl font-bold text-white group-hover:text-brand-primary transition-colors">
                                {{ product.name }}
                            </h3>
                            <p class="text-sm text-zinc-500 line-clamp-2 mt-1">
                                {{ product.description || 'View and manage your active keys for this product.' }}
                            </p>
                        </div>
                    </div>

                    <!-- Decorative Background element -->
                    <div class="absolute -bottom-8 -right-8 h-24 w-24 bg-brand-primary/5 blur-3xl rounded-full group-hover:bg-brand-primary/10 transition-all"></div>
                </Link>
            </div>
        </div>
    </AppLayout>
</template>
