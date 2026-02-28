<script setup lang="ts">
import { Form, Head, Link, usePage, useForm } from '@inertiajs/vue3';
import DeleteUser from '@/components/DeleteUser.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem } from '@/types';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { edit, update } from '@/routes/profile';
import { send } from '@/routes/verification';
import social from '@/routes/social';
import { Mail, User as UserIcon, LogIn, Github, Chrome, Camera, Trash2 } from 'lucide-vue-next';
import { useToast } from 'vue-toastification';
import { ref } from 'vue';
import { useForm as useInertiaForm } from '@inertiajs/vue3';

type Props = {
    mustVerifyEmail: boolean;
    status?: string;
};

defineProps<Props>();

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Profile Settings',
        href: edit().url,
    },
];

const page = usePage();
const user = page.props.auth.user;
const toast = useToast();

const form = useInertiaForm({
    _method: 'PATCH',
    name: user.name,
    email: user.email,
    avatar: null as File | null,
});

const avatarPreview = ref(user.avatar);
const avatarInput = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);

const handleAvatarChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        setAvatar(target.files[0]);
    }
};

const handleDrop = (event: DragEvent) => {
    isDragging.value = false;
    if (event.dataTransfer?.files && event.dataTransfer.files[0]) {
        setAvatar(event.dataTransfer.files[0]);
    }
};

const setAvatar = (file: File) => {
    if (!file.type.startsWith('image/')) {
        toast.error('Please upload an image file.');
        return;
    }
    form.avatar = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const submitProfile = () => {
    form.post(update().url, {
        preserveScroll: true,
        onSuccess: () => toast.success('Profile updated successfully!'),
    });
};

const unlinkForm = useForm({});

const handleUnlink = (provider: string) => {
    if (confirm(`Are you sure you want to unlink your ${provider} account?`)) {
        unlinkForm.post(social.unlink.url(provider), {
            onSuccess: () => toast.success(`${provider} account unlinked successfully!`),
        });
    }
};

const handleLink = (provider: string) => {
    window.location.href = social.link.url(provider);
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Profile Settings" />

        <SettingsLayout>
            <div class="space-y-8 max-w-5xl">
                <!-- Profile Information -->
                <Card class="overflow-hidden border-white/5 bg-white/[0.02]">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <UserIcon class="size-5 text-brand-primary" />
                            Profile Information
                        </CardTitle>
                        <CardDescription>Update your account's profile information and email address.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form @submit.prevent="submitProfile" class="space-y-8">
                            <!-- Avatar Section -->
                            <div class="flex flex-col sm:flex-row items-center gap-8 pb-6 border-b border-white/5">
                                <div 
                                    class="relative group"
                                    @dragover.prevent="isDragging = true"
                                    @dragleave.prevent="isDragging = false"
                                    @drop.prevent="handleDrop"
                                >
                                    <div 
                                        class="size-32 rounded-[2.5rem] overflow-hidden bg-white/5 border-2 shadow-2xl transition-all duration-500 group-hover:scale-105"
                                        :class="isDragging ? 'border-brand-primary bg-brand-primary/10' : 'border-white/10 group-hover:border-brand-primary/50'"
                                    >
                                        <img 
                                            v-if="avatarPreview" 
                                            :src="avatarPreview" 
                                            class="size-full object-cover"
                                            alt="Profile" 
                                        />
                                        <div v-else class="size-full flex items-center justify-center bg-brand-primary/10">
                                            <UserIcon class="size-12 text-brand-primary/50" />
                                        </div>
                                    </div>
                                    
                                    <Label 
                                        for="avatar" 
                                        class="absolute -bottom-2 -right-2 size-10 rounded-2xl bg-brand-primary text-white flex items-center justify-center cursor-pointer shadow-xl hover:scale-110 active:scale-95 transition-all"
                                    >
                                        <Camera class="size-5" />
                                        <input 
                                            id="avatar" 
                                            type="file" 
                                            class="hidden" 
                                            accept="image/*"
                                            @change="handleAvatarChange"
                                        />
                                    </Label>
                                </div>

                                <div class="flex-1 text-center sm:text-left space-y-1">
                                    <h4 class="text-lg font-black text-white">Profile Picture</h4>
                                    <p class="text-sm font-bold text-muted-foreground leading-relaxed">
                                        Upload a custom avatar to personalize your account. <br class="hidden sm:block" />
                                        Supported formats: JPEG, PNG, GIF.
                                    </p>
                                    <InputError :message="form.errors.avatar" />
                                </div>
                            </div>

                            <div class="grid gap-6 sm:grid-cols-2">
                                <div class="space-y-2">
                                    <Label for="name" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Name</Label>
                                    <div class="relative">
                                        <UserIcon class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground/50" />
                                        <Input
                                            id="name"
                                            class="pl-10 h-11 bg-white/5 border-white/10 rounded-xl"
                                            v-model="form.name"
                                            required
                                            autocomplete="name"
                                            placeholder="John Doe"
                                        />
                                    </div>
                                    <InputError :message="form.errors.name" />
                                </div>

                                <div class="space-y-2">
                                    <Label for="email" class="text-xs font-bold uppercase tracking-wider text-muted-foreground">Email Address</Label>
                                    <div class="relative">
                                        <Mail class="absolute left-3 top-1/2 -translate-y-1/2 size-4 text-muted-foreground/50" />
                                        <Input
                                            id="email"
                                            type="email"
                                            class="pl-10 h-11 bg-white/5 border-white/10 rounded-xl"
                                            v-model="form.email"
                                            required
                                            autocomplete="username"
                                            placeholder="john@example.com"
                                        />
                                    </div>
                                    <InputError :message="form.errors.email" />
                                </div>
                            </div>

                            <div v-if="mustVerifyEmail && !user.email_verified_at" class="rounded-xl border border-amber-500/20 bg-amber-500/10 p-4">
                                <div class="flex items-center gap-3">
                                    <div class="size-2 rounded-full bg-amber-500 animate-pulse" />
                                    <p class="text-sm font-medium text-amber-200">
                                        Your email address is unverified.
                                        <Link
                                            :href="send()"
                                            as="button"
                                            class="ml-1 text-white underline decoration-amber-500/30 underline-offset-4 hover:decoration-amber-500 transition-all font-bold"
                                        >
                                            Resend Verification OTP
                                        </Link>
                                    </p>
                                </div>

                                <div v-if="status === 'verification-link-sent'" class="mt-3 text-sm font-bold text-emerald-400">
                                    A new verification code has been sent to your email.
                                </div>
                            </div>

                            <div class="flex items-center gap-4 pt-4 border-t border-white/5">
                                <Button
                                    type="submit"
                                    :disabled="form.processing"
                                    class="bg-brand-primary hover:bg-brand-primary/90 text-white font-black h-12 px-8 rounded-2xl shadow-xl shadow-brand-primary/20 transition-all active:scale-95"
                                >
                                    <Spinner v-if="form.processing" class="mr-2" />
                                    Save Profile
                                </Button>

                                <Transition
                                    enter-active-class="transition ease-in-out"
                                    enter-from-class="opacity-0"
                                    leave-active-class="transition ease-in-out"
                                    leave-to-class="opacity-0"
                                >
                                    <p v-show="form.recentlySuccessful" class="text-sm font-bold text-emerald-400">
                                        Profile updated successfully.
                                    </p>
                                </Transition>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <!-- Connected Accounts -->
                <Card class="border-white/5 bg-white/[0.02]">
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <LogIn class="size-5 text-brand-primary" />
                            Connected Accounts
                        </CardTitle>
                        <CardDescription>Connect your social accounts to login faster and link your profile.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <!-- Discord -->
                            <div class="flex items-center justify-between p-4 rounded-2xl border border-white/5 bg-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-10 rounded-xl bg-[#5865F2]/10 text-[#5865F2]">
                                        <svg viewBox="0 0 24 24" class="size-6 fill-current">
                                            <path d="M20.317 4.37a19.791 19.791 0 0 0-4.885-1.515a.074.074 0 0 0-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 0 0-5.487 0a12.64 12.64 0 0 0-.617-1.25a.077.077 0 0 0-.079-.037A19.736 19.736 0 0 0 3.677 4.37a.07.07 0 0 0-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 0 0 .031.057a19.9 19.9 0 0 0 5.993 3.03a.078.078 0 0 0 .084-.028a14.09 14.09 0 0 0 1.226-1.994a.076.076 0 0 0-.041-.106a13.107 13.107 0 0 1-1.872-.892a.077.077 0 0 1-.008-.128a10.2 10.2 0 0 0 .372-.292a.074.074 0 0 1 .077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 0 1 .078.01c.12.098.246.196.373.292a.077.077 0 0 1-.006.127a12.299 12.299 0 0 1-1.873.892a.077.077 0 0 0-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 0 0 .084.028a19.839 19.839 0 0 0 6.002-3.03a.077.077 0 0 0 .032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 0 0-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.956-2.419 2.157-2.419c1.21 0 2.176 1.086 2.157 2.419c0 1.334-.947 2.419-2.157 2.419zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419c0-1.333.955-2.419 2.157-2.419c1.21 0 2.176 1.086 2.157 2.419c0 1.334-.946 2.419-2.157 2.419z"/>
                                        </svg>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-black text-white">Discord</span>
                                        <span class="text-xs font-bold" :class="user.discord_id ? 'text-emerald-400' : 'text-muted-foreground'">
                                            {{ user.discord_id ? 'Connected' : 'Not linked' }}
                                        </span>
                                    </div>
                                </div>
                                <Button 
                                    v-if="user.discord_id" 
                                    variant="outline" 
                                    size="sm" 
                                    class="border-red-500/20 text-red-400 hover:bg-red-500/10"
                                    @click="handleUnlink('discord')"
                                >
                                    Unlink
                                </Button>
                                <Button 
                                    v-else 
                                    variant="secondary" 
                                    size="sm"
                                    class="bg-[#5865F2] hover:bg-[#5865F2]/90 text-white border-none font-bold"
                                    @click="handleLink('discord')"
                                >
                                    Connect
                                </Button>
                            </div>

                            <!-- Google -->
                            <div class="flex items-center justify-between p-4 rounded-2xl border border-white/5 bg-white/5">
                                <div class="flex items-center gap-3">
                                    <div class="flex items-center justify-center size-10 rounded-xl bg-red-500/10 text-red-500">
                                        <Chrome class="size-6" />
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-sm font-black text-white">Google</span>
                                        <span class="text-xs font-bold" :class="user.google_id ? 'text-emerald-400' : 'text-muted-foreground'">
                                            {{ user.google_id ? 'Connected' : 'Not linked' }}
                                        </span>
                                    </div>
                                </div>
                                <Button 
                                    v-if="user.google_id" 
                                    variant="outline" 
                                    size="sm" 
                                    class="border-red-500/20 text-red-400 hover:bg-red-500/10"
                                    @click="handleUnlink('google')"
                                >
                                    Unlink
                                </Button>
                                <Button 
                                    v-else 
                                    variant="secondary" 
                                    size="sm"
                                    class="bg-white text-black hover:bg-white/90 border-none font-bold"
                                    @click="handleLink('google')"
                                >
                                    Connect
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Danger Zone -->
                <DeleteUser />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
