<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { 
    ShoppingBag, 
    Download, 
    Key, 
    Wallet, 
    ChevronRight, 
    PlusCircle,
    LayoutGrid,
    History,
    ExternalLink
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Loader2 } from 'lucide-vue-next';

interface Product {
    id: number;
    name: string;
}

interface Purchase {
    id: number;
    amount_paid: string;
    status: string;
    created_at: string;
    product: Product;
}

interface Stats {
    total_licenses: number;
    active_licenses: number;
    balance: number;
}

const props = defineProps<{
    stats: Stats;
    recentPurchases: Purchase[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reseller Portal',
        href: '#',
    },
    {
        title: 'Dashboard',
        href: '/reseller/dashboard',
    },
];

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date);
};

const toast = useToast();
const isTopupModalOpen = ref(false);
const topupAmount = ref(10);
const processingTopup = ref(false);

const handleTopup = async () => {
    if (topupAmount.value < 1) {
        toast.error('Minimum top-up amount is $1.00');
        return;
    }

    processingTopup.value = true;
    try {
        const response = await axios.post(route('reseller.topup.store'), {
            amount: topupAmount.value
        });

        if (response.data.redirectUrl) {
            window.location.href = response.data.redirectUrl;
        }
    } catch (error: any) {
        toast.error(error.response?.data?.message || 'Failed to initiate top-up.');
    } finally {
        processingTopup.value = false;
    }
};
</script>

<template>
    <Head title="Reseller Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-8 bg-brand-bg/30">
            
            <!-- Welcome Section -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-white">Reseller Portal</h1>
                    <p class="text-muted-foreground font-bold">Manage your licenses and track your sales performance.</p>
                </div>
                <div class="flex items-center gap-3">
                    <Link href="/licenses/create">
                        <Button class="bg-brand-primary hover:bg-brand-primary/90 text-white font-black h-11 px-6 rounded-2xl shadow-lg shadow-brand-primary/20 transition-all active:scale-95 flex gap-2">
                            <PlusCircle class="size-5" />
                            Generate Licenses
                        </Button>
                    </Link>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <!-- Wallet Balance -->
                <div class="group relative overflow-hidden rounded-[2.5rem] bg-sidebar/40 p-8 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">Available Credits</p>
                            <p class="mt-4 text-4xl font-black text-white tracking-tight">${{ Number(stats.balance).toFixed(2) }}</p>
                            <Button 
                                @click="isTopupModalOpen = true"
                                variant="outline" 
                                size="sm" 
                                class="mt-4 bg-brand-primary/5 border-brand-primary/20 text-brand-primary hover:bg-brand-primary hover:text-white font-black rounded-xl h-9 px-4 transition-all"
                            >
                                <PlusCircle class="size-3.5 mr-2" />
                                Top Up Credits
                            </Button>
                        </div>
                        <div class="h-16 w-16 rounded-[1.5rem] bg-emerald-500/10 flex items-center justify-center text-emerald-500 border border-emerald-500/20 group-hover:bg-emerald-500 group-hover:text-white transition-all duration-500 shadow-2xl">
                            <Wallet class="h-8 w-8" />
                        </div>
                    </div>
                </div>

                <!-- Total Licenses -->
                <div class="group relative overflow-hidden rounded-[2.5rem] bg-sidebar/40 p-8 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">Total Keys Generated</p>
                            <p class="mt-4 text-4xl font-black text-white tracking-tight">{{ stats.total_licenses }}</p>
                        </div>
                        <div class="h-16 w-16 rounded-[1.5rem] bg-blue-500/10 flex items-center justify-center text-blue-500 border border-blue-500/20 group-hover:bg-blue-500 group-hover:text-white transition-all duration-500 shadow-2xl">
                            <Key class="h-8 w-8" />
                        </div>
                    </div>
                </div>

                <!-- Active Licenses -->
                <div class="group relative overflow-hidden rounded-[2.5rem] bg-sidebar/40 p-8 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/50">Active Licenses</p>
                            <p class="mt-4 text-4xl font-black text-white tracking-tight">{{ stats.active_licenses }}</p>
                        </div>
                        <div class="h-16 w-16 rounded-[1.5rem] bg-brand-primary/10 flex items-center justify-center text-brand-primary border border-brand-primary/20 group-hover:bg-brand-primary group-hover:text-white transition-all duration-500 shadow-2xl">
                            <ShoppingBag class="h-8 w-8" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Main Content Left -->
                <div class="col-span-1 lg:col-span-2 space-y-8">
                    <!-- Quick Actions -->
                    <div class="rounded-[3rem] bg-sidebar/40 border border-white/5 backdrop-blur-xl p-10">
                        <h2 class="text-2xl font-black text-white mb-8">Fast License Creation</h2>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Purchase Keys -->
                            <Link href="/licenses/create" class="group flex items-center gap-6 bg-white/5 border border-white/5 rounded-[2rem] p-6 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-16 w-16 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 border border-yellow-500/20 group-hover:bg-yellow-500 group-hover:text-white transition-all shadow-xl">
                                    <PlusCircle class="h-8 w-8" />
                                </div>
                                <div>
                                    <span class="block text-lg font-black text-white">Buy Licenses</span>
                                    <span class="text-xs font-bold text-muted-foreground group-hover:text-white/70 transition-colors">Mass generate product keys</span>
                                </div>
                            </Link>

                            <!-- Manage Active Keys -->
                            <Link href="/licenses" class="group flex items-center gap-6 bg-white/5 border border-white/5 rounded-[2rem] p-6 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-16 w-16 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all shadow-xl">
                                    <Key class="h-8 w-8" />
                                </div>
                                <div>
                                    <span class="block text-lg font-black text-white">Manage Keys</span>
                                    <span class="text-xs font-bold text-muted-foreground group-hover:text-white/70 transition-colors">View and reset user keys</span>
                                </div>
                            </Link>

                            <!-- Downloads -->
                            <Link href="/downloads" class="group flex items-center gap-6 bg-white/5 border border-white/5 rounded-[2rem] p-6 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-16 w-16 rounded-2xl bg-brand-primary/10 flex items-center justify-center text-brand-primary border border-brand-primary/20 group-hover:bg-brand-primary group-hover:text-white transition-all shadow-xl">
                                    <Download class="h-8 w-8" />
                                </div>
                                <div>
                                    <span class="block text-lg font-black text-white">Downloads</span>
                                    <span class="text-xs font-bold text-muted-foreground group-hover:text-white/70 transition-colors">Access reseller files</span>
                                </div>
                            </Link>

                            <!-- Documentation -->
                            <a href="https://docs.rlbmods.com" target="_blank" class="group flex items-center gap-6 bg-white/5 border border-white/5 rounded-[2rem] p-6 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-16 w-16 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-500 border border-blue-500/20 group-hover:bg-blue-500 group-hover:text-white transition-all shadow-xl">
                                    <ExternalLink class="h-8 w-8" />
                                </div>
                                <div>
                                    <span class="block text-lg font-black text-white">Documentation</span>
                                    <span class="text-xs font-bold text-muted-foreground group-hover:text-white/70 transition-colors">Reseller API and guides</span>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Right -->
                <div class="space-y-8">
                    <!-- Recent Activity -->
                    <div class="rounded-[3rem] bg-sidebar/40 border border-white/5 backdrop-blur-xl overflow-hidden flex flex-col">
                        <div class="p-10 pb-6 flex items-center justify-between">
                            <h3 class="text-xl font-black text-white">Recent Activity</h3>
                            <History class="size-5 text-muted-foreground/30" />
                        </div>
                        <div class="p-10 pt-0 flex-1 flex flex-col">
                            <div v-if="recentPurchases.length > 0" class="space-y-6">
                                <div v-for="purchase in recentPurchases" :key="purchase.id" class="flex items-center justify-between group">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-white/5 flex items-center justify-center border border-white/10 group-hover:border-brand-primary/30 group-hover:text-brand-primary transition-all shadow-lg">
                                            <ShoppingBag class="h-6 w-6" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-white truncate">{{ purchase.product.name }}</p>
                                            <p class="text-[10px] text-muted-foreground uppercase tracking-widest font-black">{{ formatDate(purchase.created_at) }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-black text-emerald-400">-${{ Number(purchase.amount_paid).toFixed(2) }}</p>
                                        <p class="text-[9px] font-black uppercase text-muted-foreground/50">Credits</p>
                                    </div>
                                </div>
                            </div>
                            <div v-else class="flex-1 flex flex-col items-center justify-center text-center py-12">
                                <div class="h-16 w-16 rounded-full bg-white/5 flex items-center justify-center mb-4">
                                    <History class="h-8 w-8 text-muted-foreground/20" />
                                </div>
                                <p class="text-sm font-bold text-muted-foreground">No recent activity.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Reseller Support -->
                    <div class="rounded-[3rem] bg-brand-primary/5 border border-brand-primary/20 backdrop-blur-xl p-10 group hover:border-brand-primary/40 transition-all">
                        <h3 class="text-xl font-black text-white mb-2">Priority Support</h3>
                        <p class="text-sm font-bold text-muted-foreground mb-6">Need help with a license or have a business inquiry?</p>
                        <Link href="/support">
                            <Button class="w-full bg-black/40 hover:bg-black/60 text-white font-black h-12 rounded-2xl border border-white/5 transition-all active:scale-95">
                                Open Reseller Ticket
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>

    <Dialog v-model:open="isTopupModalOpen">
        <DialogContent class="sm:max-w-[425px] bg-neutral-950 border-white/5 backdrop-blur-3xl">
            <DialogHeader>
                <DialogTitle class="text-2xl font-black text-white italic">TOP UP CREDITS</DialogTitle>
                <DialogDescription class="text-muted-foreground font-bold">
                    Enter the amount you wish to add to your balance. You will be redirected to complete the payment via crypto.
                </DialogDescription>
            </DialogHeader>
            <div class="grid gap-6 py-4">
                <div class="space-y-2">
                    <Label for="amount" class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Amount (USD)</Label>
                    <div class="relative">
                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground font-black italic">$</span>
                        <Input
                            id="amount"
                            v-model="topupAmount"
                            type="number"
                            min="1"
                            step="0.01"
                            class="bg-white/5 border-white/5 h-14 pl-8 rounded-2xl text-lg font-black text-brand-primary focus:ring-brand-primary/20 transition-all"
                        />
                    </div>
                </div>
                
                <div class="p-4 rounded-2xl bg-brand-primary/5 border border-brand-primary/10 flex items-start gap-3">
                    <Wallet class="size-5 text-brand-primary shrink-0 mt-0.5" />
                    <p class="text-xs font-bold text-muted-foreground leading-relaxed">
                        Crypto top-ups are processed via <span class="text-white">NOWPayments</span>. Credits will be added automatically once the transaction is confirmed on the blockchain.
                    </p>
                </div>
            </div>
            <DialogFooter>
                <Button 
                    @click="handleTopup"
                    :disabled="processingTopup"
                    class="w-full bg-brand-primary hover:bg-brand-primary/90 text-white font-black h-14 rounded-2xl shadow-lg shadow-brand-primary/20 transition-all active:scale-95 flex gap-2"
                >
                    <template v-if="processingTopup">
                        <Loader2 class="size-5 animate-spin" />
                        PROCESSING...
                    </template>
                    <template v-else>
                        INITIATE PAYMENT
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
/* Scoped styles for micro-interactions if needed */
</style>
