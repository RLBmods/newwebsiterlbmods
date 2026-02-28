<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import SocialLoginButtons from '@/components/SocialLoginButtons.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

const page = usePage();
const redirectParam = computed(() => {
    const url = page.url;
    const queryStart = url.indexOf('?');
    if (queryStart === -1) return null;
    const params = new URLSearchParams(url.slice(queryStart + 1));
    return params.get('redirect');
});

const loginUrl = computed(() =>
    redirectParam.value ? login({ redirect: redirectParam.value }) : login()
);
</script>

<template>
    <AuthBase
        title="Create an account"
        description="Enter your details below to create your account"
    >
        <Head title="Register" />

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
                :reset-on-success="['password', 'password_confirmation']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <input v-if="redirectParam" type="hidden" name="redirect" :value="redirectParam" />
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="name" class="text-zinc-400 font-medium ml-1">Name</Label>
                        <Input
                            id="name"
                            type="text"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="name"
                            name="name"
                            placeholder="Display name"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email" class="text-zinc-400 font-medium ml-1">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            :tabindex="2"
                            autocomplete="email"
                            name="email"
                            placeholder="name@company.com"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password" class="text-zinc-400 font-medium ml-1">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            required
                            :tabindex="3"
                            autocomplete="new-password"
                            name="password"
                            placeholder="••••••••"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="password_confirmation" class="text-zinc-400 font-medium ml-1">Confirm password</Label>
                        <Input
                            id="password_confirmation"
                            type="password"
                            required
                            :tabindex="4"
                            autocomplete="new-password"
                            name="password_confirmation"
                            placeholder="••••••••"
                            class="bg-zinc-900/50 border-white/5 focus:border-brand-primary/50 text-white rounded-xl h-11"
                        />
                        <InputError :message="errors.password_confirmation" />
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full h-12 bg-brand-primary hover:bg-brand-primary/90 text-white font-bold rounded-xl shadow-[0_0_20px_rgba(178,0,3,0.3)] transition-all hover:scale-[1.02]"
                        tabindex="5"
                        :disabled="processing"
                        data-test="register-user-button"
                    >
                        <Spinner v-if="processing" />
                        Create account
                    </Button>
                </div>

                <div class="text-center text-sm text-zinc-500 mt-2">
                    Already have an account?
                    <TextLink
                        :href="loginUrl.url"
                        class="text-brand-primary font-bold hover:underline"
                        :tabindex="6"
                        >Log in</TextLink
                    >
                </div>
            </Form>
        </div>
    </AuthBase>
</template>
