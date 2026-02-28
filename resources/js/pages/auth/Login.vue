<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import SocialLoginButtons from '@/components/SocialLoginButtons.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const page = usePage();
const redirectParam = computed(() => {
    const url = page.url;
    const queryStart = url.indexOf('?');
    if (queryStart === -1) return null;
    const params = new URLSearchParams(url.slice(queryStart + 1));
    return params.get('redirect');
});

const registerUrl = computed(() =>
    redirectParam.value ? register({ redirect: redirectParam.value }) : register()
);

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Log in to your account"
        description="Enter your email and password below to log in"
    >
        <Head title="Log in" />

        <div class="bg-white/5 backdrop-blur-xl border border-white/10 p-8 rounded-3xl shadow-2xl relative overflow-hidden group">
            <!-- Inner Glow -->
            <div class="absolute -top-24 -right-24 w-48 h-48 bg-brand-primary/10 blur-3xl rounded-full pointer-events-none group-hover:bg-brand-primary/20 transition-all duration-700"></div>

            <SocialLoginButtons :redirect="redirectParam ?? undefined" class="mb-8" />

            <div class="relative flex items-center mb-8">
                <div class="flex-grow border-t border-white/5"></div>
                <span class="flex-shrink mx-4 text-xs font-bold uppercase tracking-widest text-zinc-600">OR</span>
                <div class="flex-grow border-t border-white/5"></div>
            </div>

            <Form
                v-bind="store.form(redirectParam ? { mergeQuery: { redirect: redirectParam } } : { mergeQuery: {} })"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <input v-if="redirectParam" type="hidden" name="redirect" :value="redirectParam" />
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email" class="text-zinc-400 font-medium ml-1">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="name@company.com"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between ml-1">
                            <Label for="password" class="text-zinc-400 font-medium">Password</Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-xs text-brand-primary hover:text-brand-primary/80 transition-colors"
                                :tabindex="5"
                            >
                                Forgot password?
                            </TextLink>
                        </div>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="••••••••"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center justify-between ml-1 text-zinc-500">
                        <Label for="remember" class="flex items-center space-x-3 cursor-pointer group/label">
                            <Checkbox id="remember" name="remember" :tabindex="3" class="border-white/10" />
                            <span class="text-sm group-hover/label:text-zinc-300 transition-colors">Keep me signed in</span>
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full h-12 bg-brand-primary hover:bg-brand-primary/90 text-white font-bold rounded-xl shadow-[0_0_20px_rgba(178,0,3,0.3)] transition-all hover:scale-[1.02]"
                        :tabindex="4"
                        :disabled="processing"
                        data-test="login-button"
                    >
                        <Spinner v-if="processing" />
                        Log in
                    </Button>
                </div>

                <div
                    class="text-center text-sm text-zinc-500 mt-2"
                    v-if="canRegister"
                >
                    Don't have an account?
                    <TextLink :href="registerUrl.url" :tabindex="5" class="text-brand-primary font-bold hover:underline">Sign up</TextLink>
                </div>
            </Form>
        </div>
    </AuthBase>
</template>
