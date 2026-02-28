<script setup lang="ts">
import { ref } from 'vue';
import { Head, useForm, Link } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { ShieldCheck, Mail, ArrowRight, AlertCircle, Loader2 } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent } from '@/components/ui/card';

defineProps<{
    status?: string;
}>();

const form = useForm({
    code: '',
});

const submit = () => {
    form.post('/auth/verify-ip', {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <Head title="IP Verification" />

    <FrontLayout>
        <div class="min-h-[80vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
            <div class="max-w-md w-full space-y-8">
                <div class="text-center">
                    <div class="mx-auto h-16 w-16 bg-brand-primary/10 rounded-2xl flex items-center justify-center border border-brand-primary/20 mb-6">
                        <ShieldCheck class="h-8 w-8 text-brand-primary" />
                    </div>
                    <h2 class="text-4xl font-black text-white tracking-widest uppercase italic mb-2">New IP Detected</h2>
                    <p class="text-muted-foreground text-sm">
                        For your security, we've sent a verification code to your email address. Please enter it below to authorize this login.
                    </p>
                </div>

                <Card class="bg-white/5 border-white/10 backdrop-blur-xl border-t-brand-primary/50 overflow-hidden">
                    <CardContent class="p-8">
                        <form @submit.prevent="submit" class="space-y-6">
                            <div v-if="status" class="bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl text-xs font-bold flex items-center gap-3">
                                <AlertCircle class="h-4 w-4" />
                                {{ status }}
                            </div>

                            <div class="space-y-2">
                                <Label for="code" class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Verification Code</Label>
                                <div class="relative">
                                    <Mail class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input 
                                        id="code"
                                        v-model="form.code"
                                        placeholder="000000"
                                        class="pl-10 bg-white/5 border-white/10 h-12 text-center text-lg tracking-[0.5em] font-mono"
                                        required
                                        autofocus
                                        autocomplete="one-time-code"
                                    />
                                </div>
                                <div v-if="form.errors.code" class="text-red-500 text-[10px] font-bold uppercase mt-1 italic tracking-widest">
                                    {{ form.errors.code }}
                                </div>
                            </div>

                            <Button 
                                type="submit" 
                                class="w-full h-12 bg-white text-black hover:bg-neutral-200 uppercase font-black italic tracking-wider group transition-all"
                                :disabled="form.processing"
                            >
                                <Loader2 v-if="form.processing" class="h-4 w-4 animate-spin mr-2" />
                                {{ form.processing ? 'Verifying...' : 'Authorize Login' }}
                                <ArrowRight v-if="!form.processing" class="ml-2 h-4 w-4 group-hover:translate-x-1 transition-transform" />
                            </Button>

                            <div class="text-center pt-4 border-t border-white/5">
                                <Link 
                                    href="/login" 
                                    class="text-[10px] font-black uppercase tracking-widest text-muted-foreground hover:text-white transition-colors"
                                >
                                    Cancel & Return to Login
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <p class="text-center text-[10px] text-muted-foreground uppercase font-bold tracking-widest opacity-50">
                    Secure Login System &bull; Powered by RLBmods
                </p>
            </div>
        </div>
    </FrontLayout>
</template>
