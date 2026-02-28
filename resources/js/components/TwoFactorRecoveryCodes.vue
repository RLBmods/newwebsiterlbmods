<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { Eye, EyeOff, LockKeyhole, RefreshCw, Copy, Check } from 'lucide-vue-next';
import { nextTick, onMounted, ref, useTemplateRef } from 'vue';
import AlertError from '@/components/AlertError.vue';
import { Button } from '@/components/ui/button';
import { useTwoFactorAuth } from '@/composables/useTwoFactorAuth';
import { regenerateRecoveryCodes } from '@/routes/two-factor';
import { useClipboard } from '@vueuse/core';

const { recoveryCodesList, fetchRecoveryCodes, errors } = useTwoFactorAuth();
const isRecoveryCodesVisible = ref<boolean>(false);
const recoveryCodeSectionRef = useTemplateRef('recoveryCodeSectionRef');
const { copy, copied } = useClipboard();

const toggleRecoveryCodesVisibility = async () => {
    if (!isRecoveryCodesVisible.value && !recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }

    isRecoveryCodesVisible.value = !isRecoveryCodesVisible.value;

    if (isRecoveryCodesVisible.value) {
        await nextTick();
        recoveryCodeSectionRef.value?.scrollIntoView({ behavior: 'smooth' });
    }
};

onMounted(async () => {
    if (!recoveryCodesList.value.length) {
        await fetchRecoveryCodes();
    }
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-col gap-4">
            <p class="text-sm font-bold text-muted-foreground leading-relaxed">
                Recovery codes let you regain access if you lose your 2FA device. 
                Store them in a secure password manager and never share them.
            </p>

            <div class="flex flex-wrap items-center gap-3">
                <Button 
                    @click="toggleRecoveryCodesVisibility" 
                    variant="secondary"
                    class="h-10 rounded-xl bg-white/5 border-white/10 hover:bg-white/10 text-white font-bold px-4"
                >
                    <component
                        :is="isRecoveryCodesVisible ? EyeOff : Eye"
                        class="size-4 mr-2"
                    />
                    {{ isRecoveryCodesVisible ? 'Hide' : 'View' }} Codes
                </Button>

                <Form
                    v-if="isRecoveryCodesVisible && recoveryCodesList.length"
                    v-bind="regenerateRecoveryCodes.form()"
                    method="post"
                    :options="{ preserveScroll: true }"
                    @success="fetchRecoveryCodes"
                    #default="{ processing }"
                >
                    <Button
                        variant="secondary"
                        type="submit"
                        :disabled="processing"
                        class="h-10 rounded-xl bg-white/5 border-white/10 hover:bg-white/10 text-white font-bold px-4"
                    >
                        <RefreshCw class="size-4 mr-2" :class="{ 'animate-spin': processing }" /> 
                        Regenerate
                    </Button>
                </Form>
            </div>
        </div>

        <div
            :class="[
                'relative overflow-hidden transition-all duration-500 ease-in-out',
                isRecoveryCodesVisible
                    ? 'max-h-[500px] opacity-100'
                    : 'max-h-0 opacity-0',
            ]"
        >
            <div v-if="errors?.length" class="mt-4">
                <AlertError :errors="errors" />
            </div>
            
            <div v-else class="mt-4 space-y-4">
                <div
                    ref="recoveryCodeSectionRef"
                    class="grid grid-cols-2 gap-3 rounded-2xl bg-black/40 border border-white/5 p-6 font-mono text-sm"
                >
                    <div v-if="!recoveryCodesList.length" class="col-span-2 space-y-3">
                        <div
                            v-for="n in 8"
                            :key="n"
                            class="h-4 animate-pulse rounded bg-white/5"
                        ></div>
                    </div>
                    <template v-else>
                        <div
                            v-for="(code, index) in recoveryCodesList"
                            :key="index"
                            class="flex items-center justify-between group p-2 rounded-lg hover:bg-white/5 transition-colors"
                        >
                            <span class="text-white/80 font-bold tracking-wider">{{ code }}</span>
                            <button 
                                @click="copy(code)" 
                                class="opacity-0 group-hover:opacity-100 transition-opacity p-1 text-muted-foreground hover:text-white"
                            >
                                <Copy class="size-3" />
                            </button>
                        </div>
                    </template>
                </div>
                
                <div class="flex items-start gap-2 p-3 rounded-xl bg-blue-500/10 border border-blue-500/20">
                    <ShieldAlert class="size-4 text-blue-400 shrink-0 mt-0.5" />
                    <p class="text-xs font-bold text-blue-200/80">
                        Each recovery code can be used once. If you use one, it will be removed. 
                        Regenerate them if you run low.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
