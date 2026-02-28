<script setup lang="ts">
import { Form, Head, useForm, usePage } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { ref, onMounted, watch } from 'vue';
import { useToast } from 'vue-toastification';

defineProps<{
    status?: string;
}>();

const page = usePage();
const toast = useToast();
const digits = ref(['', '', '', '', '', '']);
const inputRefs = ref<HTMLInputElement[]>([]);

const form = useForm({
    code: '',
});

const resendForm = useForm({});

const handleInput = (index: number, e: Event) => {
    const target = e.target as HTMLInputElement;
    const val = target.value;

    if (val.length > 1) {
        digits.value[index] = val.slice(-1);
    }

    if (val && index < 5) {
        inputRefs.value[index + 1].focus();
    }
    
    updateCode();
};

const handleKeyDown = (index: number, e: KeyboardEvent) => {
    if (e.key === 'Backspace' && !digits.value[index] && index > 0) {
        inputRefs.value[index - 1].focus();
    }
};

const handlePaste = (e: ClipboardEvent) => {
    e.preventDefault();
    const pasteData = e.clipboardData?.getData('text').slice(0, 6) || '';
    if (!/^\d+$/.test(pasteData)) return;

    pasteData.split('').forEach((char, i) => {
        if (i < 6) digits.value[i] = char;
    });

    const nextIndex = Math.min(pasteData.length, 5);
    inputRefs.value[nextIndex].focus();
    updateCode();
};

const updateCode = () => {
    form.code = digits.value.join('');
};

const submit = () => {
    if (form.code.length !== 6) {
        toast.error('Please enter the full 6-digit code.');
        return;
    }

    form.post('/email/verify', {
        onFinish: () => form.reset('code'),
    });
};

const resend = () => {
    resendForm.post('/email/verification-notification', {
        onSuccess: () => toast.success('A new verification code has been sent!'),
    });
};

onMounted(() => {
    inputRefs.value[0]?.focus();
});

watch(() => form.code, (newVal) => {
    if (newVal.length === 6) {
        submit();
    }
});
</script>

<template>
    <AuthLayout
        title="Verify Your Account"
        description="We've sent a 6-digit verification code to your email address."
    >
        <Head title="Email verification" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-6 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 p-4 text-center text-sm font-bold text-emerald-400 animate-in fade-in zoom-in"
        >
            A new verification code has been sent to your email!
        </div>

        <div class="space-y-8">
            <div class="flex justify-between gap-3 px-2">
                <input
                    v-for="(digit, index) in digits"
                    :key="index"
                    ref="inputRefs"
                    v-model="digits[index]"
                    type="text"
                    maxlength="1"
                    inputmode="numeric"
                    class="h-14 w-12 rounded-xl bg-white/5 border border-white/10 text-center text-2xl font-black text-white focus:outline-none focus:ring-2 focus:ring-brand-primary/50 focus:border-brand-primary transition-all"
                    @input="handleInput(index, $event)"
                    @keydown="handleKeyDown(index, $event)"
                    @paste="handlePaste"
                />
            </div>

            <div v-if="form.errors.code" class="text-center text-sm font-bold text-red-500 animate-in shake-1">
                {{ form.errors.code }}
            </div>

            <div class="space-y-4">
                <Button 
                    @click="submit" 
                    :disabled="form.processing || form.code.length !== 6" 
                    class="w-full h-12 rounded-2xl bg-brand-primary hover:bg-brand-primary/90 text-white font-bold shadow-xl shadow-brand-primary/20 transition-all active:scale-95"
                >
                    <Spinner v-if="form.processing" class="mr-2" />
                    Verify Account
                </Button>

                <div class="flex flex-col items-center gap-4 pt-4">
                    <button
                        @click="resend"
                        :disabled="resendForm.processing"
                        class="text-sm font-bold text-muted-foreground hover:text-brand-primary transition-colors disabled:opacity-50"
                    >
                        Didn't receive the code? Resend
                    </button>

                    <TextLink
                        :href="logout()"
                        method="post"
                        as="button"
                        class="text-xs font-bold text-red-500/70 hover:text-red-500 transition-colors"
                    >
                        Log out
                    </TextLink>
                </div>
            </div>
        </div>
    </AuthLayout>
</template>

<style scoped>
.shake-1 {
    animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
}

@keyframes shake {
    10%, 90% { transform: translate3d(-1px, 0, 0); }
    20%, 80% { transform: translate3d(2px, 0, 0); }
    30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
    40%, 60% { transform: translate3d(4px, 0, 0); }
}
</style>
