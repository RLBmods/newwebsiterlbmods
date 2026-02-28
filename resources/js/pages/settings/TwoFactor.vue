<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ShieldBan, ShieldCheck, ShieldAlert, KeyRound } from 'lucide-vue-next';
import { onUnmounted, ref } from 'vue';
import TwoFactorRecoveryCodes from '@/components/TwoFactorRecoveryCodes.vue';
import TwoFactorSetupModal from '@/components/TwoFactorSetupModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';
import { disable, enable, show } from '@/routes/two-factor';

type Props = {
    requiresConfirmation?: boolean;
    twoFactorEnabled?: boolean;
};

withDefaults(defineProps<Props>(), {
    requiresConfirmation: false,
    twoFactorEnabled: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Two-Factor Authentication',
        href: show.url(),
    },
];

const { hasSetupData, clearTwoFactorAuthData } = useTwoFactorAuth();
const showSetupModal = ref<boolean>(false);

onUnmounted(() => {
    clearTwoFactorAuthData();
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Two-Factor Authentication" />

        <SettingsLayout>
            <div class="space-y-8 max-w-5xl">
                <!-- Main 2FA Card -->
                <Card class="overflow-hidden border-white/5 bg-white/[0.02]">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2 text-xl font-black">
                            <ShieldCheck v-if="twoFactorEnabled" class="size-6 text-emerald-400" />
                            <ShieldAlert v-else class="size-6 text-amber-400" />
                            Two-Factor Authentication
                        </CardTitle>
                        <CardDescription>
                            Add an extra layer of security to your account using a time-based one-time password (TOTP).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div v-if="!twoFactorEnabled" class="space-y-6">
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 w-fit">
                                <div class="size-2 rounded-full bg-amber-500 animate-pulse" />
                                <span class="text-xs font-black uppercase tracking-wider text-amber-400">Security Recommendation</span>
                            </div>

                            <p class="text-sm font-bold text-muted-foreground leading-relaxed">
                                When you enable two-factor authentication, you will be prompted for a secure pin during login. 
                                This pin can be retrieved from a TOTP-supported application like Google Authenticator or Authy on your phone.
                            </p>

                            <div class="pt-2">
                                <Button
                                    v-if="hasSetupData"
                                    @click="showSetupModal = true"
                                    class="h-12 px-8 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-black shadow-xl shadow-brand-primary/20 transition-all active:scale-95 flex gap-2"
                                >
                                    <ShieldCheck class="size-5" />
                                    Continue Setup
                                </Button>
                                <Form
                                    v-else
                                    v-bind="enable.form()"
                                    @success="showSetupModal = true"
                                    #default="{ processing }"
                                >
                                    <Button 
                                        type="submit" 
                                        :disabled="processing"
                                        class="h-12 px-8 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-black shadow-xl shadow-brand-primary/20 transition-all active:scale-95 flex gap-2"
                                    >
                                        <ShieldCheck v-if="!processing" class="size-5" />
                                        <span v-if="processing">Enabling...</span>
                                        <span v-else>Enable 2FA</span>
                                    </Button>
                                </Form>
                            </div>
                        </div>

                        <div v-else class="space-y-8">
                            <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 w-fit">
                                <div class="size-2 rounded-full bg-emerald-500" />
                                <span class="text-xs font-black uppercase tracking-wider text-emerald-400">Protected by 2FA</span>
                            </div>

                            <p class="text-sm font-bold text-muted-foreground leading-relaxed">
                                Your account is currently secured with two-factor authentication. You will be prompted for a 
                                random pin from your authenticator app whenever you log in.
                            </p>

                            <!-- Recovery Codes Integrated -->
                            <div class="pt-4 border-t border-white/5">
                                <div class="flex items-center gap-2 mb-4">
                                    <KeyRound class="size-5 text-brand-primary" />
                                    <h3 class="font-black text-white">Recovery Access</h3>
                                </div>
                                <TwoFactorRecoveryCodes />
                            </div>

                            <div class="pt-6 border-t border-white/5">
                                <Form v-bind="disable.form()" #default="{ processing }">
                                    <Button
                                        variant="outline"
                                        type="submit"
                                        :disabled="processing"
                                        class="h-11 px-6 rounded-xl border-red-500/20 text-red-500 hover:bg-red-500/10 font-bold transition-all"
                                    >
                                        <ShieldBan class="mr-2 size-4" />
                                        Disable Two-Factor Authentication
                                    </Button>
                                </Form>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <TwoFactorSetupModal
                    v-model:isOpen="showSetupModal"
                    :requiresConfirmation="requiresConfirmation"
                    :twoFactorEnabled="twoFactorEnabled"
                />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
