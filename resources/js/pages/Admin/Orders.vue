<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ShoppingCart, Search, ExternalLink, Calendar, CreditCard } from 'lucide-vue-next';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';

interface Props {
    orders: {
        data: Array<{
            id: number;
            user_name: string;
            product_name: string;
            amount: number;
            status: string;
            payment_method: string;
            created_at: string;
        }>;
        links: any[];
    };
}

defineProps<Props>();

const getStatusColor = (status: string) => {
    switch (status.toLowerCase()) {
        case 'completed': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
        case 'pending': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'failed': return 'bg-red-500/10 text-red-500 border-red-500/20';
        default: return 'bg-white/10 text-muted-foreground border-white/20';
    }
};
</script>

<template>
    <Head title="Manage Orders" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2">
                        Manage Orders
                    </h1>
                    <p class="text-muted-foreground">Track and manage platform sales</p>
                </div>

                <!-- Filters -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-4">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input 
                                    placeholder="Search by order ID, user or product..." 
                                    class="pl-10 bg-white/5 border-white/10 h-11"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Orders Table -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-white/10 bg-white/2">
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">Order Info</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">User</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">Amount</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">Status</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground">Date</th>
                                    <th class="px-6 py-4 text-xs font-bold uppercase tracking-widest text-muted-foreground text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="order in orders.data" :key="order.id" class="hover:bg-white/2 transition-colors">
                                    <td class="px-6 py-4">
                                        <div>
                                            <p class="font-bold text-white uppercase italic">ORD-{{ order.id }}</p>
                                            <p class="text-xs text-muted-foreground">{{ order.product_name }}</p>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <p class="font-medium text-white">{{ order.user_name }}</p>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="font-black text-brand-primary italic">${{ order.amount.toFixed(2) }}</span>
                                            <span class="text-[10px] text-muted-foreground uppercase tracking-widest flex items-center gap-1">
                                                <CreditCard class="h-2.5 w-2.5" />
                                                {{ order.payment_method }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-lg text-[10px] font-black uppercase tracking-widest border italic"
                                            :class="getStatusColor(order.status)">
                                            {{ order.status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2 text-xs text-muted-foreground">
                                            <Calendar class="h-3 w-3" />
                                            {{ order.created_at }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <button class="p-2 rounded-lg bg-white/5 border border-white/10 text-muted-foreground hover:text-brand-primary hover:border-brand-primary transition-all">
                                            <ExternalLink class="h-4 w-4" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>
            </div>
        </div>
    </AdminLayout>
</template>
