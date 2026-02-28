<script setup lang="ts">
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { type BreadcrumbItem } from '@/types';
import { 
    Users, 
    Settings, 
    PlusCircle, 
    Trash2, 
    Shield, 
    Mail, 
    Clock, 
    ChevronRight,
    Search,
    UserPlus,
    LayoutGrid,
    CheckCircle2,
    XCircle,
    ChevronDown,
    MoreHorizontal
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { Separator } from '@/components/ui/separator';
import InviteModal from '@/components/Reseller/Workspace/InviteModal.vue';
import workspaceRoutes from '@/routes/reseller/workspace';

interface Workspace {
    id: number;
    name: string;
    owner_id: number;
}

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    permissions: string[];
    avatar: string | null;
}

interface Invitation {
    id: number;
    email: string;
    role: string;
    permissions: string[];
    created_at: string;
}

const props = defineProps<{
    currentWorkspace: Workspace | null;
    members: Member[];
    invitations: Invitation[];
    ownedWorkspaces: Workspace[];
    memberWorkspaces: Workspace[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Reseller Portal',
        href: '/reseller/dashboard',
    },
    {
        title: 'Workspace Management',
        href: '/reseller/workspace',
    },
];

const newWorkspaceForm = useForm({
    name: '',
});

const createWorkspace = () => {
    newWorkspaceForm.post(workspaceRoutes.store().url, {
        onSuccess: () => newWorkspaceForm.reset(),
    });
};

const switchWorkspace = (id: number) => {
    router.post(workspaceRoutes.switch(id).url);
};

const removeMember = (memberId: number) => {
    if (confirm('Are you sure you want to remove this member?')) {
        router.delete(workspaceRoutes.members.destroy({ 
            workspace: props.currentWorkspace!.id, 
            member: memberId 
        }).url);
    }
};

const cancelInvitation = (invitationId: number) => {
    router.delete(workspaceRoutes.invitations.destroy(invitationId).url);
};

const deleteWorkspace = (id: number) => {
    if (confirm('Are you sure you want to delete this workspace? This action cannot be undone and all members will be removed.')) {
        router.delete(workspaceRoutes.destroy(id).url);
    }
};

const getRoleBadge = (role: string) => {
    switch (role) {
        case 'owner': return 'bg-brand-primary/20 text-brand-primary border-brand-primary/30';
        case 'manager': return 'bg-blue-500/20 text-blue-400 border-blue-500/30';
        case 'reseller': return 'bg-emerald-500/20 text-emerald-400 border-emerald-500/30';
        default: return 'bg-muted/20 text-muted-foreground border-white/5';
    }
};
</script>

<template>
    <Head title="Workspace Management" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-8 p-4 md:p-8 bg-brand-bg/30">
            
            <!-- Header -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-black text-white">Team Workspaces</h1>
                    <p class="text-muted-foreground font-bold">Collaborate with your managers and sub-resellers.</p>
                </div>
                
                <div v-if="currentWorkspace" class="flex items-center gap-3">
                    <InviteModal :workspaceId="currentWorkspace.id" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                <!-- Sidebar: Workspace Selection -->
                <div class="lg:col-span-1 space-y-6">
                    <div class="rounded-[2.5rem] bg-sidebar/40 border border-white/5 backdrop-blur-xl p-6">
                        <h3 class="text-xs font-black uppercase tracking-[0.2em] text-muted-foreground/50 mb-6">Select Workspace</h3>
                        
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" class="w-full h-14 justify-between bg-white/5 border-white/5 hover:bg-white/10 hover:border-white/10 text-white rounded-2xl px-4 transition-all">
                                    <div class="flex items-center gap-3 truncate">
                                        <div :class="['p-2 rounded-xl', currentWorkspace?.owner_id === $page.props.auth.user.id ? 'bg-brand-primary/10 text-brand-primary' : 'bg-blue-500/10 text-blue-500']">
                                            <LayoutGrid v-if="currentWorkspace" class="size-4" />
                                            <PlusCircle v-else class="size-4" />
                                        </div>
                                        <div class="flex flex-col items-start truncate">
                                            <span class="text-xs font-black uppercase tracking-widest text-muted-foreground/50 leading-none mb-1">Active Team</span>
                                            <span class="font-bold truncate text-sm leading-none">{{ currentWorkspace?.name || 'Select Workspace' }}</span>
                                        </div>
                                    </div>
                                    <ChevronDown class="size-4 text-muted-foreground/50" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="start" class="w-[280px] bg-sidebar/95 backdrop-blur-xl border-white/10 text-white rounded-[2rem] p-2 shadow-2xl">
                                <div class="px-4 py-3 mb-2">
                                    <h4 class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/40">Switch Workspace</h4>
                                </div>
                                
                                <!-- Owned Workspaces -->
                                <DropdownMenuItem v-for="ws in ownedWorkspaces" :key="ws.id" 
                                    @click="switchWorkspace(ws.id)"
                                    class="flex items-center justify-between p-3 rounded-2xl cursor-pointer focus:bg-brand-primary/10 focus:text-white transition-all group mb-1"
                                >
                                    <div class="flex items-center gap-3">
                                        <Shield class="size-4 text-brand-primary" />
                                        <span class="font-bold text-sm">{{ ws.name }}</span>
                                    </div>
                                    <CheckCircle2 v-if="currentWorkspace?.id === ws.id" class="size-4 text-brand-primary" />
                                </DropdownMenuItem>

                                <Separator v-if="memberWorkspaces.length > 0" class="my-2 bg-white/5" />

                                <!-- Joined Workspaces -->
                                <DropdownMenuItem v-for="ws in memberWorkspaces" :key="ws.id" 
                                    @click="switchWorkspace(ws.id)"
                                    class="flex items-center justify-between p-3 rounded-2xl cursor-pointer focus:bg-blue-500/10 focus:text-white transition-all group mb-1"
                                >
                                    <div class="flex items-center gap-3">
                                        <Users class="size-4 text-blue-500" />
                                        <span class="font-bold text-sm">{{ ws.name }}</span>
                                    </div>
                                    <CheckCircle2 v-if="currentWorkspace?.id === ws.id" class="size-4 text-blue-500" />
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>

                        <Separator class="my-6 bg-white/5" />

                        <!-- Create New Workspace -->
                        <form @submit.prevent="createWorkspace" class="space-y-4">
                            <Input 
                                v-model="newWorkspaceForm.name" 
                                placeholder="New team name..." 
                                class="bg-black/20 border-white/5 h-11 rounded-xl text-sm"
                                required
                            />
                            <Button 
                                type="submit" 
                                variant="outline" 
                                :disabled="newWorkspaceForm.processing"
                                class="w-full h-11 rounded-xl border-white/10 hover:bg-white/5 font-black text-xs uppercase tracking-widest"
                            >
                                Create Workspace
                            </Button>
                        </form>
                    </div>
                </div>

                <!-- Main Content: Member List -->
                <div class="lg:col-span-3 space-y-8">
                    <div v-if="currentWorkspace" class="space-y-8">
                        <!-- Members Table -->
                        <div class="rounded-[3rem] bg-sidebar/40 border border-white/5 backdrop-blur-xl overflow-hidden">
                            <div class="p-8 pb-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-4">
                                    <h3 class="text-xl font-black text-white">Workspace Members</h3>
                                    <Badge variant="outline" class="rounded-lg bg-white/5 border-white/10 font-black text-[10px]">{{ members.length }} Members</Badge>
                                </div>
                                
                                <div v-if="currentWorkspace.owner_id === $page.props.auth.user.id" class="flex items-center gap-2">
                                    <Button 
                                        variant="ghost" 
                                        @click="deleteWorkspace(currentWorkspace.id)"
                                        class="h-9 px-4 rounded-xl text-red-400 hover:text-red-400 hover:bg-red-400/10 font-black text-[10px] uppercase tracking-widest transition-all"
                                    >
                                        <Trash2 class="size-3.5 mr-2" />
                                        Delete Workspace
                                    </Button>
                                </div>
                            </div>
                            
                            <div class="overflow-x-auto">
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground/30">
                                            <th class="p-8 py-6">Member</th>
                                            <th class="p-8 py-6">Role</th>
                                            <th class="p-8 py-6">Permissions</th>
                                            <th class="p-8 py-6 text-right">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-white/5 font-bold">
                                        <tr v-for="member in members" :key="member.id" class="group hover:bg-white/(2) transition-colors">
                                            <td class="p-8">
                                                <div class="flex items-center gap-4">
                                                    <Avatar class="h-10 w-10 rounded-xl border border-white/10 shadow-lg shadow-black/40">
                                                        <AvatarImage :src="member.avatar || ''" />
                                                        <AvatarFallback class="bg-brand-primary/10 text-brand-primary font-black uppercase text-xs">
                                                            {{ member.name.substring(0, 2) }}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <p class="text-sm text-white font-black">{{ member.name }}</p>
                                                        <p class="text-xs text-muted-foreground/60">{{ member.email }}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="p-8">
                                                <Badge :class="[getRoleBadge(member.role), 'rounded-lg font-black uppercase text-[9px] tracking-wider px-2 py-0.5']">
                                                    {{ member.role }}
                                                </Badge>
                                            </td>
                                            <td class="p-8">
                                                <div class="flex flex-wrap gap-1.5">
                                                    <Badge v-for="perm in member.permissions" :key="perm" variant="outline" class="rounded-md border-white/5 bg-white/5 text-[9px] font-bold text-muted-foreground/70 lowercase px-1.5 h-5">
                                                        {{ perm }}
                                                    </Badge>
                                                    <span v-if="!member.permissions || member.permissions.length === 0" class="text-[10px] text-muted-foreground/30 font-black italic">None</span>
                                                </div>
                                            </td>
                                            <td class="p-8 text-right">
                                                <DropdownMenu v-if="member.role !== 'owner'">
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" class="h-8 w-8 p-0 rounded-lg hover:bg-white/10 transition-colors">
                                                            <MoreHorizontal class="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end" class="bg-sidebar border-white/10 text-white rounded-xl shadow-2xl">
                                                        <DropdownMenuItem @click="removeMember(member.id)" class="text-red-400 focus:text-red-400 focus:bg-red-400/10 cursor-pointer font-black px-4 py-2.5 rounded-lg flex gap-2">
                                                            <Trash2 class="size-4" />
                                                            Remove Member
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pending Invitations -->
                        <div v-if="invitations.length > 0" class="rounded-[3rem] bg-sidebar/40 border border-white/5 backdrop-blur-xl overflow-hidden">
                            <div class="p-8 pb-4 border-b border-white/5 flex items-center justify-between">
                                <h3 class="text-xl font-black text-white">Pending Invitations</h3>
                                <Clock class="size-5 text-muted-foreground/30" />
                            </div>
                            
                            <div class="p-8 grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div v-for="invite in invitations" :key="invite.id" class="flex items-center justify-between bg-white/5 border border-white/5 rounded-[2rem] p-6 group hover:border-brand-primary/20 transition-all">
                                    <div class="flex items-center gap-4">
                                        <div class="h-12 w-12 rounded-2xl bg-brand-primary/5 flex items-center justify-center text-brand-primary border border-brand-primary/10 shadow-xl">
                                            <Mail class="h-6 w-6" />
                                        </div>
                                        <div>
                                            <p class="text-sm font-black text-white">{{ invite.email }}</p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <Badge :class="[getRoleBadge(invite.role), 'rounded-md font-black uppercase text-[8px] px-1.5 h-4']">
                                                    {{ invite.role }}
                                                </Badge>
                                                <span class="text-[9px] font-bold text-muted-foreground/30">Expires in 7 days</span>
                                            </div>
                                        </div>
                                    </div>
                                    <Button 
                                        variant="ghost" 
                                        size="icon" 
                                        @click="cancelInvitation(invite.id)"
                                        class="h-10 w-10 text-muted-foreground/40 hover:text-red-400 hover:bg-red-400/10 rounded-2xl transition-all"
                                    >
                                        <XCircle class="size-5" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Empty State -->
                    <div v-else class="h-[60vh] flex flex-col items-center justify-center text-center p-8 bg-sidebar/20 rounded-[4rem] border border-dashed border-white/5">
                        <div class="h-24 w-24 rounded-[2rem] bg-brand-primary/10 flex items-center justify-center text-brand-primary mb-8 animate-pulse shadow-2xl shadow-brand-primary/20">
                            <LayoutGrid class="h-12 w-12" />
                        </div>
                        <h2 class="text-3xl font-black text-white mb-4">No Workspace Selected</h2>
                        <p class="text-muted-foreground max-w-sm font-bold mb-8">Select an existing workspace from the sidebar or create a new one to start collaborating with your team.</p>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <Button variant="outline" class="h-12 px-8 rounded-2xl border-white/10 hover:bg-white/5 font-black uppercase text-xs tracking-widest">
                                Learn More
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.active-pulse {
    animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .5; }
}
</style>
