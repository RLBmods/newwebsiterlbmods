<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type Product } from '@/types';
import { Download, Video, Calendar, HardDrive, Info, AlertTriangle } from 'lucide-vue-next';
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import downloads from '@/routes/downloads';
import licenses from '@/routes/licenses';

interface Props {
    products: Product[];
}

defineProps<Props>();
const toast = useToast();
const processingId = ref<number | null>(null);

const getStatusInfo = (status: number) => {
    const statuses = {
        1: { text: 'UNDETECTED', class: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20' },
        2: { text: 'USE AT OWN RISK', class: 'bg-amber-500/10 text-amber-400 border-amber-500/20' },
        3: { text: 'TESTING', class: 'bg-blue-500/10 text-blue-400 border-blue-500/20' },
        4: { text: 'UPDATING', class: 'bg-brand-primary/10 text-brand-primary border-brand-primary/20' },
        5: { text: 'OFFLINE', class: 'bg-rose-500/10 text-rose-400 border-rose-500/20' },
        6: { text: 'IN-DEVELOPMENT', class: 'bg-cyan-500/10 text-cyan-400 border-cyan-500/20' },
    };
    return statuses[status as keyof typeof statuses] || statuses[5];
};

const formatDate = (date: string) => {
    return new Date(date).toLocaleDateString();
};

const downloadProduct = async (product: Product) => {
    if (processingId.value) return;

    if (!product.download_url) {
        toast.error('No download file has been assigned to this product yet.');
        return;
    }
    
    processingId.value = product.id;
    try {
        const response = await axios.post(downloads.key(product.id).url);
        if (response.data.success) {
            window.location.href = response.data.download_url;
            toast.success('Download started!');
        }
    } catch (error: any) {
        toast.error(error.response?.data?.error || 'Failed to initiate download.');
    } finally {
        processingId.value = null;
    }
};

const getFileType = (product: Product): string => {
    if (!product.download_url) return 'N/A';
    const ext = product.download_url.split('.').pop()?.toUpperCase();
    return ext && ['EXE', 'ZIP'].includes(ext) ? ext : 'EXE';
};
</script>

<template>
    <AppLayout>
        <Head title="Downloads" />

        <div class="py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto">
                <!-- Header Section -->
                <div class="mb-12">
                    <h1 class="text-3xl font-bold text-white mb-2 flex items-center gap-3">
                        <Download class="w-8 h-8 text-brand-primary" />
                        Software Downloads
                    </h1>
                    <p class="text-gray-400 max-w-2xl">
                        Access your authorized game enhancements. All downloads are cryptographically signed and secured.
                    </p>
                </div>

                <!-- Products Grid -->
                <div v-if="products.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <div 
                        v-for="product in products" 
                        :key="product.id"
                        class="group relative bg-[#0a0a0a]/60 backdrop-blur-xl border border-white/5 rounded-2xl overflow-hidden hover:border-brand-primary/30 transition-all duration-300 flex flex-col"
                    >
                        <!-- Card Banner/Background -->
                        <div class="h-48 w-full relative overflow-hidden">
                            <img 
                                :src="product.image_url || '/placeholder.jpg'" 
                                :alt="product.name"
                                class="w-full h-full object-cover grayscale-[20%] group-hover:scale-105 transition-transform duration-500"
                            />
                            <div class="absolute inset-0 bg-gradient-to-t from-[#0a0a0a] via-[#0a0a0a]/40 to-transparent" />
                            
                            <!-- Status Badge -->
                            <div class="absolute top-4 right-4 animate-in fade-in zoom-in duration-500">
                                <span 
                                    class="px-3 py-1 rounded-full text-[10px] font-bold border backdrop-blur-md tracking-wider flex items-center gap-1.5"
                                    :class="getStatusInfo(product.status || 5).class"
                                >
                                    <div class="w-1.5 h-1.5 rounded-full bg-current animate-pulse" />
                                    {{ getStatusInfo(product.status || 5).text }}
                                </span>
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 flex-grow flex flex-col">
                            <div class="flex justify-between items-start mb-4">
                                <h3 class="text-xl font-bold text-white">{{ product.name }}</h3>
                                <span class="text-brand-primary font-mono text-xs bg-brand-primary/10 px-2 py-0.5 rounded border border-brand-primary/20">
                                    v{{ product.version }}
                                </span>
                            </div>

                            <p class="text-gray-400 text-sm line-clamp-2 mb-6 flex-grow">
                                {{ product.description || 'Access and manage your ' + product.name + ' software components.' }}
                            </p>

                            <!-- Meta Info -->
                            <div class="space-y-3 mb-8">
                                <div class="flex items-center text-xs text-gray-500 gap-2">
                                    <Calendar class="w-3.5 h-3.5" />
                                    Updated: {{ formatDate(product.updated_at) }}
                                </div>
                                <div class="flex items-center text-xs text-gray-500 gap-2">
                                    <HardDrive class="w-3.5 h-3.5" />
                                    File Type: 
                                    <span v-if="product.download_url" class="text-white font-mono">.{{ getFileType(product) }}</span>
                                    <span v-else class="text-amber-400 flex items-center gap-1">
                                        <AlertTriangle class="w-3 h-3" /> No file assigned
                                    </span>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex gap-3">
                                <button
                                    @click="downloadProduct(product)"
                                    :disabled="product.status === 5 || !product.download_url || processingId === product.id"
                                    class="flex-grow flex items-center justify-center gap-2 px-6 py-3 rounded-xl font-bold text-sm transition-all duration-200"
                                    :class="[
                                        (product.status === 5 || !product.download_url)
                                            ? 'bg-gray-800 text-gray-500 cursor-not-allowed opacity-50'
                                            : 'bg-brand-primary text-white hover:bg-brand-primary/80 shadow-lg shadow-brand-primary/20 active:scale-95'
                                    ]"
                                >
                                    <template v-if="processingId === product.id">
                                        <div class="w-4 h-4 border-2 border-white/20 border-t-white rounded-full animate-spin" />
                                        PREPARING...
                                    </template>
                                    <template v-else>
                                        <Download class="w-4 h-4" />
                                        DOWNLOAD
                                    </template>
                                </button>

                                <a 
                                    v-if="product.tutorial_link"
                                    :href="product.tutorial_link"
                                    target="_blank"
                                    class="p-3 bg-white/5 text-gray-400 hover:text-white hover:bg-white/10 rounded-xl border border-white/5 transition-all"
                                    title="View Tutorial"
                                >
                                    <Video class="w-5 h-5" />
                                </a>
                            </div>
                        </div>

                        <!-- Glow Effect on Hover -->
                        <div class="absolute inset-0 -z-10 bg-brand-primary/5 opacity-0 group-hover:opacity-100 blur-3xl transition-opacity duration-300" />
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="flex flex-col items-center justify-center py-20 text-center">
                    <div class="w-20 h-20 bg-white/5 rounded-full flex items-center justify-center mb-6">
                        <Info class="w-10 h-10 text-gray-500" />
                    </div>
                    <h3 class="text-xl font-bold text-white mb-2">No Downloads Available</h3>
                    <p class="text-gray-400 max-w-md">
                        You don't currently have access to any software products. 
                        Please purchase a license or contact support if you believe this is an error.
                    </p>
                    <a 
                        :href="licenses.create().url"
                        class="mt-8 px-8 py-3 bg-white/5 text-white hover:bg-white/10 rounded-xl font-bold border border-white/10 transition-all"
                    >
                        Browse Store
                    </a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
