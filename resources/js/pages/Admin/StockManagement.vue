<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { 
    Boxes, 
    PlusCircle, 
    Import, 
    Clock, 
    Key, 
    AlertCircle,
    CheckCircle2
} from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import admin from '@/routes/admin';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';

interface Price {
    id: number;
    duration: number;
    duration_type: string;
}

interface Product {
    id: number;
    name: string;
    prices: Price[];
}

interface Props {
    products: Product[];
}

const props = defineProps<Props>();

const form = useForm({
    product_id: '',
    duration: '',
    duration_type: '',
    keysText: '',
});

const selectedProduct = computed(() => 
    props.products.find(p => p.id === Number(form.product_id))
);

const availableDurations = computed(() => 
    selectedProduct.value?.prices || []
);

// Reset duration when product changes
watch(() => form.product_id, () => {
    form.duration = '';
    form.duration_type = '';
});

// Update duration_type when duration is selected
const onDurationChange = (e: Event) => {
    const value = (e.target as HTMLSelectElement).value;
    if (!value) return;
    
    const [duration, type] = value.split('-');
    form.duration = duration;
    form.duration_type = type;
};

const submit = () => {
    form.post(admin.stock.store().url, {
        onSuccess: () => {
            form.reset('keysText');
        },
    });
};

const getDurationLabel = (duration: number, type: string) => {
    if (duration >= 9999 || type === 'lifetime') return 'Lifetime';
    const unit = duration === 1 ? type.slice(0, -1) : type;
    return `${duration} ${unit}`;
};
</script>

<template>
    <Head title="Stock Management" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-4xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col gap-2">
                    <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic flex items-center gap-3">
                        <Boxes class="h-10 w-10 text-brand-primary" />
                        Stock Management
                    </h1>
                    <p class="text-muted-foreground">Upload bulk license keys for manual stock products</p>
                </div>

                <Card class="bg-white/5 border-white/10 backdrop-blur-xl overflow-hidden shadow-2xl">
                    <CardHeader class="border-b border-white/5 bg-white/[0.02] p-8">
                        <div class="flex items-center gap-3 mb-2">
                            <PlusCircle class="h-6 w-6 text-brand-primary" />
                            <CardTitle class="text-2xl font-black text-white uppercase italic tracking-tight">Bulk Import Keys</CardTitle>
                        </div>
                        <CardDescription class="text-muted-foreground">Add new stock by selecting a product and pasting keys.</CardDescription>
                    </CardHeader>
                    
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-8">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                <div class="space-y-3">
                                    <Label class="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                        <Boxes class="h-3.5 w-3.5" /> Target Product
                                    </Label>
                                    <select 
                                        v-model="form.product_id"
                                        class="w-full h-12 rounded-xl border border-white/10 bg-white/5 px-4 py-1 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-primary/50 transition-all custom-select appearance-none"
                                        required
                                    >
                                        <option value="" disabled selected>-- Select a Product --</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id" class="bg-neutral-900">
                                            {{ product.name }}
                                        </option>
                                    </select>
                                </div>

                                <div class="space-y-3">
                                    <Label class="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                        <Clock class="h-3.5 w-3.5" /> Duration
                                    </Label>
                                    <select 
                                        :disabled="!form.product_id"
                                        @change="onDurationChange"
                                        class="w-full h-12 rounded-xl border border-white/10 bg-white/5 px-4 py-1 text-sm text-white focus:outline-none focus:ring-2 focus:ring-brand-primary/50 transition-all custom-select appearance-none disabled:opacity-50 disabled:cursor-not-allowed"
                                        required
                                    >
                                        <option value="" disabled selected>Select Duration</option>
                                        <option 
                                            v-for="p in availableDurations" 
                                            :key="p.id" 
                                            :value="`${p.duration}-${p.duration_type}`"
                                            class="bg-neutral-900"
                                        >
                                            {{ getDurationLabel(p.duration, p.duration_type) }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="space-y-3">
                                <Label class="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground flex items-center gap-2">
                                    <Key class="h-3.5 w-3.5" /> License Keys (One per line)
                                </Label>
                                <div class="relative group">
                                    <Textarea 
                                        v-model="form.keysText"
                                        rows="10" 
                                        placeholder="FN-KEY-1234&#10;FN-KEY-5678" 
                                        class="bg-white/5 border-white/10 rounded-xl focus:ring-2 focus:ring-brand-primary/50 transition-all min-h-[300px] font-mono text-sm p-6"
                                        required
                                    />
                                    <div class="absolute inset-x-0 bottom-0 p-4 bg-gradient-to-t from-black/80 to-transparent pointer-events-none rounded-b-xl opacity-0 group-hover:opacity-100 transition-opacity">
                                        <p class="text-[10px] text-white/50 font-bold uppercase tracking-widest flex items-center gap-2">
                                            <AlertCircle class="h-3 w-3" /> System cleans line numbers automatically
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4">
                                <Button 
                                    type="submit" 
                                    class="w-full md:w-auto bg-brand-primary hover:bg-brand-primary/90 text-white px-10 py-6 rounded-xl font-black uppercase italic tracking-tighter text-lg shadow-[0_10px_30px_rgba(178,0,3,0.3)] transition-all hover:-translate-y-1 active:translate-y-0"
                                    :disabled="form.processing"
                                >
                                    <Import v-if="!form.processing" class="mr-2 h-6 w-6" />
                                    <div v-else class="mr-2 h-5 w-5 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                                    {{ form.processing ? 'Importing Stock...' : 'Import Stock' }}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Help Alert -->
                <div class="bg-blue-500/10 border border-blue-500/20 rounded-2xl p-6 flex gap-4 items-start">
                    <div class="bg-blue-500/20 p-2 rounded-lg">
                        <AlertCircle class="h-5 w-5 text-blue-400" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-bold text-blue-400 uppercase tracking-widest">Key Formatting</p>
                        <p class="text-sm text-blue-100/70 leading-relaxed">
                            Pasted keys can include line numbers like <code class="bg-blue-500/20 px-1 rounded">1. KEY-123</code> or <code class="bg-blue-500/20 px-1 rounded">2) KEY-456</code>. 
                            The system will automatically strip these prefixes and only store the keys. Each line is treated as a single stock item.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.custom-select {
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1.25em;
}

select:focus {
    border-color: rgba(178, 0, 3, 0.5) !important;
}

textarea:focus {
    border-color: rgba(178, 0, 3, 0.5) !important;
}
</style>
