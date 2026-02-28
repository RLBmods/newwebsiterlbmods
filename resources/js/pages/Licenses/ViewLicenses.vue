<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { index as licensesIndex } from '@/routes/licenses';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Package, 
    Copy, 
    CheckCircle2, 
    Clock, 
    User,
    ArrowLeft,
    Search,
    RotateCcw,
    SlidersHorizontal,
    RefreshCw,
    ShieldAlert
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuTrigger,
    DropdownMenuSeparator,
    DropdownMenuLabel 
} from '@/components/ui/dropdown-menu';
import { useToast } from 'vue-toastification';
import { useForm } from '@inertiajs/vue3';
import { post as resetPost } from '@/routes/licenses/reset';

const props = defineProps<{
    product: any;
    licenses: any[];
    is_reseller: boolean;
}>();

const toast = useToast();
const searchQuery = ref('');
const sortBy = ref('latest');
const copiedKey = ref<string | null>(null);
const allCopied = ref(false);

const resetForm = useForm({
    product_id: props.product.id,
    license_key: '',
});

const sortOptions = [
    { label: 'Latest Purchase', value: 'latest' },
    { label: 'Oldest Purchase', value: 'oldest' },
    { label: 'Status: Active First', value: 'active' },
    { label: 'Status: Inactive First', value: 'inactive' },
    { label: 'Expiration: Soonest', value: 'time_asc' },
    { label: 'Expiration: Furthest', value: 'time_desc' },
];

const filteredLicenses = computed(() => {
    let result = [...props.licenses];

    // Search filter
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(l => 
            l.key.toLowerCase().includes(query) || 
            l.status.toLowerCase().includes(query)
        );
    }

    // Sorting logic
    result.sort((a, b) => {
        switch (sortBy.value) {
            case 'latest':
                return b.raw_generated_at - a.raw_generated_at;
            case 'oldest':
                return a.raw_generated_at - b.raw_generated_at;
            case 'active':
                return (a.status.toLowerCase().includes('active') ? -1 : 1) - 
                       (b.status.toLowerCase().includes('active') ? -1 : 1);
            case 'inactive':
                return (a.status.toLowerCase().includes('inactive') ? -1 : 1) - 
                       (b.status.toLowerCase().includes('inactive') ? -1 : 1);
            case 'time_asc':
                return a.raw_expires_at - b.raw_expires_at;
            case 'time_desc':
                return b.raw_expires_at - a.raw_expires_at;
            default:
                return 0;
        }
    });

    return result;
});

const handleReset = (key: string) => {
    if (!confirm(`Are you sure you want to reset the HWID for license: ${key}?`)) return;

    resetForm.license_key = key;
    resetForm.post(resetPost().url, {
        onSuccess: () => {
            toast.success('HWID reset successfully processed.');
        },
        onError: (errors) => {
            toast.error(errors.message || 'Failed to reset HWID.');
        }
    });
};

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

        toast.success('Key copied to clipboard!');
    } catch (err) {
        toast.error('Failed to copy key.');
    }
};

const copyAll = () => {
    if (props.licenses.length > 0) {
        const textToCopy = props.licenses.map(l => l.key).join('\n');
        copyToClipboard(textToCopy);
        allCopied.value = true;
        setTimeout(() => allCopied.value = false, 2000);
    }
};

const getStatusColor = (status: string) => {
    const s = status.toLowerCase();
    if (s.includes('active')) return 'text-emerald-400 bg-emerald-400/10 border-emerald-400/20';
    if (s.includes('inactive') || s.includes('unused')) return 'text-zinc-400 bg-zinc-400/10 border-zinc-400/20';
    if (s.includes('expired')) return 'text-brand-primary bg-brand-primary/10 border-brand-primary/20';
    return 'text-zinc-400 bg-zinc-400/10 border-zinc-400/20';
};

</script>

<template>
    <AppLayout :title="`Licenses - ${product.name}`">
        <Head :title="`Licenses - ${product.name}`" />

        <div class="max-w-6xl mx-auto px-4 py-8">
            <div class="flex items-center gap-4 mb-8">
                <Link :href="licensesIndex().url" class="p-2 rounded-xl bg-white/5 hover:bg-white/10 text-zinc-400 transition-colors">
                    <ArrowLeft class="h-5 w-5" />
                </Link>
                <div>
                    <h1 class="text-3xl font-bold text-white mb-1">{{ product.name }}</h1>
                    <p class="text-zinc-500">Manage your generated keys for this product.</p>
                </div>
            </div>

            <!-- Stats/Controls Bar -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                <div class="p-5 rounded-3xl bg-[#0f0f0f] border border-white/5 flex items-center gap-4">
                    <div class="h-10 w-10 rounded-xl bg-brand-primary/10 flex items-center justify-center">
                        <Package class="h-5 w-5 text-brand-primary" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Total Keys</p>
                        <p class="text-xl font-black text-white">{{ licenses.length }}</p>
                    </div>
                </div>
                
                <div class="md:col-span-3 flex items-center gap-4">
                    <div class="relative flex-1">
                        <Search class="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-zinc-500" />
                        <input 
                            v-model="searchQuery"
                            type="text" 
                            placeholder="Search by key..." 
                            class="w-full h-full bg-[#0f0f0f] border border-white/5 rounded-2xl py-4 pl-12 pr-4 text-white focus:outline-none focus:ring-1 focus:ring-brand-primary/30 transition-all border-none shadow-inner"
                        />
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="outline" class="h-14 px-5 rounded-2xl border-white/5 bg-[#0f0f0f] hover:bg-white/5 text-zinc-400 gap-2">
                                <SlidersHorizontal class="h-4 w-4 text-brand-primary" />
                                <span class="hidden sm:inline">Sort</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56 bg-[#0f0f0f] border-white/5 text-white">
                            <DropdownMenuLabel class="text-[10px] uppercase tracking-widest text-zinc-600 px-3 py-2">Sort Options</DropdownMenuLabel>
                            <DropdownMenuSeparator class="bg-white/5" />
                            <DropdownMenuItem 
                                v-for="option in sortOptions" 
                                :key="option.value"
                                @click="sortBy = option.value"
                                class="px-3 py-2.5 cursor-pointer hover:bg-white/5 transition-colors flex items-center justify-between"
                                :class="{ 'bg-brand-primary/10 text-brand-primary': sortBy === option.value }"
                            >
                                {{ option.label }}
                                <div v-if="sortBy === option.value" class="h-1.5 w-1.5 rounded-full bg-brand-primary"></div>
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>

                    <Button variant="outline" @click="copyAll" class="h-14 px-6 rounded-2xl border-white/5 bg-[#0f0f0f] hover:bg-white/5 text-white font-bold">
                        <span v-if="!allCopied" class="flex items-center gap-2 uppercase tracking-widest text-[10px]">
                            <Copy class="h-4 w-4" /> Copy All
                        </span>
                        <span v-else class="text-emerald-400 flex items-center gap-2 uppercase tracking-widest text-[10px]">
                            <CheckCircle2 class="h-4 w-4" /> Copied
                        </span>
                    </Button>
                </div>
            </div>

            <!-- Table Container -->
            <div class="rounded-3xl bg-[#0f0f0f] border border-white/5 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-white/5 bg-white/[0.02]">
                                <th class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-zinc-500">License Key</th>
                                <th class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-zinc-500">Status</th>
                                <th class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-zinc-500">Expiration</th>
                                <th class="px-6 py-5 text-[10px] font-bold uppercase tracking-widest text-zinc-500 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/[0.02]">
                            <tr 
                                v-for="license in filteredLicenses" 
                                :key="license.key"
                                class="hover:bg-white/[0.01] transition-colors group"
                            >
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-3">
                                        <code class="text-sm font-mono font-bold text-white group-hover:text-brand-primary transition-colors">
                                            {{ license.key }}
                                        </code>
                                    </div>
                                </td>
                                <td class="px-6 py-5">
                                    <span 
                                        class="px-3 py-1 rounded-full text-[10px] font-bold border transition-all"
                                        :class="getStatusColor(license.status)"
                                    >
                                        {{ license.status.toUpperCase() }}
                                    </span>
                                </td>
                                <td class="px-6 py-5">
                                    <div class="flex items-center gap-2 text-sm text-zinc-400">
                                        <Clock class="h-3.5 w-3.5 text-zinc-600" />
                                        {{ license.expires_at }}
                                    </div>
                                </td>
                                <td class="px-6 py-5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <!-- Reset HWID Button -->
                                        <Button 
                                            v-if="is_reseller && product.type !== 'stock'"
                                            variant="ghost" 
                                            size="sm"
                                            class="h-8 px-3 rounded-lg hover:bg-brand-primary/10 hover:text-brand-primary text-zinc-600 transition-all flex items-center gap-2 border border-transparent hover:border-brand-primary/20"
                                            @click="handleReset(license.key)"
                                            :disabled="resetForm.processing"
                                        >
                                            <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': resetForm.processing && resetForm.license_key === license.key }" />
                                            <span class="text-[10px] font-bold uppercase tracking-widest">Reset</span>
                                        </Button>

                                        <Button 
                                            variant="ghost" 
                                            size="sm"
                                            class="h-8 p-2 rounded-lg hover:bg-brand-primary/10 hover:text-brand-primary transition-all relative"
                                            :class="copiedKey === license.key ? 'text-emerald-400' : 'text-zinc-600'"
                                            @click="copyToClipboard(license.key)"
                                        >
                                            <Copy v-if="copiedKey !== license.key" class="h-4 w-4" />
                                            <CheckCircle2 v-else class="h-4 w-4" />
                                        </Button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Empty State -->
                <div v-if="licenses.length === 0" class="py-20 flex flex-col items-center justify-center text-center space-y-4">
                    <div class="h-16 w-16 rounded-full bg-white/5 flex items-center justify-center mb-2">
                        <CheckCircle2 class="h-8 w-8 text-zinc-800" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-white font-bold">No licenses found</h3>
                        <p class="text-sm text-zinc-500">Refresh the page or generate some keys first.</p>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
