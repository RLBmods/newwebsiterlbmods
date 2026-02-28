<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { store } from '@/routes/licenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Package, 
    Clock, 
    Hash, 
    CheckCircle2, 
    Copy, 
    ChevronRight, 
    Zap,
    AlertCircle,
    Loader2
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle,
    DialogDescription 
} from '@/components/ui/dialog';
import { useToast } from 'vue-toastification';

const props = defineProps<{
    products: any[];
}>();

const { props: pageProps } = usePage() as any;
const flash = computed(() => pageProps.flash);
const keys = computed(() => flash.value?.keys || []);
const product_name = computed(() => flash.value?.product_name || '');

const toast = useToast();
const selectedProduct = ref<any>(null);
const showSuccessModal = ref(false);
const copiedKey = ref<string | null>(null);
const allCopied = ref(false);

const form = useForm({
    product_id: '',
    duration: 1,
    duration_type: 'days',
    count: 1,
});

const durations = computed(() => {
    if (!selectedProduct.value || !selectedProduct.value.prices) return [];
    
    return selectedProduct.value.prices.map((p: any) => ({
        label: p.duration_type === 'lifetime' ? 'Lifetime' : `${p.duration} ${p.duration_type.charAt(0).toUpperCase() + p.duration_type.slice(1)}`,
        value: p.duration,
        type: p.duration_type,
        price: parseFloat(p.price)
    }));
});

// Watch product change and reset duration if needed
watch(selectedProduct, (newProd) => {
    if (newProd && newProd.prices && newProd.prices.length > 0) {
        form.duration = newProd.prices[0].duration;
        form.duration_type = newProd.prices[0].duration_type;
    }
});

const currentUnitPrice = computed(() => {
    if (!selectedProduct.value || !durations.value.length) return 0;
    const d = durations.value.find((d: any) => d.value === form.duration && d.type === form.duration_type);
    return d ? d.price : 0;
});

const discountInfo = computed(() => {
    const user = pageProps.auth.user;
    let percentage = 0;
    let label = '';

    if (user.role === 'admin') {
        percentage = 100;
        label = 'Admin (100% OFF)';
    } else if (user.role === 'reseller') {
        percentage = form.count >= 10 ? 50 : 40;
        label = form.count >= 10 ? 'Bulk Reseller (50% OFF)' : 'Reseller (40% OFF)';
    }

    return { percentage, label };
});

const subtotal = computed(() => currentUnitPrice.value * form.count);
const totalCost = computed(() => subtotal.value * (1 - (discountInfo.value.percentage / 100)));

const selectProduct = (product: any) => {
    selectedProduct.value = product;
    form.product_id = product.id;
};

const submit = () => {
    form.post(store().url, {
        onSuccess: () => {
            // Success is handled by the watcher on flash props
        },
        onError: (errors) => {
            toast.error(errors.message || 'Failed to generate license.');
        }
    });
};

// Use a watcher to catch flash success data even after redirects
watch(() => flash.value, (newFlash) => {
    if (newFlash?.success) {
        showSuccessModal.value = true;
    }
}, { deep: true, immediate: true });

const copyToClipboard = (text: string) => {
    try {
        const textArea = document.createElement("textarea");
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        
        // Also use modern API if available
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        }

        copiedKey.value = text;
        setTimeout(() => {
            if (copiedKey.value === text) copiedKey.value = null;
        }, 2000);

        toast.success('Copied to clipboard!');
    } catch (err) {
        toast.error('Failed to copy key.');
    }
};

const copyAll = () => {
    if (keys.value.length > 0) {
        copyToClipboard(keys.value.join('\n'));
        allCopied.value = true;
        setTimeout(() => allCopied.value = false, 2000);
    }
};

</script>

<template>
    <AppLayout title="Generate Licenses">
        <Head title="Generate Licenses" />

        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-white mb-2">Generate Licenses</h1>
                <p class="text-zinc-500">Select a product and duration to generate new license keys.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Product Selection Grid -->
                <div class="lg:col-span-2 space-y-4">
                    <h2 class="text-sm font-bold uppercase tracking-widest text-zinc-600 mb-4 flex items-center gap-2">
                        <Package class="h-4 w-4" /> Available Products
                    </h2>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div 
                            v-for="product in products" 
                            :key="product.id"
                            @click="selectProduct(product)"
                            class="group relative p-5 rounded-2xl border transition-all cursor-pointer overflow-hidden"
                            :class="[
                                selectedProduct?.id === product.id 
                                ? 'bg-brand-primary/10 border-brand-primary shadow-[0_0_20px_rgba(178,0,3,0.15)]' 
                                : 'bg-zinc-900/50 border-white/5 hover:border-white/10'
                            ]"
                        >
                            <div class="flex items-start justify-between">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-white group-hover:text-brand-primary transition-colors">
                                        {{ product.name }}
                                    </h3>
                                    <p class="text-xs text-zinc-500 line-clamp-1">{{ product.description || 'Professional software solution' }}</p>
                                </div>
                                <div class="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center group-hover:scale-110 transition-transform">
                                    <Zap class="h-5 w-5 text-zinc-400 group-hover:text-brand-primary" />
                                </div>
                            </div>

                            <div class="mt-4 flex items-center justify-between">
                                <span class="text-lg font-black text-white">${{ product.price }}</span>
                                <div class="flex items-center gap-1 text-[10px] uppercase font-bold text-zinc-600">
                                    <Clock class="h-3 w-3" />
                                    {{ product.type }}
                                </div>
                            </div>

                            <!-- Selected Indicator -->
                            <div v-if="selectedProduct?.id === product.id" class="absolute top-0 right-0 p-2">
                                <CheckCircle2 class="h-4 w-4 text-brand-primary" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Configuration Sidebar -->
                <div class="space-y-6">
                    <div class="p-6 rounded-3xl bg-[#0f0f0f] border border-white/5 space-y-6 sticky top-8">
                        <h2 class="text-sm font-bold uppercase tracking-widest text-zinc-600 flex items-center gap-2">
                            <Clock class="h-4 w-4" /> Configure Order
                        </h2>

                        <div v-if="!selectedProduct" class="py-12 flex flex-col items-center justify-center text-center space-y-4 border-2 border-dashed border-white/5 rounded-2xl">
                            <AlertCircle class="h-8 w-8 text-zinc-800" />
                            <p class="text-xs text-zinc-500 px-4">Select a product from the grid to begin configuration.</p>
                        </div>

                        <div v-else class="space-y-6 animate-in fade-in slide-in-from-bottom-2 duration-300">
                            <!-- Duration Selection -->
                            <div class="space-y-3">
                                <label class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Duration</label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button 
                                        v-for="d in durations" 
                                        :key="d.label"
                                        @click="form.duration = d.value; form.duration_type = d.type"
                                        class="px-4 py-2 rounded-xl text-xs font-bold transition-all border"
                                        :class="[
                                            form.duration === d.value 
                                            ? 'bg-white text-black border-white' 
                                            : 'bg-zinc-900 text-zinc-400 border-white/5 hover:border-white/10'
                                        ]"
                                    >
                                        {{ d.label }}
                                    </button>
                                </div>
                            </div>

                            <!-- Count Selection -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <label class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Quantity (Max 50)</label>
                                    <span v-if="pageProps.auth.user.role === 'reseller' && form.count < 10" class="text-[9px] font-extrabold text-brand-primary animate-pulse">BUY 10+ FOR 50% OFF!</span>
                                    <span v-else-if="pageProps.auth.user.role === 'reseller' && form.count >= 10" class="text-[9px] font-extrabold text-emerald-400">BULK DISCOUNT APPLIED!</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <input 
                                        v-model="form.count" 
                                        type="range" 
                                        min="1" 
                                        max="50" 
                                        class="flex-1 accent-brand-primary h-2 bg-zinc-800 rounded-lg appearance-none cursor-pointer"
                                    />
                                    <input 
                                        v-model.number="form.count"
                                        type="number"
                                        min="1"
                                        max="50"
                                        class="w-12 bg-transparent text-center text-xl font-black text-white border-none focus:ring-0"
                                    />
                                </div>
                            </div>

                            <div class="pt-6 border-t border-white/5 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500 text-sm">Unit Price</span>
                                    <span class="text-white font-bold">${{ currentUnitPrice.toFixed(2) }}</span>
                                </div>
                                <div v-if="discountInfo.percentage > 0" class="flex items-center justify-between">
                                    <span class="text-zinc-500 text-sm">Discount</span>
                                    <span class="text-emerald-400 font-bold">-{{ discountInfo.percentage }}% ({{ discountInfo.label }})</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-zinc-500 text-sm uppercase text-[10px] font-bold tracking-widest">Total Cost</span>
                                    <span class="text-2xl font-black text-brand-primary">${{ totalCost.toFixed(2) }}</span>
                                </div>

                                <Button 
                                    @click="submit" 
                                    class="w-full h-14 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-black text-lg shadow-[0_8px_30px_rgba(178,0,3,0.3)] group"
                                    :disabled="form.processing"
                                >
                                    <Loader2 v-if="form.processing" class="mr-2 h-5 w-5 animate-spin" />
                                    <template v-else>
                                        GENERATE NOW
                                        <ChevronRight class="ml-2 h-5 w-5 group-hover:translate-x-1 transition-transform" />
                                    </template>
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Success Modal -->
        <Dialog v-model:open="showSuccessModal">
            <DialogContent class="sm:max-w-md bg-[#0f0f0f] border-white/5 text-white rounded-3xl overflow-hidden p-0">
                <div class="p-8 space-y-6">
                    <div class="flex flex-col items-center text-center space-y-4">
                        <div class="h-20 w-20 rounded-full bg-brand-primary/10 flex items-center justify-center">
                            <CheckCircle2 class="h-10 w-10 text-brand-primary" />
                        </div>
                        <DialogHeader>
                            <DialogTitle class="text-2xl font-black">Success!</DialogTitle>
                            <DialogDescription class="text-zinc-500">
                                Your licenses for <span class="text-white font-bold">{{ product_name }}</span> are ready.
                            </DialogDescription>
                        </DialogHeader>
                    </div>

                    <div class="space-y-3">
                        <div 
                            v-for="key in keys" 
                            :key="key"
                            class="group flex items-center justify-between p-4 bg-zinc-900/50 border border-white/5 rounded-2xl hover:border-brand-primary/50 transition-all cursor-pointer relative"
                            @click="copyToClipboard(key)"
                        >
                            <code class="text-sm font-mono text-brand-primary font-bold">{{ key }}</code>
                            <div class="flex items-center gap-2">
                                <transition name="fade">
                                    <span v-if="copiedKey === key" class="text-[10px] text-emerald-400 font-bold uppercase tracking-wider">Copied!</span>
                                </transition>
                                <Copy v-if="copiedKey !== key" class="h-4 w-4 text-zinc-600 group-hover:text-white transition-colors" />
                                <CheckCircle2 v-else class="h-4 w-4 text-emerald-400" />
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-4">
                        <Button variant="outline" @click="copyAll" class="rounded-xl border-white/5 hover:bg-white/5 text-xs font-bold py-6 relative overflow-hidden">
                            <span v-if="!allCopied">COPY ALL</span>
                            <span v-else class="text-emerald-400 flex items-center gap-2">
                                <CheckCircle2 class="h-4 w-4" /> ALL COPIED
                            </span>
                        </Button>
                        <Button @click="showSuccessModal = false" class="rounded-xl bg-white text-black hover:bg-white/90 text-xs font-bold py-6">
                            DONE
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.accent-brand-primary {
    --tw-accent-color: #b20003;
}
</style>
