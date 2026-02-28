<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { useClipboard } from '@vueuse/core';
import { Check, Copy, ScanLine, ShieldCheck, Smartphone, Key } from 'lucide-vue-next';
import { computed, nextTick, ref, useTemplateRef, watch } from 'vue';
import AlertError from '@/components/AlertError.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { useAppearance } from '@/composables/useAppearance';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import type { TwoFactorConfigContent } from '@/types';
import { confirm } from '@/routes/two-factor';

type Props = {
    requiresConfirmation: boolean;
    twoFactorEnabled: boolean;
};

const { resolvedAppearance } = useAppearance();

const props = defineProps<Props>();
const isOpen = defineModel<boolean>('isOpen');

const { copy, copied } = useClipboard();
const { qrCodeSvg, manualSetupKey, clearSetupData, fetchSetupData, errors } =
    useTwoFactorAuth();

const showVerificationStep = ref(false);
const code = ref<string>('');

const pinInputContainerRef = useTemplateRef('pinInputContainerRef');

const modalConfig = computed<TwoFactorConfigContent>(() => {
    if (props.twoFactorEnabled) {
        return {
            title: '2FA Securely Enabled',
            description:
                'Your account is now protected. Please ensure you have saved your recovery codes.',
            buttonText: 'Finish Setup',
        };
    }

    if (showVerificationStep.value) {
        return {
            title: 'Verify Device',
            description: 'Enter the 6-digit code currently displayed in your authenticator app.',
            buttonText: 'Confirm & Enable',
        };
    }

    return {
        title: 'Setup Authenticator',
        description:
            'To finish enabling two-factor authentication, scan the QR code or enter the setup key in your app.',
        buttonText: 'Next: Verify Code',
    };
});

const handleModalNextStep = () => {
    if (props.requiresConfirmation) {
        showVerificationStep.value = true;

        nextTick(() => {
            pinInputContainerRef.value?.querySelector('input')?.focus();
        });

        return;
    }

    clearSetupData();
    isOpen.value = false;
};

const resetModalState = () => {
    if (props.twoFactorEnabled) {
        clearSetupData();
    }

    showVerificationStep.value = false;
    code.value = '';
};

watch(
    () => isOpen.value,
    async (isOpen) => {
        if (!isOpen) {
            resetModalState();
            return;
        }

        if (!qrCodeSvg.value) {
            await fetchSetupData();
        }
    },
);
</script>

<template>
    <Dialog :open="isOpen" @update:open="isOpen = $event">
        <DialogContent class="sm:max-w-md border-white/5 bg-[#0A0A0A] p-0 overflow-hidden rounded-[2rem]">
            <!-- Custom Header Area -->
            <div class="relative h-32 bg-gradient-to-br from-brand-primary/20 to-transparent flex items-center justify-center overflow-hidden border-b border-white/5">
                <div class="absolute inset-0 opacity-20" style="background-image: radial-gradient(circle at 2px 2px, white 1px, transparent 0); background-size: 24px 24px;"></div>
                
                <div class="relative size-16 rounded-[1.25rem] bg-black border border-white/10 flex items-center justify-center shadow-2xl">
                    <div class="absolute inset-0 bg-brand-primary/10 rounded-[1.125rem] animate-pulse"></div>
                    <Smartphone v-if="!twoFactorEnabled" class="size-8 text-brand-primary" />
                    <ShieldCheck v-else class="size-8 text-emerald-400" />
                </div>
            </div>

            <div class="p-8 pt-6 space-y-6">
                <div class="text-center space-y-2">
                    <DialogTitle class="text-2xl font-black text-white tracking-tight">{{ modalConfig.title }}</DialogTitle>
                    <DialogDescription class="text-muted-foreground font-bold">
                        {{ modalConfig.description }}
                    </DialogDescription>
                </div>

                <div class="relative flex w-auto flex-col items-center justify-center space-y-6">
                    <template v-if="!showVerificationStep">
                        <AlertError v-if="errors?.length" :errors="errors" />
                        <template v-else>
                            <!-- QR Code Container -->
                            <div class="group relative">
                                <div class="absolute -inset-4 rounded-[2.5rem] bg-gradient-to-tr from-brand-primary/20 to-transparent blur-2xl opacity-0 group-hover:opacity-100 transition-opacity duration-500"></div>
                                <div class="relative p-4 rounded-[2rem] bg-white/5 border border-white/10">
                                    <div class="aspect-square w-48 overflow-hidden rounded-2xl bg-white flex items-center justify-center relative shadow-inner">
                                        <div v-if="!qrCodeSvg" class="flex flex-col items-center gap-2">
                                            <Spinner class="size-6 text-brand-primary" />
                                            <span class="text-[10px] font-black uppercase text-brand-primary tracking-widest">Generating</span>
                                        </div>
                                        <div
                                            v-else
                                            v-html="qrCodeSvg"
                                            class="size-full p-4"
                                            :style="{
                                                filter: resolvedAppearance === 'dark' ? 'invert(1) brightness(0)' : undefined,
                                            }"
                                        />
                                        <!-- Scan Animation -->
                                        <div v-if="qrCodeSvg" class="absolute inset-x-0 h-0.5 bg-brand-primary shadow-[0_0_15px_rgba(var(--brand-primary-rgb),0.5)] animate-scan"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="w-full space-y-4">
                                <Button class="w-full h-12 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-black shadow-xl shadow-brand-primary/20 transition-all active:scale-95" @click="handleModalNextStep">
                                    {{ modalConfig.buttonText }}
                                </Button>

                                <div class="flex flex-col items-center gap-4">
                                    <div class="flex items-center gap-4 w-full px-2">
                                        <div class="h-px bg-white/5 flex-1"></div>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/50">Manual Setup</span>
                                        <div class="h-px bg-white/5 flex-1"></div>
                                    </div>

                                    <div class="w-full relative group">
                                        <div v-if="!manualSetupKey" class="h-12 w-full rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center">
                                            <Spinner class="size-4" />
                                        </div>
                                        <template v-else>
                                            <input
                                                type="text"
                                                readonly
                                                :value="manualSetupKey"
                                                class="h-12 w-full rounded-2xl bg-white/5 border border-white/10 pl-11 pr-12 text-center font-mono text-sm font-bold text-white tracking-widest focus:outline-none"
                                            />
                                            <Key class="absolute left-4 top-1/2 -translate-y-1/2 size-4 text-muted-foreground/50" />
                                            <button
                                                @click="copy(manualSetupKey || '')"
                                                class="absolute right-2 top-1/2 -translate-y-1/2 size-8 flex items-center justify-center rounded-xl bg-white/5 hover:bg-white/10 border border-white/5 transition-all"
                                            >
                                                <Check v-if="copied" class="size-3.5 text-emerald-400" />
                                                <Copy v-else class="size-3.5 text-muted-foreground" />
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </template>

                    <template v-else>
                        <Form
                            v-bind="confirm.form()"
                            reset-on-error
                            @finish="code = ''"
                            @success="isOpen = false"
                            class="w-full space-y-8"
                            v-slot="{ errors, processing }"
                        >
                            <input type="hidden" name="code" :value="code" />
                            <div
                                ref="pinInputContainerRef"
                                class="flex flex-col items-center gap-6"
                            >
                                <InputOTP
                                    id="otp"
                                    v-model="code"
                                    :maxlength="6"
                                    :disabled="processing"
                                >
                                    <InputOTPGroup class="gap-3">
                                        <InputOTPSlot
                                            v-for="index in 6"
                                            :key="index"
                                            :index="index - 1"
                                            class="h-12 w-10 border-white/10 bg-white/5 rounded-xl font-black text-lg focus:ring-brand-primary"
                                        />
                                    </InputOTPGroup>
                                </InputOTP>
                                
                                <InputError
                                    class="font-bold text-center"
                                    :message="errors.code"
                                />

                                <div class="grid grid-cols-2 gap-4 w-full">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        class="h-12 rounded-2xl border-white/10 bg-white/5 hover:bg-white/10 text-white font-black"
                                        @click="showVerificationStep = false"
                                        :disabled="processing"
                                    >
                                        Back
                                    </Button>
                                    <Button
                                        type="submit"
                                        class="h-12 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-black shadow-xl shadow-brand-primary/20"
                                        :disabled="processing || code.length < 6"
                                    >
                                        <Spinner v-if="processing" class="mr-2" />
                                        Confirm
                                    </Button>
                                </div>
                            </div>
                        </Form>
                    </template>
                </div>
            </div>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
@keyframes scan {
    0% { top: 0; }
    100% { top: 100%; }
}

.animate-scan {
    animation: scan 2s linear infinite;
}
</style>
