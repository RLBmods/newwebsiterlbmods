<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { Bell, Loader2, Check, Inbox } from 'lucide-vue-next';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import axios from 'axios';
import { index as notificationsIndex, read as markAsReadRoute } from '@/routes/notifications';

const notifications = ref<any[]>([]);
const unreadCount = ref(0);
const isLoading = ref(true);
const page = usePage();
const toast = useToast();

const fetchNotifications = async () => {
    try {
        const response = await axios.get(notificationsIndex().url);
        notifications.value = response.data.notifications;
        unreadCount.value = response.data.unreadCount;
    } catch (error) {
        console.error('Failed to fetch notifications', error);
    } finally {
        isLoading.value = false;
    }
};

const markAsRead = async (id: string | number) => {
    try {
        await axios.post(markAsReadRoute({ id }).url);
        // Optimistic update - remove the notification from the list
        unreadCount.value = Math.max(0, unreadCount.value - 1);
        notifications.value = notifications.value.filter(n => n.id !== id);
    } catch (error) {
        console.error('Failed to mark notification as read', error);
    }
};

const timeAgo = (date: string) => {
    const now = new Date();
    const past = new Date(date);
    const diffInSeconds = Math.floor((now.getTime() - past.getTime()) / 1000);
    
    if (diffInSeconds < 60) return 'Just now';
    if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)}m ago`;
    if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)}h ago`;
    return past.toLocaleDateString();
};

onMounted(() => {
    fetchNotifications();

    // Listen for real-time notifications
    const userId = (page.props.auth as any).user.id;
    if (window.Echo) {
        window.Echo.private(`App.Models.User.${userId}`)
            .notification((notification: any) => {
                unreadCount.value++;
                notifications.value.unshift({
                    id: notification.id,
                    data: {
                        message: notification.message,
                        type: notification.type
                    },
                    created_at: notification.created_at || new Date().toISOString(),
                    read_at: null
                });

                // Show toast
                toast.info(notification.message || 'New notification received!', {
                    timeout: 5000,
                    closeOnClick: true,
                    pauseOnHover: true,
                });
            });
    }
});
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="icon" class="relative group h-10 w-10 rounded-xl hover:bg-white/5 transition-all outline-none!">
                <Bell class="h-5 w-5 text-zinc-400 group-hover:text-white transition-colors" />
                <span v-if="unreadCount > 0" class="absolute top-2.5 right-2.5 flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-primary opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-brand-primary shadow-[0_0_8px_#b20003]"></span>
                </span>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-80 bg-[#0f0f0f]/95 backdrop-blur-xl border-white/10 text-white p-0 rounded-2xl shadow-2xl overflow-hidden mt-2 z-50">
            <div class="px-4 py-3 border-b border-white/5 flex items-center justify-between bg-white/5">
                <h3 class="text-sm font-bold tracking-tight">Notifications</h3>
                <span v-if="unreadCount > 0" class="text-[10px] bg-brand-primary/20 text-brand-primary px-2 py-0.5 rounded-full font-bold uppercase tracking-wider">
                    {{ unreadCount }} New
                </span>
            </div>

            <div class="max-h-[350px] overflow-y-auto custom-scrollbar">
                <div v-if="isLoading" class="p-8 flex flex-col items-center justify-center space-y-3">
                    <Loader2 class="h-6 w-6 text-brand-primary animate-spin" />
                    <span class="text-xs text-zinc-500 font-medium tracking-wide">Syncing...</span>
                </div>

                <div v-else-if="notifications.length === 0" class="p-10 flex flex-col items-center justify-center space-y-3 text-center">
                    <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center">
                        <Inbox class="h-6 w-6 text-zinc-700" />
                    </div>
                    <div class="space-y-1">
                        <p class="text-sm font-bold text-zinc-400 leading-tight">All caught up!</p>
                        <p class="text-[11px] text-zinc-600">No new alerts at the moment.</p>
                    </div>
                </div>

                <div v-else class="divide-y divide-white/5">
                    <div 
                        v-for="notification in notifications" 
                        :key="notification.id"
                        class="p-4 hover:bg-white/5 transition-colors cursor-pointer group/item relative"
                        @click="markAsRead(notification.id)"
                    >
                        <div class="flex items-start gap-3">
                            <div class="mt-1 h-2 w-2 rounded-full bg-brand-primary flex-shrink-0 mt-2"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-zinc-300 leading-relaxed font-medium">
                                    {{ notification.data.message || 'New notification' }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="text-[10px] text-zinc-600 font-bold uppercase tracking-widest">
                                        {{ timeAgo(notification.created_at) }}
                                    </span>
                                </div>
                            </div>
                            <button class="opacity-0 group-hover/item:opacity-100 transition-opacity p-1 hover:text-brand-primary" title="Mark as read">
                                <Check class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div v-if="notifications.length > 0" class="p-3 bg-white/5 border-t border-white/5 text-center">
                <button class="text-[11px] font-bold text-zinc-500 hover:text-white transition-colors uppercase tracking-widest">View History</button>
            </div>
        </DropdownMenuContent>
    </DropdownMenu>
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
