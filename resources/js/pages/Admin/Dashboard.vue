<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref, computed } from 'vue';
import {
    DollarSign,
    Users,
    ShoppingCart,
    TrendingUp,
    UserPlus,
    Package,
    CreditCard,
    ArrowUpRight,
} from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card/index';

interface Props {
    stats: {
        totalRevenue: number;
        revenueLast30Days: number;
        revenueLast7Days: number;
        totalUsers: number;
        newUsersLast30Days: number;
        newUsersLast7Days: number;
        totalPurchases: number;
        purchasesLast30Days: number;
    };
    revenueChart: {
        labels: string[];
        data: number[];
    };
    latestUsers: Array<{
        id: number;
        name: string;
        email: string;
        role: string;
        balance: number;
        created_at: string;
        avatar?: string;
    }>;
    revenueByMethod: Array<{
        method: string;
        total: number;
    }>;
    topProducts: Array<{
        product_id: number;
        product_name: string;
        revenue: number;
        sales: number;
    }>;
}

const props = defineProps<Props>();

const chartCanvas = ref<HTMLCanvasElement | null>(null);
let chartInstance: any = null;

onMounted(() => {
    if (chartCanvas.value && typeof window !== 'undefined') {
        import('chart.js/auto').then(({ Chart, registerables }) => {
            Chart.register(...registerables);

            const ctx = chartCanvas.value!.getContext('2d');
            if (!ctx) return;

            chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: props.revenueChart.labels,
                    datasets: [
                        {
                            label: 'Revenue ($)',
                            data: props.revenueChart.data,
                            borderColor: '#b20003',
                            backgroundColor: 'rgba(178, 0, 3, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 3,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#b20003',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false,
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: {
                                size: 14,
                                weight: 'bold',
                            },
                            bodyFont: {
                                size: 13,
                            },
                            callbacks: {
                                label: function (context: any) {
                                    return `$${context.parsed.y.toFixed(2)}`;
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.05)',
                            },
                            ticks: {
                                color: '#9ca3af',
                                callback: function (value: any) {
                                    return `$${value}`;
                                },
                            },
                        },
                        x: {
                            grid: {
                                display: false,
                            },
                            ticks: {
                                color: '#9ca3af',
                                maxRotation: 45,
                                minRotation: 45,
                            },
                        },
                    },
                },
            });
        });
    }
});

const revenueGrowth = computed(() => {
    if (props.stats.revenueLast7Days === 0) return '0';
    const previousWeek = props.stats.revenueLast30Days - props.stats.revenueLast7Days;
    if (previousWeek === 0) return '0';
    return ((props.stats.revenueLast7Days / previousWeek) * 100 - 100).toFixed(1);
});

const userGrowth = computed(() => {
    if (props.stats.newUsersLast7Days === 0) return '0';
    const previousWeek = props.stats.newUsersLast30Days - props.stats.newUsersLast7Days;
    if (previousWeek === 0) return '0';
    return ((props.stats.newUsersLast7Days / previousWeek) * 100 - 100).toFixed(1);
});
</script>

<template>
    <Head title="Admin Dashboard" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div>
                    <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2">
                        Admin Dashboard
                    </h1>
                    <p class="text-muted-foreground">Overview of your platform's performance and activity</p>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- Total Revenue -->
                    <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                                Total Revenue
                            </CardTitle>
                            <DollarSign class="h-5 w-5 text-brand-primary" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-black text-white italic">${{ stats.totalRevenue.toFixed(2) }}</div>
                            <p class="text-xs text-muted-foreground mt-2">
                                Last 30 days: ${{ stats.revenueLast30Days.toFixed(2) }}
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Total Users -->
                    <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                                Total Users
                            </CardTitle>
                            <Users class="h-5 w-5 text-emerald-400" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-black text-white italic">{{ stats.totalUsers.toLocaleString() }}</div>
                            <p class="text-xs text-muted-foreground mt-2 flex items-center gap-1">
                                <UserPlus class="h-3 w-3" />
                                <span>{{ stats.newUsersLast7Days }} new this week</span>
                                <span v-if="parseFloat(userGrowth) > 0" class="text-emerald-400 flex items-center gap-1">
                                    <ArrowUpRight class="h-3 w-3" />
                                    {{ userGrowth }}%
                                </span>
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Total Purchases -->
                    <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                                Total Purchases
                            </CardTitle>
                            <ShoppingCart class="h-5 w-5 text-blue-400" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-black text-white italic">{{ stats.totalPurchases.toLocaleString() }}</div>
                            <p class="text-xs text-muted-foreground mt-2">
                                {{ stats.purchasesLast30Days }} in last 30 days
                            </p>
                        </CardContent>
                    </Card>

                    <!-- Revenue Growth -->
                    <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-bold uppercase tracking-widest text-muted-foreground">
                                Weekly Revenue
                            </CardTitle>
                            <TrendingUp class="h-5 w-5 text-yellow-400" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-3xl font-black text-white italic">${{ stats.revenueLast7Days.toFixed(2) }}</div>
                            <p class="text-xs text-muted-foreground mt-2 flex items-center gap-1">
                                <span v-if="parseFloat(revenueGrowth) > 0" class="text-emerald-400 flex items-center gap-1">
                                    <ArrowUpRight class="h-3 w-3" />
                                    {{ revenueGrowth }}% growth
                                </span>
                                <span v-else-if="parseFloat(revenueGrowth) < 0" class="text-red-400">
                                    {{ revenueGrowth }}% decline
                                </span>
                                <span v-else class="text-muted-foreground">No change</span>
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Revenue Chart -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardHeader>
                        <CardTitle class="text-xl font-black text-white uppercase tracking-tight">
                            Revenue Trend (Last 30 Days)
                        </CardTitle>
                        <CardDescription>Daily revenue breakdown</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="h-[400px]">
                            <canvas ref="chartCanvas"></canvas>
                        </div>
                    </CardContent>
                </Card>

                <!-- Bottom Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Latest Users -->
                    <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                        <CardHeader>
                            <CardTitle class="text-xl font-black text-white uppercase tracking-tight">
                                Latest Users
                            </CardTitle>
                            <CardDescription>Recently registered users</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="space-y-4">
                                <div
                                    v-for="user in latestUsers"
                                    :key="user.id"
                                    class="flex items-center justify-between p-4 rounded-xl bg-white/2 border border-white/5 hover:bg-white/5 transition-colors"
                                >
                                    <div class="flex items-center gap-4">
                                        <div
                                            class="size-10 rounded-full bg-brand-primary/20 border border-brand-primary/40 flex items-center justify-center text-brand-primary font-black text-xs uppercase"
                                        >
                                            {{ user.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <p class="font-bold text-white">{{ user.name }}</p>
                                            <p class="text-xs text-muted-foreground">{{ user.email }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xs font-bold uppercase tracking-widest text-muted-foreground">
                                            {{ user.created_at }}
                                        </p>
                                        <p class="text-xs text-muted-foreground mt-1">
                                            {{ user.role }} • ${{ user.balance.toFixed(2) }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Revenue by Payment Method & Top Products -->
                    <div class="space-y-6">
                        <!-- Revenue by Payment Method -->
                        <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                            <CardHeader>
                                <CardTitle class="text-xl font-black text-white uppercase tracking-tight">
                                    Revenue by Payment Method
                                </CardTitle>
                                <CardDescription>Breakdown by payment type</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        v-for="method in revenueByMethod"
                                        :key="method.method"
                                        class="flex items-center justify-between p-3 rounded-xl bg-white/2 border border-white/5"
                                    >
                                        <div class="flex items-center gap-3">
                                            <CreditCard class="h-4 w-4 text-muted-foreground" />
                                            <span class="font-medium text-white">{{ method.method }}</span>
                                        </div>
                                        <span class="font-black text-brand-primary">${{ method.total.toFixed(2) }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <!-- Top Products -->
                        <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                            <CardHeader>
                                <CardTitle class="text-xl font-black text-white uppercase tracking-tight">
                                    Top Products
                                </CardTitle>
                                <CardDescription>Best performing products by revenue</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div class="space-y-3">
                                    <div
                                        v-for="(product, index) in topProducts"
                                        :key="product.product_id"
                                        class="flex items-center justify-between p-3 rounded-xl bg-white/2 border border-white/5"
                                    >
                                        <div class="flex items-center gap-3">
                                            <div
                                                class="size-8 rounded-lg bg-brand-primary/20 border border-brand-primary/40 flex items-center justify-center text-brand-primary font-black text-xs"
                                            >
                                                {{ index + 1 }}
                                            </div>
                                            <div>
                                                <p class="font-medium text-white">{{ product.product_name }}</p>
                                                <p class="text-xs text-muted-foreground">{{ product.sales }} sales</p>
                                            </div>
                                        </div>
                                        <span class="font-black text-brand-primary">${{ product.revenue.toFixed(2) }}</span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
