<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    Receipt, 
    Search, 
    Filter, 
    Eye,
    CheckCircle2,
    Clock,
    XCircle,
    RotateCcw,
    User as UserIcon,
    ArrowUpDown
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import admin from '@/routes/admin';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogDescription,
    DialogFooter 
} from '@/components/ui/dialog';
import Pagination from '@/components/Pagination.vue';

interface Transaction {
    id: number;
    user_name: string;
    user_email: string;
    amount: number;
    status: string;
    payment_method: string;
    details: string;
    created_at: string;
}

interface Props {
    transactions: {
        data: Transaction[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        status?: string;
        search?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const status = ref(props.filters.status || '');
const selectedTransaction = ref<Transaction | null>(null);
const isDetailsModalOpen = ref(false);

const updateFilters = () => {
    router.get(admin.transactions.index().url, {
        search: search.value,
        status: status.value,
    }, {
        preserveState: true,
        replace: true,
    });
};

watch([search, status], () => {
    updateFilters();
});

const openDetailsModal = (txn: Transaction) => {
    selectedTransaction.value = txn;
    isDetailsModalOpen.value = true;
};

const getStatusIcon = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed': return CheckCircle2;
        case 'pending': return Clock;
        case 'failed': return XCircle;
        case 'refunded': return RotateCcw;
        default: return Receipt;
    }
};

const getStatusColorClass = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
        case 'pending': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'failed': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'refunded': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        default: return 'bg-white/10 text-muted-foreground border-white/20';
    }
};

const formatDetails = (details: string ) => {
    try {
        const parsed = JSON.parse(details);
        return JSON.stringify(parsed, null, 2);
    } catch (e) {
        return details;
    }
};
</script>

<template>
    <Head title="Transaction History" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2 flex items-center gap-3">
                            <Receipt class="h-10 w-10 text-brand-primary" />
                            Transactions
                        </h1>
                        <p class="text-muted-foreground">Monitor and manage all payment transactions</p>
                    </div>
                </div>

                <!-- Filters -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardContent class="p-4">
                        <div class="flex flex-col md:flex-row items-center gap-4">
                            <div class="relative flex-1 w-full">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input 
                                    v-model="search"
                                    placeholder="Search by ID, user, or method..." 
                                    class="pl-10 bg-white/5 border-white/10 h-11"
                                />
                            </div>
                            <div class="flex items-center gap-4 w-full md:w-auto">
                                <select 
                                    v-model="status"
                                    class="h-11 rounded-md border border-white/10 bg-white/5 px-4 py-1 text-sm text-white focus:outline-none focus:ring-1 focus:ring-brand-primary custom-select w-full md:w-48 appearance-none"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="completed">Completed</option>
                                    <option value="pending">Pending</option>
                                    <option value="failed">Failed</option>
                                    <option value="refunded">Refunded</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Transactions Table -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 bg-white/[0.02]">
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">ID</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">User</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground text-right">Amount</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Method</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Date</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="txn in transactions.data" :key="txn.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="text-xs font-mono text-white/50">#{{ txn.id }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="size-8 rounded-full bg-white/5 flex items-center justify-center border border-white/10 group-hover:border-brand-primary/50 transition-colors">
                                                <UserIcon class="h-4 w-4 text-white/40" />
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-white italic leading-tight">{{ txn.user_name }}</p>
                                                <p class="text-[10px] text-muted-foreground">{{ txn.user_email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <span class="text-sm font-black tracking-tight" :class="txn.amount < 0 ? 'text-red-400' : 'text-emerald-400'">
                                            {{ txn.amount < 0 ? '-' : '+' }}${{ Math.abs(txn.amount).toFixed(2) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge variant="outline" class="bg-white/5 border-white/10 text-[10px] font-bold uppercase tracking-widest">
                                            {{ txn.payment_method }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-xs text-muted-foreground">
                                        {{ txn.created_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-2 px-2.5 py-1 rounded-lg border text-[10px] font-black uppercase italic tracking-wider transition-all"
                                            :class="getStatusColorClass(txn.status)">
                                            <component :is="getStatusIcon(txn.status)" class="h-3 w-3" />
                                            {{ txn.status }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <Button 
                                            @click="openDetailsModal(txn)"
                                            variant="ghost" 
                                            size="icon" 
                                            class="size-8 hover:bg-brand-primary hover:text-white transition-all rounded-lg"
                                        >
                                            <Eye class="h-4 w-4" />
                                        </Button>
                                    </td>
                                </tr>
                                <tr v-if="transactions.data.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center gap-3">
                                            <Receipt class="h-12 w-12 text-white/5" />
                                            <p class="text-muted-foreground text-sm font-bold uppercase tracking-widest">No transactions found</p>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- Pagination -->
                <div v-if="transactions.last_page > 1" class="flex justify-center">
                    <Pagination :links="transactions.links" />
                </div>
            </div>
        </div>

        <!-- Details Modal -->
        <Dialog :open="isDetailsModalOpen" @update:open="isDetailsModalOpen = false">
            <DialogContent class="max-w-2xl bg-neutral-950 border-white/10 backdrop-blur-2xl">
                <DialogHeader>
                    <DialogTitle class="text-2xl font-black text-white uppercase italic tracking-tighter">
                        Transaction Details
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground">
                        Detailed information for transaction #{{ selectedTransaction?.id }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedTransaction" class="space-y-6 py-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">User</p>
                            <p class="text-sm font-bold text-white">{{ selectedTransaction.user_name }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Amount</p>
                            <p class="text-sm font-bold italic" :class="selectedTransaction.amount < 0 ? 'text-red-400' : 'text-emerald-400'">
                                ${{ selectedTransaction.amount.toFixed(2) }}
                            </p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Method</p>
                            <p class="text-sm font-bold text-white uppercase">{{ selectedTransaction.payment_method }}</p>
                        </div>
                        <div class="space-y-1">
                            <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Status</p>
                            <div class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-black uppercase italic tracking-wider bg-white/5 border border-white/10"
                                :class="getStatusColorClass(selectedTransaction.status)">
                                <component :is="getStatusIcon(selectedTransaction.status)" class="h-3 w-3" />
                                {{ selectedTransaction.status }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <p class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Raw Details / Note</p>
                        <div class="p-4 rounded-xl bg-black border border-white/5 font-mono text-xs overflow-x-auto">
                            <pre class="text-white/70 whitespace-pre-wrap">{{ formatDetails(selectedTransaction.details) }}</pre>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-white/5 pt-6 mt-4">
                    <Button @click="isDetailsModalOpen = false" class="bg-white/5 border-white/10 hover:bg-white/10 text-white uppercase font-black italic tracking-tight px-8">
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
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
    border-color: var(--brand-primary) !important;
}
</style>
