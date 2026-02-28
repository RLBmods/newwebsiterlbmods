<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    Users as UsersIcon, 
    Search, 
    Mail, 
    Shield, 
    DollarSign, 
    Edit2, 
    Trash2, 
    Ban, 
    CheckCircle2,
    Percent,
    UserCircle,
    X
} from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
    DialogDescription
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import Pagination from '@/components/Pagination.vue';

interface UserData {
    id: number;
    name: string;
    email: string;
    role: string;
    balance: number;
    reseller_discount: number | null;
    banned: boolean;
    created_at: string;
}

interface Props {
    users: {
        data: UserData[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: {
        search?: string;
    };
}

const props = defineProps<Props>();

const search = ref(props.filters.search || '');
const isEditModalOpen = ref(false);
const selectedUser = ref<UserData | null>(null);

const form = useForm({
    name: '',
    email: '',
    role: 'user',
    balance: 0,
    reseller_discount: null as number | null,
    banned: false,
});

const updateFilters = () => {
    router.get('/admin/users', { search: search.value }, {
        preserveState: true,
        replace: true,
    });
};

watch(search, () => {
    updateFilters();
});

const openEditModal = (user: UserData) => {
    selectedUser.value = user;
    form.name = user.name;
    form.email = user.email;
    form.role = user.role;
    form.balance = user.balance;
    form.reseller_discount = user.reseller_discount;
    form.banned = user.banned;
    isEditModalOpen.value = true;
};

const submitUpdate = () => {
    if (!selectedUser.value) return;
    
    form.put(`/admin/users/${selectedUser.value.id}`, {
        onSuccess: () => {
            isEditModalOpen.value = false;
        },
    });
};

const deleteUser = (user: UserData) => {
    if (confirm(`Are you sure you want to delete ${user.name}? This action cannot be undone.`)) {
        router.delete(`/admin/users/${user.id}`);
    }
};

const getRoleBadgeClass = (role: string) => {
    switch (role) {
        case 'admin': return 'bg-brand-primary/20 text-brand-primary border-brand-primary/30';
        case 'reseller': return 'bg-blue-500/20 text-blue-400 border-blue-500/30';
        default: return 'bg-white/10 text-muted-foreground border-white/20';
    }
};
</script>

<template>
    <Head title="Manage Users" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2 flex items-center gap-3">
                            <UsersIcon class="h-10 w-10 text-brand-primary" />
                            Manage Users
                        </h1>
                        <p class="text-muted-foreground">View and manage platform users, roles, and balances</p>
                    </div>
                </div>

                <!-- Filters & Search -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-4">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input 
                                    v-model="search"
                                    placeholder="Search users by name or email..." 
                                    class="pl-10 bg-white/5 border-white/10 h-11"
                                />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Users Table -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-white/5 bg-white/[0.02]">
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">User</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Role</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Balance</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Discount</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Status</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground">Joined</th>
                                    <th class="px-6 py-4 text-[10px] font-black uppercase tracking-widest text-muted-foreground text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <tr v-for="user in users.data" :key="user.id" class="hover:bg-white/[0.02] transition-colors group">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-3">
                                            <div class="size-10 rounded-full bg-white/5 flex items-center justify-center border border-white/10 group-hover:border-brand-primary/50 transition-colors">
                                                <UserCircle class="h-5 w-5 text-white/40" />
                                            </div>
                                            <div>
                                                <p class="text-sm font-black text-white italic leading-tight">{{ user.name }}</p>
                                                <p class="text-[10px] text-muted-foreground">{{ user.email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <Badge variant="outline" class="text-[10px] font-black uppercase italic tracking-widest" :class="getRoleBadgeClass(user.role)">
                                            {{ user.role }}
                                        </Badge>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-white italic font-black">
                                        ${{ user.balance.toFixed(2) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span v-if="user.reseller_discount !== null" class="text-[10px] font-black text-emerald-400 italic">
                                            {{ user.reseller_discount }}% OFF
                                        </span>
                                        <span v-else class="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter opacity-30">
                                            Default
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5 px-2 py-0.5 rounded-lg border text-[9px] font-black uppercase italic tracking-widest"
                                            :class="user.banned ? 'bg-red-500/10 text-red-500 border-red-500/20' : 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20'">
                                            <component :is="user.banned ? Ban : CheckCircle2" class="h-3 w-3" />
                                            {{ user.banned ? 'Banned' : 'Active' }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-[10px] text-muted-foreground font-bold uppercase tracking-widest">
                                        {{ user.created_at }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <Button @click="openEditModal(user)" variant="ghost" size="icon" class="size-8 rounded-full bg-white/5 border border-white/10 hover:bg-brand-primary hover:border-brand-primary hover:text-white transition-all">
                                                <Edit2 class="h-3.5 w-3.5" />
                                            </Button>
                                            <Button @click="deleteUser(user)" variant="ghost" size="icon" class="size-8 rounded-full bg-white/5 border border-white/10 hover:bg-red-500 hover:border-red-500 hover:text-white transition-all">
                                                <Trash2 class="h-3.5 w-3.5" />
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </Card>

                <!-- Pagination -->
                <div v-if="users.last_page > 1" class="flex justify-center">
                    <Pagination :links="users.links" />
                </div>
            </div>
        </div>

        <!-- Edit User Modal -->
        <Dialog :open="isEditModalOpen" @update:open="isEditModalOpen = $event">
            <DialogContent class="bg-neutral-950 border-white/10 text-white max-w-lg p-0 overflow-hidden backdrop-blur-3xl">
                <DialogHeader class="p-6 border-b border-white/5">
                    <DialogTitle class="text-2xl font-black uppercase italic tracking-tighter flex items-center gap-3">
                        <Edit2 class="h-6 w-6 text-brand-primary" />
                        Edit User Profile
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground">Modify permissions, balance and account status</DialogDescription>
                </DialogHeader>

                <div class="p-6 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Full Name</Label>
                            <Input v-model="form.name" class="bg-white/5 border-white/10 h-11" />
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Email Address</Label>
                            <Input v-model="form.email" type="email" class="bg-white/5 border-white/10 h-11" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Account Role</Label>
                            <select v-model="form.role" class="w-full h-11 rounded-md border border-white/10 bg-white/5 px-4 py-1 text-sm text-white focus:outline-none focus:ring-1 focus:ring-brand-primary custom-select appearance-none">
                                <option value="user">User</option>
                                <option value="reseller">Reseller</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Account Balance ($)</Label>
                            <div class="relative">
                                <DollarSign class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input v-model="form.balance" type="number" step="0.01" class="pl-10 bg-white/5 border-white/10 h-11 font-mono" />
                            </div>
                        </div>
                    </div>

                    <div v-if="form.role === 'reseller'" class="space-y-2 p-4 rounded-xl bg-blue-500/5 border border-blue-500/10">
                        <Label class="text-[10px] font-black uppercase tracking-widest text-blue-400 flex items-center gap-2 mb-2">
                            <Percent class="h-3 w-3" /> 
                            Custom Reseller Discount
                        </Label>
                        <div class="relative">
                            <Input 
                                :model-value="form.reseller_discount === null ? '' : form.reseller_discount" 
                                @update:model-value="val => form.reseller_discount = val === '' ? null : Number(val)"
                                type="number" 
                                placeholder="Leave empty for default (40% - 50%)" 
                                class="bg-white/5 border-white/10 h-11" 
                            />
                            <span class="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground font-black italic">%</span>
                        </div>
                        <p class="text-[10px] text-muted-foreground leading-tight mt-2 italic">
                            Overrides the default tiered logic. Set to 0 for no discount.
                        </p>
                    </div>

                    <div class="flex items-center justify-between p-4 rounded-xl" :class="form.banned ? 'bg-red-500/10 border border-red-500/20' : 'bg-white/5 border border-white/10'">
                        <div class="flex items-center gap-3">
                            <div class="size-10 rounded-full flex items-center justify-center" :class="form.banned ? 'bg-red-500/20 text-red-500' : 'bg-emerald-500/20 text-emerald-500'">
                                <Ban v-if="form.banned" class="h-5 w-5" />
                                <CheckCircle2 v-else class="h-5 w-5" />
                            </div>
                            <div>
                                <p class="text-sm font-black text-white italic leading-tight">Restrict Account Access</p>
                                <p class="text-[10px] text-muted-foreground">Immediately ban this user from the platform</p>
                            </div>
                        </div>
                        <button @click="form.banned = !form.banned" type="button" class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 focus:ring-offset-neutral-950" :class="form.banned ? 'bg-red-500' : 'bg-white/10'">
                            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform" :class="form.banned ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                    </div>
                </div>

                <DialogFooter class="p-6 border-t border-white/5 bg-white/[0.01]">
                    <Button @click="isEditModalOpen = false" variant="ghost" class="uppercase font-bold text-xs tracking-widest text-muted-foreground">Cancel</Button>
                    <Button @click="submitUpdate" class="bg-brand-primary hover:bg-brand-primary/90 text-white uppercase font-black italic tracking-tight px-8" :disabled="form.processing">
                        {{ form.processing ? 'Syncing...' : 'Save Changes' }}
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
    background-position: right 0.75rem center;
    background-size: 1.25em;
}

select option {
    background-color: #0c0c0c;
    color: white;
}
</style>

