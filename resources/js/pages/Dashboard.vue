<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { dashboard } from '@/routes';
import { 
    Users, 
    Download, 
    Package, 
    ShoppingCart, 
    ChevronRight, 
    BookOpen, 
    HelpCircle, 
    Headset,
    LayoutGrid,
    FileText
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';

interface Product {
    id: number;
    name: string;
}

interface Order {
    id: number;
    amount_paid: string;
    status: string;
    created_at: string;
    product: Product;
}

interface Stats {
    total_users: number;
    total_downloads: number;
    total_products: number;
    order_status: string;
}

const props = defineProps<{
    stats: Stats;
    recentOrders: Order[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard().url,
    },
];

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric' }).format(date);
};
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-8 bg-brand-bg/30">
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <!-- Total Users -->
                <div class="group relative overflow-hidden rounded-3xl bg-sidebar/40 p-7 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Total Users</p>
                            <p class="mt-3 text-4xl font-black text-foreground">{{ stats?.total_users || 0 }}</p>
                        </div>
                        <div class="h-14 w-14 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all duration-300">
                            <Users class="h-7 w-7" />
                        </div>
                    </div>
                </div>

                <!-- Total Downloads -->
                <div class="group relative overflow-hidden rounded-3xl bg-sidebar/40 p-7 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Total Downloads</p>
                            <p class="mt-3 text-4xl font-black text-foreground">{{ stats?.total_downloads || 0 }}</p>
                        </div>
                        <div class="h-14 w-14 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all duration-300">
                            <Download class="h-7 w-7" />
                        </div>
                    </div>
                </div>

                <!-- Total Products -->
                <div class="group relative overflow-hidden rounded-3xl bg-sidebar/40 p-7 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Total Products</p>
                            <p class="mt-3 text-4xl font-black text-foreground">{{ stats?.total_products || 0 }}</p>
                        </div>
                        <div class="h-14 w-14 rounded-full bg-yellow-500/10 flex items-center justify-center text-yellow-500 border border-yellow-500/20 group-hover:bg-yellow-500 group-hover:text-white transition-all duration-300">
                            <Package class="h-7 w-7" />
                        </div>
                    </div>
                </div>

                <!-- Order Status -->
                <div class="group relative overflow-hidden rounded-3xl bg-sidebar/40 p-7 border border-white/5 backdrop-blur-xl transition-all hover:border-brand-primary/20">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Order Status</p>
                            <p class="mt-3 text-2xl font-black text-foreground break-all" :class="stats?.order_status === 'None' ? 'text-muted-foreground' : 'text-foreground'">
                                {{ stats?.order_status || 'None' }}
                            </p>
                        </div>
                        <div class="h-14 w-14 rounded-full bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all duration-300">
                            <ShoppingCart class="h-7 w-7" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
                <!-- Main Content Left -->
                <div class="col-span-1 lg:col-span-2 space-y-8">
                    <!-- Welcome Card -->
                    <div class="rounded-[32px] bg-sidebar/40 border border-white/5 backdrop-blur-xl p-10 flex flex-col gap-8">
                        <div>
                            <h2 class="text-3xl font-black text-foreground">Welcome to the Dashboard</h2>
                            <p class="mt-3 text-lg text-muted-foreground">Easily manage your account and purchases right your dashboard.</p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Browse Products -->
                            <Link href="/downloads" class="group flex flex-col items-center justify-center gap-4 bg-white/5 border border-white/5 rounded-3xl p-8 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-14 w-14 rounded-2xl bg-yellow-500/10 flex items-center justify-center text-yellow-500 border border-yellow-500/20 group-hover:bg-yellow-500 group-hover:text-white transition-all">
                                    <Package class="h-7 w-7" />
                                </div>
                                <span class="text-sm font-bold text-foreground">Browse Products</span>
                            </Link>

                            <!-- View Invoices -->
                            <Link href="/purchases" class="group flex flex-col items-center justify-center gap-4 bg-white/5 border border-white/5 rounded-3xl p-8 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-14 w-14 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all">
                                    <FileText class="h-7 w-7" />
                                </div>
                                <span class="text-sm font-bold text-foreground">View Invoices</span>
                            </Link>

                            <!-- Go to Downloads -->
                            <Link href="/downloads" class="group flex flex-col items-center justify-center gap-4 bg-white/5 border border-white/5 rounded-3xl p-8 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-14 w-14 rounded-2xl bg-red-500/10 flex items-center justify-center text-red-500 border border-red-500/20 group-hover:bg-red-500 group-hover:text-white transition-all">
                                    <Download class="h-7 w-7" />
                                </div>
                                <span class="text-sm font-bold text-foreground">Go to Downloads</span>
                            </Link>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Right -->
                <div class="space-y-8">
                    <!-- Recent Orders -->
                    <div class="rounded-[32px] bg-sidebar/40 border border-white/5 backdrop-blur-xl overflow-hidden">
                        <div class="p-8 pb-4">
                            <h3 class="text-xl font-black text-foreground">Recent Orders</h3>
                        </div>
                        <div class="p-8 pt-0 min-h-[150px] flex flex-col">
                            <div v-if="recentOrders.length > 0" class="space-y-4">
                                <div v-for="order in recentOrders" :key="order.id" class="flex items-center justify-between group">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 rounded-xl bg-white/5 flex items-center justify-center border border-white/10 group-hover:border-brand-primary/30 group-hover:text-brand-primary transition-all">
                                            <ShoppingCart class="h-5 w-5" />
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-bold text-foreground truncate">{{ order.product?.name || 'Unknown Product' }}</p>
                                            <p class="text-[10px] text-muted-foreground uppercase tracking-widest font-bold">{{ formatDate(order.created_at) }}</p>
                                        </div>
                                    </div>
                                    <span class="text-sm font-bold text-foreground">${{ order.amount_paid }}</span>
                                </div>
                            </div>
                            <div v-else class="flex-1 flex flex-col items-center justify-center text-center py-8">
                                <p class="text-sm font-bold text-muted-foreground">No recent orders found.</p>
                            </div>
                        </div>
                    </div>

                    <!-- Resources -->
                    <div v-if="$page.props.auth.user.role === 'admin' || ($page.props.auth.user.role === 'reseller' && $page.props.auth.workspace_permissions?.access_api)" class="rounded-[32px] bg-sidebar/40 border border-white/5 backdrop-blur-xl p-8">
                        <h3 class="text-xl font-black text-foreground mb-6">Resources</h3>
                        <div class="space-y-5">
                            <Link href="#" class="group flex items-center justify-between py-1">
                                <div class="flex items-center gap-3">
                                    <ChevronRight class="h-4 w-4 text-brand-primary group-hover:translate-x-1 transition-transform" />
                                    <span class="text-sm font-bold text-muted-foreground group-hover:text-foreground transition-colors">Getting Started Guide</span>
                                </div>
                                <ChevronRight class="h-4 w-4 text-muted-foreground/30 group-hover:text-brand-primary" />
                            </Link>

                            <Link href="#" class="group flex items-center justify-between py-1">
                                <div class="flex items-center gap-3">
                                    <ChevronRight class="h-4 w-4 text-brand-primary group-hover:translate-x-1 transition-transform" />
                                    <span class="text-sm font-bold text-muted-foreground group-hover:text-foreground transition-colors">FAQs</span>
                                </div>
                                <ChevronRight class="h-4 w-4 text-muted-foreground/30 group-hover:text-brand-primary" />
                            </Link>

                            <Link href="#" class="group flex items-center justify-between py-1">
                                <div class="flex items-center gap-3">
                                    <ChevronRight class="h-4 w-4 text-brand-primary group-hover:translate-x-1 transition-transform" />
                                    <span class="text-sm font-bold text-muted-foreground group-hover:text-foreground transition-colors">Support</span>
                                </div>
                                <ChevronRight class="h-4 w-4 text-muted-foreground/30 group-hover:text-brand-primary" />
                            </Link>
                        </div>
                    </div>

                    <!-- Quick Links -->
                    <div class="rounded-[32px] bg-sidebar/40 border border-white/5 backdrop-blur-xl p-8">
                        <h3 class="text-xl font-black text-foreground mb-6">Quick Links</h3>
                        <div class="grid grid-cols-2 gap-4">
                            <Link href="#" class="group flex items-center justify-center gap-2 bg-white/5 border border-white/5 rounded-2xl py-3.5 px-4 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <FileText class="h-4 w-4 text-red-500" />
                                <span class="text-xs font-bold text-foreground">Our Blog</span>
                            </Link>

                            <Link href="#" class="group flex items-center justify-center gap-2 bg-white/5 border border-white/5 rounded-2xl py-3.5 px-4 transition-all hover:bg-brand-primary/10 hover:border-brand-primary/30">
                                <div class="h-4 w-4 flex items-center justify-center rounded-full bg-white text-black p-0.5">
                                    <Users class="h-3 w-3" />
                                </div>
                                <span class="text-xs font-bold text-foreground">Join Discord</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* No extra styles needed, using Tailwind */
</style>
