<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { 
    Dialog, 
    DialogContent, 
    DialogDescription, 
    DialogFooter, 
    DialogHeader, 
    DialogTitle, 
    DialogTrigger 
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { PlusCircle, Loader2 } from 'lucide-vue-next';
import workspaceRoutes from '@/routes/reseller/workspace';

const props = defineProps<{
    workspaceId: number;
}>();

const isOpen = ref(false);

const form = useForm({
    email: '',
    role: 'reseller',
    permissions: ['view'],
});

const permissionOptions = [
    { id: 'generate', label: 'Generate Keys' },
    { id: 'view', label: 'View Keys' },
    { id: 'reset', label: 'Reset HWID' },
    { id: 'access_api', label: 'API & Documentation' },
];

const togglePermission = (id: string, checked: boolean) => {
    if (checked) {
        if (!form.permissions.includes(id)) {
            form.permissions.push(id);
        }
    } else {
        form.permissions = form.permissions.filter(p => p !== id);
    }
};

const submit = () => {
    form.post(workspaceRoutes.invitations.store(props.workspaceId).url, {
        onSuccess: () => {
            isOpen.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Dialog v-model:open="isOpen">
        <DialogTrigger asChild>
            <Button class="bg-brand-primary hover:bg-brand-primary/90 text-white font-black h-11 px-6 rounded-2xl shadow-lg shadow-brand-primary/20 transition-all active:scale-95 flex gap-2">
                <PlusCircle class="size-5" />
                Invite Member
            </Button>
        </DialogTrigger>
        <DialogContent class="sm:max-w-[425px] bg-sidebar border-white/5 text-white rounded-[2rem]">
            <DialogHeader>
                <DialogTitle class="text-2xl font-black">Invite Team Member</DialogTitle>
                <DialogDescription class="text-muted-foreground font-bold">
                    Invite a registered user to join your workspace.
                </DialogDescription>
            </DialogHeader>

            <form @submit.prevent="submit" class="space-y-6 py-4">
                <div class="space-y-2">
                    <Label for="email" class="text-sm font-black uppercase tracking-widest text-muted-foreground/50">Email Address</Label>
                    <Input 
                        id="email" 
                        v-model="form.email" 
                        placeholder="user@example.com" 
                        class="bg-white/5 border-white/10 h-12 rounded-xl focus:ring-brand-primary/20 focus:border-brand-primary"
                        required
                    />
                    <p v-if="form.errors.email" class="text-xs text-red-500 font-bold">{{ form.errors.email }}</p>
                </div>

                <div class="space-y-4">
                    <Label class="text-sm font-black uppercase tracking-widest text-muted-foreground/50">Workspace Role</Label>
                    <div class="grid grid-cols-2 gap-3">
                        <button 
                            type="button"
                            @click="form.role = 'reseller'"
                            :class="[
                                'h-12 rounded-xl border font-bold transition-all',
                                form.role === 'reseller' 
                                    ? 'bg-brand-primary/10 border-brand-primary text-brand-primary' 
                                    : 'bg-white/5 border-white/10 text-muted-foreground hover:bg-white/10'
                            ]"
                        >
                            Reseller
                        </button>
                        <button 
                            type="button"
                            @click="form.role = 'manager'"
                            :class="[
                                'h-12 rounded-xl border font-bold transition-all',
                                form.role === 'manager' 
                                    ? 'bg-brand-primary/10 border-brand-primary text-brand-primary' 
                                    : 'bg-white/5 border-white/10 text-muted-foreground hover:bg-white/10'
                            ]"
                        >
                            Manager
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <Label class="text-sm font-black uppercase tracking-widest text-muted-foreground/50">Permissions</Label>
                    <div class="grid grid-cols-2 gap-4">
                        <div v-for="opt in permissionOptions" :key="opt.id" class="flex items-center space-x-3">
                            <Checkbox 
                                :id="opt.id" 
                                :checked="form.permissions.includes(opt.id)"
                                @update:checked="(val: boolean) => togglePermission(opt.id, val)"
                                class="border-white/20 data-[state=checked]:bg-brand-primary data-[state=checked]:border-brand-primary"
                            />
                            <label :for="opt.id" class="text-sm font-bold text-muted-foreground cursor-pointer select-none">
                                {{ opt.label }}
                            </label>
                        </div>
                    </div>
                </div>

                <DialogFooter class="pt-4">
                    <Button 
                        type="submit" 
                        :disabled="form.processing"
                        class="w-full bg-brand-primary hover:bg-brand-primary/90 text-white font-black h-12 rounded-2xl shadow-xl shadow-brand-primary/20"
                    >
                        <Loader2 v-if="form.processing" class="mr-2 h-4 w-4 animate-spin" />
                        Send Invitation
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>
</template>
