<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { 
    Ticket as TicketIcon, 
    Search, 
    Filter, 
    MessageSquare, 
    Clock, 
    User,
    ChevronRight,
    AlertCircle,
    CheckCircle2,
    XCircle
} from 'lucide-vue-next';
import { ref } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import ticketsRoutes from '@/routes/admin/tickets';

interface Ticket {
    id: number;
    subject: string;
    type: string;
    priority: string;
    status: string;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

interface Props {
    tickets: {
        data: Ticket[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    statusCounts: {
        All: number;
        Open: number;
        Answered: number;
        Closed: number;
    };
    filters: {
        status: string;
    };
}

const props = defineProps<Props>();

const activeStatus = ref(props.filters.status || 'All');

const setStatus = (status: string) => {
    activeStatus.value = status;
    router.visit(ticketsRoutes.index.url({ query: { status } }), { 
        preserveState: true,
        replace: true 
    });
};

const getStatusIcon = (status: string) => {
    switch (status) {
        case 'Open': return AlertCircle;
        case 'Answered': return CheckCircle2;
        case 'Closed': return XCircle;
        default: return TicketIcon;
    }
};

const getStatusColorClass = (status: string) => {
    switch (status) {
        case 'Open': return 'bg-amber-500/10 text-amber-500 border-amber-500/20';
        case 'Answered': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
        case 'Closed': return 'bg-white/5 text-muted-foreground border-white/10';
        default: return 'bg-white/10 text-white border-white/20';
    }
};

const formatDate = (dateString: string) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};
</script>

<template>
    <Head title="Ticket Management" />

    <AdminLayout>
        <div class="py-12 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2">
                            Ticket Management
                        </h1>
                        <p class="text-muted-foreground">Review and respond to user support requests</p>
                    </div>
                </div>

                <!-- Stats/Filters -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <button 
                        v-for="(count, label) in statusCounts" 
                        :key="label"
                        @click="setStatus(label)"
                        class="p-4 rounded-2xl border transition-all text-left relative overflow-hidden group"
                        :class="activeStatus === label 
                            ? 'bg-brand-primary border-brand-primary shadow-[0_0_20px_rgba(178,0,3,0.3)]' 
                            : 'bg-white/5 border-white/10 hover:border-white/20'"
                    >
                        <div class="relative z-10">
                            <p class="text-[10px] font-black uppercase tracking-widest mb-1" 
                               :class="activeStatus === label ? 'text-white/70' : 'text-muted-foreground'">
                                {{ label }} Tickets
                            </p>
                            <p class="text-2xl font-black text-white italic tracking-tighter">{{ count }}</p>
                        </div>
                        <component 
                            :is="getStatusIcon(label)" 
                            class="absolute -right-2 -bottom-2 size-16 opacity-10 group-hover:scale-110 transition-transform"
                            :class="activeStatus === label ? 'text-white' : 'text-white'"
                        />
                    </button>
                </div>

                <!-- Search & Actions -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-4">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input 
                                    placeholder="Search by subject or user..." 
                                    class="pl-10 bg-white/5 border-white/10 h-11"
                                />
                            </div>
                            <Button variant="outline" class="h-11 border-white/10 bg-white/5 uppercase tracking-widest font-bold text-xs">
                                <Filter class="mr-2 h-4 w-4" />
                                Filter
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Tickets List -->
                <div class="space-y-4">
                    <div v-if="tickets.data.length === 0" class="text-center py-20 bg-white/5 rounded-3xl border border-dashed border-white/10">
                        <div class="size-16 bg-white/5 rounded-full flex items-center justify-center mx-auto mb-4">
                            <TicketIcon class="size-8 text-white/20" />
                        </div>
                        <h3 class="text-xl font-bold text-white mb-1">No tickets found</h3>
                        <p class="text-muted-foreground">There are no tickets matching your current filter.</p>
                    </div>

                    <Link 
                        v-for="ticket in tickets.data" 
                        :key="ticket.id"
                        :href="ticketsRoutes.show.url(ticket.id)"
                        class="block group"
                    >
                        <Card class="bg-white/5 border-white/10 backdrop-blur-xl hover:border-brand-primary/50 transition-all overflow-hidden">
                            <CardContent class="p-6">
                                <div class="flex items-center justify-between gap-6">
                                    <div class="flex items-center gap-6 flex-1 min-w-0">
                                        <!-- ID & Status -->
                                        <div class="hidden md:block">
                                            <div class="size-12 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center flex-col">
                                                <span class="text-[10px] font-black text-muted-foreground uppercase">#</span>
                                                <span class="text-sm font-black text-white italic">{{ ticket.id }}</span>
                                            </div>
                                        </div>

                                        <!-- Info -->
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 mb-1">
                                                <Badge variant="outline" :class="getStatusColorClass(ticket.status)">
                                                    {{ ticket.status }}
                                                </Badge>
                                                <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                                    {{ ticket.type }} • {{ ticket.priority }} Priority
                                                </span>
                                            </div>
                                            <h3 class="text-lg font-black text-white tracking-tight uppercase italic truncate group-hover:text-brand-primary transition-colors">
                                                {{ ticket.subject }}
                                            </h3>
                                            <div class="flex items-center gap-4 mt-2">
                                                <div class="flex items-center gap-1.5">
                                                    <User class="size-3 text-muted-foreground" />
                                                    <span class="text-xs text-muted-foreground font-medium">{{ ticket.user.name }}</span>
                                                </div>
                                                <div class="flex items-center gap-1.5">
                                                    <Clock class="size-3 text-muted-foreground" />
                                                    <span class="text-xs text-muted-foreground font-medium">{{ formatDate(ticket.created_at) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-4">
                                        <Button variant="ghost" size="icon" class="rounded-full bg-white/5 border border-white/10 group-hover:bg-brand-primary group-hover:border-brand-primary group-hover:text-white transition-all">
                                            <ChevronRight class="size-5" />
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </Link>
                </div>

                <!-- Pagination -->
                <div v-if="tickets.total > tickets.data.length" class="flex justify-center mt-8">
                    <!-- Basic pagination placeholder -->
                    <div class="flex items-center gap-2">
                        <Button 
                            v-for="link in tickets.links" 
                            :key="link.label"
                            variant="outline"
                            class="bg-white/5 border-white/10 h-10 px-4"
                            :class="{ 'bg-brand-primary border-brand-primary text-white': link.active }"
                            v-html="link.label"
                            :disabled="!link.url"
                            @click="router.visit(link.url)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
.custom-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1.25em;
}
</style>
