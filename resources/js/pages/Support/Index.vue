<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { 
    Plus, Search, Calendar, Tag, MessageSquare, 
    Headset, Lightbulb, HelpCircle, Book, Info,
    X, Send, Paperclip, ChevronRight, Loader2,
    Shield, User
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from "@/components/ui/sheet";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import axios from 'axios';
import { index as supportIndex, store as supportStore, show as supportShow, reply as supportReply } from '@/routes/support';

interface Message {
    id: number;
    message: string;
    is_support: boolean;
    created_at: string;
    user: {
        name: string;
    };
}

interface Ticket {
    id: number;
    subject: string;
    type: string;
    priority: string;
    message: string;
    order_id: string | null;
    status: 'Open' | 'Answered' | 'Closed';
    created_at: string;
    updated_at: string;
}

interface Purchase {
    order_id: string;
    product: {
        name: string;
    };
}

const props = defineProps<{
    tickets: Ticket[];
    statusCounts: {
        All: number;
        Open: number;
        Answered: number;
        Closed: number;
    };
    purchases: Purchase[];
}>();

const activeFilter = ref('All');
const searchQuery = ref('');
const selectedTicket = ref<Ticket | null>(null);
const ticketMessages = ref<Message[]>([]);
const isViewingTicket = ref(false);
const isLoadingMessages = ref(false);

const filteredTickets = computed(() => {
    return props.tickets.filter(ticket => {
        const matchesFilter = activeFilter.value === 'All' || ticket.status === activeFilter.value;
        const matchesSearch = ticket.subject.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                             ticket.id.toString().includes(searchQuery.value);
        return matchesFilter && matchesSearch;
    });
});

const createForm = useForm({
    subject: '',
    category: '',
    order_id: '',
    message: '',
    attachment: null as File | null,
});

const replyForm = useForm({
    message: '',
    attachment: null as File | null,
});

const submitTicket = () => {
    createForm.post(supportStore().url, {
        onSuccess: () => {
            createForm.reset();
        },
    });
};

const viewTicket = async (ticket: Ticket) => {
    selectedTicket.value = ticket;
    isViewingTicket.value = true;
    isLoadingMessages.value = true;
    
    try {
        const response = await axios.get(supportShow({ ticket: ticket.id }).url);
        ticketMessages.value = response.data.messages;
    } catch (error) {
        console.error('Failed to load messages', error);
    } finally {
        isLoadingMessages.value = false;
    }
};

const submitReply = () => {
    if (!selectedTicket.value) return;
    
    replyForm.post(supportReply({ ticket: selectedTicket.value.id }).url, {
        onSuccess: () => {
            replyForm.reset();
            viewTicket(selectedTicket.value!);
        },
    });
};

const getStatusColor = (status: string) => {
    switch (status) {
        case 'Open': return 'bg-red-500';
        case 'Answered': return 'bg-green-500';
        case 'Closed': return 'bg-zinc-500';
        default: return 'bg-zinc-500';
    }
};

const timeSince = (date: string) => {
    return new Date(date).toLocaleDateString();
};
</script>

<template>
    <Head title="Support Center" />

    <AppLayout>
        <div class="min-h-screen bg-[#0a0a0a] text-zinc-200">
            <!-- Hero Section -->
            <div class="relative overflow-hidden bg-gradient-to-b from-brand-primary/20 to-transparent py-16 px-4 sm:px-6 lg:px-8 border-b border-white/5">
                <div class="max-w-4xl mx-auto text-center relative z-10">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-brand-primary/10 border border-brand-primary/20 mb-6 shadow-[0_0_20px_rgba(178,0,3,0.2)]">
                        <Headset class="w-8 h-8 text-brand-primary" />
                    </div>
                    <h1 class="text-4xl font-extrabold text-white tracking-tight sm:text-5xl mb-4">
                        How can we <span class="text-brand-primary">help you?</span>
                    </h1>
                    <p class="text-lg text-zinc-400 mb-8 max-w-2xl mx-auto">
                        Our premium support team is here to assist you with any inquiries. Average response time: 12-24 hours.
                    </p>
                    
                    <div class="relative max-w-xl mx-auto">
                        <div class="absolute inset-0 bg-brand-primary/20 blur-2xl rounded-full opacity-30"></div>
                        <div class="relative flex items-center bg-zinc-900/50 backdrop-blur-xl border border-white/10 rounded-2xl p-2 shadow-2xl focus-within:border-brand-primary/50 transition-all">
                            <Search class="w-5 h-5 text-zinc-500 ml-3" />
                            <input 
                                v-model="searchQuery"
                                type="text" 
                                placeholder="Search your tickets..." 
                                class="w-full bg-transparent border-none focus:ring-0 text-white placeholder:text-zinc-600 px-4 py-2"
                            />
                        </div>
                    </div>
                </div>
                <!-- Decorative elements -->
                <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full h-full pointer-events-none opacity-20">
                    <div class="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-brand-primary/30 blur-[120px] rounded-full"></div>
                    <div class="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-brand-primary/20 blur-[100px] rounded-full"></div>
                </div>
            </div>

            <div class="max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
                    
                    <!-- Main Tickets Area -->
                    <div class="lg:col-span-8 order-1 lg:order-1">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-8 gap-4">
                            <div class="flex flex-wrap gap-2">
                                <button 
                                    v-for="filter in ['All', 'Open', 'Answered', 'Closed']"
                                    :key="filter"
                                    @click="activeFilter = filter"
                                    class="px-4 py-2 rounded-xl text-sm font-medium transition-all"
                                    :class="activeFilter === filter 
                                        ? 'bg-brand-primary text-white shadow-[0_0_15px_rgba(178,0,3,0.3)]' 
                                        : 'bg-white/5 text-zinc-500 hover:bg-white/10 hover:text-zinc-300'"
                                >
                                    {{ filter }}
                                    <span class="ml-1 opacity-60">({{ statusCounts[filter as keyof typeof statusCounts] }})</span>
                                </button>
                            </div>
                            
                            <Dialog>
                                <DialogTrigger asChild>
                                    <Button class="bg-brand-primary hover:bg-brand-primary/90 text-white rounded-xl h-11 px-6 shadow-[0_0_20px_rgba(178,0,3,0.2)]">
                                        <Plus class="w-5 h-5 mr-2" />
                                        Create New Ticket
                                    </Button>
                                </DialogTrigger>
                                <DialogContent class="bg-[#0f0f0f] border-zinc-800 text-zinc-200 w-full sm:max-w-md rounded-3xl">
                                    <DialogHeader class="mb-4">
                                        <DialogTitle class="text-2xl font-bold text-white">Open a Ticket</DialogTitle>
                                        <DialogDescription class="text-zinc-500">
                                            Our agents will get back to you as soon as possible.
                                        </DialogDescription>
                                    </DialogHeader>
                                    
                                    <form @submit.prevent="submitTicket" class="space-y-6">
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-400">Subject</label>
                                            <Input 
                                                v-model="createForm.subject"
                                                placeholder="Briefly describe the issue"
                                                class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl"
                                                required
                                            />
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-400">Category</label>
                                            <select 
                                                v-model="createForm.category"
                                                class="w-full bg-zinc-900/50 border border-white/5 focus:border-brand-primary/50 text-white rounded-xl px-4 py-2 text-sm outline-none"
                                                required
                                            >
                                                <option value="" disabled>Select a category</option>
                                                <option value="billing">Billing/Payment</option>
                                                <option v-if="purchases.length > 0" value="hwid_reset">Reset HWID</option>
                                                <option value="technical">Technical Support</option>
                                                <option value="product">Product Questions</option>
                                                <option value="other">Other</option>
                                            </select>
                                        </div>

                                        <div v-if="createForm.category === 'hwid_reset'" class="space-y-2 animate-in fade-in slide-in-from-top-2 duration-300">
                                            <label class="text-sm font-medium text-zinc-400">Select Order ID</label>
                                            <select 
                                                v-model="createForm.order_id"
                                                class="w-full bg-zinc-900/50 border border-white/5 focus:border-brand-primary/50 text-white rounded-xl px-4 py-2 text-sm outline-none"
                                                required
                                            >
                                                <option value="" disabled>Select a purchase</option>
                                                <option v-for="purchase in purchases" :key="purchase.order_id" :value="purchase.order_id">
                                                    {{ purchase.product.name }} ({{ purchase.order_id }})
                                                </option>
                                            </select>
                                        </div>
                                        
                                        <div class="space-y-2">
                                            <label class="text-sm font-medium text-zinc-400">Message</label>
                                            <Textarea 
                                                v-model="createForm.message"
                                                placeholder="Describe your issue in detail..."
                                                class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl min-h-[150px]"
                                                required
                                            />
                                        </div>
                                        
                                        <div class="p-4 bg-zinc-900/30 rounded-2xl border border-white/5">
                                            <label class="flex flex-col items-center justify-center cursor-pointer group">
                                                <Paperclip class="w-6 h-6 text-zinc-600 group-hover:text-brand-primary transition-colors mb-2" />
                                                <span class="text-sm text-zinc-500">Attach file (Optional, Max 5MB)</span>
                                                <input type="file" class="hidden" @change="e => createForm.attachment = (e.target as HTMLInputElement).files?.[0] || null" />
                                                <span v-if="createForm.attachment" class="mt-2 text-xs text-brand-primary truncate max-w-[200px]">
                                                    {{ createForm.attachment.name }}
                                                </span>
                                            </label>
                                        </div>
                                        
                                        <Button 
                                            type="submit" 
                                            class="w-full bg-brand-primary hover:bg-brand-primary/90 text-white rounded-xl h-12 text-lg font-semibold shadow-xl"
                                            :disabled="createForm.processing"
                                        >
                                            <span v-if="createForm.processing" class="flex items-center">
                                                <Loader2 class="w-5 h-5 mr-2 animate-spin" /> Submitting...
                                            </span>
                                            <span v-else>Submit Ticket</span>
                                        </Button>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        </div>

                        <!-- Tickets List -->
                        <div class="space-y-4">
                            <div v-if="filteredTickets.length === 0" class="text-center py-20 bg-white/5 rounded-3xl border border-dashed border-white/10">
                                <Search class="w-12 h-12 text-zinc-700 mx-auto mb-4" />
                                <h3 class="text-xl font-medium text-zinc-400">No tickets found</h3>
                                <p class="text-zinc-600">Try adjusting your filters or search query.</p>
                            </div>
                            
                            <div 
                                v-for="ticket in filteredTickets" 
                                :key="ticket.id"
                                @click="viewTicket(ticket)"
                                class="group relative bg-[#121212]/50 backdrop-blur-sm border border-white/5 p-6 rounded-3xl hover:bg-white/5 hover:border-white/10 transition-all cursor-pointer"
                            >
                                <div class="flex items-start justify-between">
                                    <div class="space-y-3">
                                        <div class="flex items-center gap-3">
                                            <span class="text-xs font-mono text-zinc-600 uppercase tracking-widest">#TKT-{{ ticket.id.toString().padStart(5, '0') }}</span>
                                            <span v-if="ticket.order_id" class="text-[10px] bg-brand-primary/10 text-brand-primary font-bold px-2 py-0.5 rounded border border-brand-primary/20">
                                                ID: {{ ticket.order_id }}
                                            </span>
                                            <div class="flex items-center gap-1.5">
                                                <div 
                                                    class="w-2 h-2 rounded-full animate-pulse"
                                                    :class="getStatusColor(ticket.status)"
                                                ></div>
                                                <span class="text-xs font-bold uppercase tracking-wide text-zinc-400">{{ ticket.status }}</span>
                                            </div>
                                        </div>
                                        <h3 class="text-xl font-bold text-white group-hover:text-brand-primary transition-colors">
                                            {{ ticket.subject }}
                                        </h3>
                                        <p class="text-zinc-500 line-clamp-1 text-sm max-w-2xl">
                                            {{ ticket.message }}
                                        </p>
                                        <div class="flex flex-wrap items-center gap-6 pt-2">
                                            <div class="flex items-center text-xs text-zinc-600">
                                                <Calendar class="w-3.5 h-3.5 mr-1.5" />
                                                {{ timeSince(ticket.created_at) }}
                                            </div>
                                            <div class="flex items-center text-xs text-zinc-600">
                                                <Tag class="w-3.5 h-3.5 mr-1.5" />
                                                {{ ticket.type }}
                                            </div>
                                            <div class="flex items-center text-xs text-zinc-600">
                                                <Shield class="w-3.5 h-3.5 mr-1.5" />
                                                {{ ticket.priority }} Priority
                                            </div>
                                        </div>
                                    </div>
                                    <div class="hidden sm:flex items-center justify-center w-10 h-10 rounded-full bg-white/5 group-hover:bg-brand-primary group-hover:text-white group-hover:translate-x-1 transition-all">
                                        <ChevronRight class="w-5 h-5 text-zinc-500 group-hover:text-white" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Area -->
                    <div class="lg:col-span-4 order-2 lg:order-2 space-y-8">
                        <!-- Quick Solutions Card -->
                        <div class="bg-gradient-to-br from-zinc-900 to-black border border-white/5 rounded-3xl overflow-hidden">
                            <div class="p-6 border-b border-white/5 flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-brand-primary/10 flex items-center justify-center text-brand-primary">
                                    <Lightbulb class="w-5 h-5" />
                                </div>
                                <h3 class="text-lg font-bold text-white">Quick Solutions</h3>
                            </div>
                            <div class="p-6 space-y-6">
                                <div class="space-y-4">
                                    <div class="group flex items-center gap-3 cursor-pointer">
                                        <div class="w-2 h-2 rounded-full bg-brand-primary group-hover:scale-125 transition-transform"></div>
                                        <span class="text-zinc-400 group-hover:text-zinc-200 text-sm">Payment not processed</span>
                                    </div>
                                    <div class="group flex items-center gap-3 cursor-pointer">
                                        <div class="w-2 h-2 rounded-full bg-brand-primary group-hover:scale-125 transition-transform"></div>
                                        <span class="text-zinc-400 group-hover:text-zinc-200 text-sm">Download problems</span>
                                    </div>
                                    <div class="group flex items-center gap-3 cursor-pointer">
                                        <div class="w-2 h-2 rounded-full bg-brand-primary group-hover:scale-125 transition-transform"></div>
                                        <span class="text-zinc-400 group-hover:text-zinc-200 text-sm">Account verification</span>
                                    </div>
                                </div>
                                
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5">
                                    <div class="flex items-center gap-2 text-brand-primary font-bold mb-2">
                                        <Info class="w-4 h-4" />
                                        <span class="text-xs uppercase tracking-wider">Note</span>
                                    </div>
                                    <p class="text-xs text-zinc-500 leading-relaxed">
                                        Average response time is 12-24 hours. For urgent matters, please mark as high priority.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Knowledge Card -->
                        <div class="bg-zinc-900/30 border border-white/5 rounded-3xl p-6">
                            <div class="flex items-center gap-3 mb-6">
                                <Book class="w-5 h-5 text-zinc-500" />
                                <h3 class="text-lg font-bold text-white">Documentation</h3>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-colors cursor-pointer text-center">
                                    <HelpCircle class="w-5 h-5 text-zinc-500 mx-auto mb-2" />
                                    <span class="text-xs font-medium text-zinc-400">FAQs</span>
                                </div>
                                <div class="p-4 bg-white/5 rounded-2xl border border-white/5 hover:bg-white/10 transition-colors cursor-pointer text-center">
                                    <Shield class="w-5 h-5 text-zinc-500 mx-auto mb-2" />
                                    <span class="text-xs font-medium text-zinc-400">Rules</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Ticket Detail Overlay (Simplified version of what was in legacy modal) -->
            <Sheet :open="isViewingTicket" @update:open="isViewingTicket = $event">
                <SheetContent overlay-class="backdrop-blur-md" class="bg-[#0f0f0f] border-zinc-800 text-zinc-200 w-full sm:max-w-2xl p-0 h-full flex flex-col">
                    <div v-if="selectedTicket" class="flex flex-col h-full">
                        <!-- Header -->
                        <div class="p-6 border-b border-white/5 bg-zinc-900/50">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-xs font-mono text-zinc-600">TICKET #{{ selectedTicket.id.toString().padStart(5, '0') }}</span>
                                <Badge :class="getStatusColor(selectedTicket.status)" class="text-white border-none">{{ selectedTicket.status }}</Badge>
                            </div>
                            <h2 class="text-2xl font-bold text-white">{{ selectedTicket.subject }}</h2>
                        </div>

                        <!-- Chat Area -->
                        <div class="flex-1 overflow-y-auto p-6 space-y-6 custom-scrollbar">
                            <div v-if="isLoadingMessages" class="flex flex-col items-center justify-center py-20">
                                <Loader2 class="w-8 h-8 text-brand-primary animate-spin mb-4" />
                                <p class="text-zinc-500">Retrieving messages...</p>
                            </div>
                            
                            <template v-else>
                                <div 
                                    v-for="msg in ticketMessages" 
                                    :key="msg.id"
                                    class="flex flex-col"
                                    :class="msg.is_support ? 'items-start' : 'items-end'"
                                >
                                    <!-- Message Label -->
                                    <div class="flex items-center gap-2 mb-1.5 px-1" :class="msg.is_support ? '' : 'flex-row-reverse'">
                                        <div class="size-6 rounded-full flex items-center justify-center" 
                                             :class="msg.is_support ? 'bg-brand-primary/20 text-brand-primary' : 'bg-white/10 text-muted-foreground'">
                                            <Shield v-if="msg.is_support" class="size-3" />
                                            <User v-else class="size-3" />
                                        </div>
                                        <span class="text-[10px] font-black uppercase tracking-widest" :class="msg.is_support ? 'text-brand-primary' : 'text-zinc-400'">
                                            {{ msg.is_support ? 'Support Team' : 'You' }}
                                        </span>
                                    </div>

                                    <div 
                                        class="max-w-[85%] rounded-2xl p-4 text-sm shadow-xl"
                                        :class="msg.is_support 
                                            ? 'bg-zinc-800/80 border border-white/10 text-white rounded-tl-none backdrop-blur-md' 
                                            : 'bg-brand-primary text-white rounded-tr-none'"
                                    >
                                        <p class="whitespace-pre-wrap leading-relaxed">{{ msg.message }}</p>
                                        <div class="mt-2 text-[9px] font-medium opacity-40 text-right">
                                            {{ timeSince(msg.created_at) }}
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Footer / Reply -->
                        <div v-if="selectedTicket.status !== 'Closed'" class="p-6 bg-zinc-900/50 border-t border-white/5">
                            <form @submit.prevent="submitReply" class="space-y-4">
                                <div class="relative">
                                    <Textarea 
                                        v-model="replyForm.message"
                                        placeholder="Type your reply here..."
                                        class="bg-zinc-900 border-white/10 focus:border-brand-primary/50 text-white rounded-2xl min-h-[100px] pr-12"
                                        required
                                    />
                                    <button 
                                        type="button" 
                                        class="absolute right-4 bottom-4 text-zinc-600 hover:text-brand-primary transition-colors"
                                        title="Attach file"
                                    >
                                        <Paperclip class="w-5 h-5" />
                                    </button>
                                </div>
                                <Button 
                                    type="submit" 
                                    class="w-full bg-brand-primary hover:bg-brand-primary/90 text-white rounded-xl h-12 font-bold"
                                    :disabled="replyForm.processing"
                                >
                                    <Send v-if="!replyForm.processing" class="w-4 h-4 mr-2" />
                                    <Loader2 v-else class="w-4 h-4 mr-2 animate-spin" />
                                    {{ replyForm.processing ? 'Sending...' : 'Send Reply' }}
                                </Button>
                            </form>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>
        </div>
    </AppLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.05);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(178, 0, 3, 0.2);
}
</style>
