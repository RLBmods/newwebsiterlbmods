<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import ticketsRoutes from '@/routes/admin/tickets';
import { 
    MessageSquare, 
    Send, 
    Paperclip, 
    ArrowLeft,
    User,
    Shield,
    Clock,
    CheckCircle2,
    XCircle,
    AlertCircle
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { 
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';

interface Message {
    id: number;
    message: string;
    is_support: boolean;
    attachment: string | null;
    created_at: string;
    user: {
        id: number;
        name: string;
        email: string;
    };
}

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
    ticket: Ticket;
    messages: Message[];
}

const props = defineProps<Props>();

const form = useForm({
    message: '',
    attachment: null as File | null,
});

const statusForm = useForm({
    status: props.ticket.status,
});

const submitReply = () => {
    form.post(ticketsRoutes.reply.url(props.ticket.id), {
        onSuccess: () => {
            form.reset('message', 'attachment');
        },
        preserveScroll: true
    });
};

const updateStatus = (newStatus: string) => {
    statusForm.status = newStatus;
    statusForm.patch(ticketsRoutes.status.url(props.ticket.id), {
        preserveScroll: true
    });
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
        month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
    });
};

const scrollContainer = ref<HTMLElement | null>(null);

onMounted(() => {
    if (scrollContainer.value) {
        scrollContainer.value.scrollTop = scrollContainer.value.scrollHeight;
    }
});
</script>

<template>
    <Head :title="`Ticket #${ticket.id} - ${ticket.subject}`" />

    <AdminLayout>
        <div class="py-12 px-6">
            <div class="max-w-5xl mx-auto space-y-6">
                <!-- Back & Actions -->
                <div class="flex items-center justify-between">
                    <Link 
                        :href="ticketsRoutes.index.url()"
                        class="inline-flex items-center gap-2 text-muted-foreground hover:text-white transition-colors group"
                    >
                        <ArrowLeft class="size-4 group-hover:-translate-x-1 transition-transform" />
                        <span class="text-sm font-black uppercase tracking-widest">Back to Tickets</span>
                    </Link>

                    <div class="flex items-center gap-3">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" class="h-9 bg-white/5 border-white/10 uppercase tracking-widest font-black text-xs italic">
                                    Status: {{ ticket.status }}
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" class="bg-neutral-900 border-white/10">
                                <DropdownMenuItem @click="updateStatus('Open')" class="text-amber-500">
                                    <AlertCircle class="mr-2 h-4 w-4" /> Open
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="updateStatus('Answered')" class="text-emerald-500">
                                    <CheckCircle2 class="mr-2 h-4 w-4" /> Answered
                                </DropdownMenuItem>
                                <DropdownMenuItem @click="updateStatus('Closed')" class="text-muted-foreground">
                                    <XCircle class="mr-2 h-4 w-4" /> Closed
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <!-- Ticket Info Card -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl mb-6">
                    <CardContent class="p-6">
                        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 pb-6 border-b border-white/5">
                            <div>
                                <div class="flex items-center gap-3 mb-2">
                                    <Badge variant="outline" :class="getStatusColorClass(ticket.status)">
                                        {{ ticket.status }}
                                    </Badge>
                                    <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                        {{ ticket.type }} Support
                                    </span>
                                </div>
                                <h1 class="text-3xl font-black text-white tracking-tighter uppercase italic">
                                    {{ ticket.subject }}
                                </h1>
                            </div>
                            <div class="text-left md:text-right">
                                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground mb-1">Created At</p>
                                <p class="text-sm font-bold text-white italic">{{ formatDate(ticket.created_at) }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 pt-6">
                            <div class="flex items-center gap-4">
                                <div class="size-12 rounded-2xl bg-brand-primary/10 border border-brand-primary/20 flex items-center justify-center">
                                    <User class="size-6 text-brand-primary" />
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Requester</p>
                                    <p class="text-base font-black text-white italic">{{ ticket.user.name }}</p>
                                    <p class="text-xs text-muted-foreground">{{ ticket.user.email }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 md:justify-end">
                                <div class="size-12 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center">
                                    <Shield class="size-6 text-white/40" />
                                </div>
                                <div class="md:text-right">
                                    <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Ticket ID</p>
                                    <p class="text-base font-black text-white italic">#{{ ticket.id }}</p>
                                    <p class="text-xs text-muted-foreground">Priority: {{ ticket.priority }}</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Messages area -->
                <div class="space-y-6 mb-8">
                    <div 
                        v-for="message in messages" 
                        :key="message.id"
                        class="flex flex-col"
                        :class="message.is_support ? 'items-end' : 'items-start'"
                    >
                        <!-- Message Header -->
                        <div class="flex items-center gap-2 mb-2 px-2" :class="message.is_support ? 'flex-row-reverse' : ''">
                            <div class="size-8 rounded-full flex items-center justify-center" 
                                 :class="message.is_support ? 'bg-brand-primary text-white' : 'bg-white/10 text-muted-foreground'">
                                <Shield v-if="message.is_support" class="size-4" />
                                <User v-else class="size-4" />
                            </div>
                            <span class="text-xs font-black uppercase tracking-widest" :class="message.is_support ? 'text-brand-primary' : 'text-white'">
                                {{ message.is_support ? 'Admin Response' : message.user.name }}
                            </span>
                            <span class="text-[10px] font-medium text-muted-foreground">
                                {{ formatDate(message.created_at) }}
                            </span>
                        </div>

                        <!-- Message Bubble -->
                        <div 
                            class="max-w-[80%] p-4 rounded-2xl text-sm leading-relaxed"
                            :class="message.is_support 
                                ? 'bg-brand-primary text-white shadow-[0_0_20px_rgba(178,0,3,0.1)] rounded-tr-none' 
                                : 'bg-white/5 border border-white/10 text-white rounded-tl-none'"
                        >
                            {{ message.message }}
                            
                            <div v-if="message.attachment" class="mt-4 pt-4 border-t border-white/10">
                                <a 
                                    :href="`/storage/${message.attachment}`" 
                                    target="_blank"
                                    class="inline-flex items-center gap-2 text-xs hover:underline decoration-brand-primary underline-offset-4"
                                    :class="message.is_support ? 'text-white/80' : 'text-brand-primary font-bold'"
                                >
                                    <Paperclip class="size-3" />
                                    View Attachment
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reply Form -->
                <Card v-if="ticket.status !== 'Closed'" class="bg-white/10 border-brand-primary/30 backdrop-blur-2xl sticky bottom-6 shadow-2xl">
                    <CardContent class="p-4">
                        <form @submit.prevent="submitReply" class="space-y-4">
                            <div class="relative">
                                <Textarea 
                                    v-model="form.message"
                                    placeholder="Type your response to the user..."
                                    class="bg-black/20 border-white/10 min-h-[120px] focus:border-brand-primary transition-all resize-none text-white p-4"
                                />
                                
                                <div class="absolute right-3 bottom-3 flex items-center gap-2">
                                    <input 
                                        type="file" 
                                        class="hidden" 
                                        id="attachment"
                                        @input="form.attachment = ($event.target as HTMLInputElement).files?.[0] || null"
                                    >
                                    <label 
                                        for="attachment"
                                        class="cursor-pointer size-10 rounded-xl bg-white/5 hover:bg-white/10 flex items-center justify-center transition-colors text-muted-foreground border border-white/5"
                                        :class="{ 'text-brand-primary border-brand-primary bg-brand-primary/10': form.attachment }"
                                    >
                                        <Paperclip class="size-5" />
                                    </label>
                                    <Button 
                                        type="submit" 
                                        class="h-10 bg-brand-primary hover:bg-brand-primary/90 text-white font-black uppercase italic tracking-tighter px-6 rounded-xl"
                                        :disabled="form.processing || !form.message"
                                    >
                                        <span v-if="!form.processing">Send Reply</span>
                                        <span v-else>Sending...</span>
                                        <Send class="ml-2 size-4" />
                                    </Button>
                                </div>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div v-else class="p-8 text-center bg-white/5 rounded-3xl border border-dashed border-white/10">
                    <XCircle class="size-12 text-muted-foreground mx-auto mb-4" />
                    <h3 class="text-xl font-bold text-white mb-1">Ticket Closed</h3>
                    <p class="text-muted-foreground mb-4">You cannot post replies to closed tickets.</p>
                    <Button @click="updateStatus('Open')" variant="outline" class="border-brand-primary text-brand-primary hover:bg-brand-primary hover:text-white uppercase font-black italic">
                        Reopen Ticket
                    </Button>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>

<style scoped>
/* Glassmorphism focus effect */
textarea:focus {
    box-shadow: 0 0 20px rgba(178, 0, 3, 0.1);
}
</style>
