<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { 
    Paintbrush, 
    ShieldAlert, 
    Save, 
    Globe, 
    Copyright, 
    Layout, 
    Image as ImageIcon,
    Loader2
} from 'lucide-vue-next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import FileUpload from '@/components/FileUpload.vue';

const props = defineProps<{
    initialSettings: Record<string, string>;
}>();

const form = useForm({
    settings: {
        site_name: props.initialSettings.site_name || '',
        site_logo: props.initialSettings.site_logo || '',
        site_favicon: props.initialSettings.site_favicon || '',
        copyright_text: props.initialSettings.copyright_text || '',
        maintenance_mode: props.initialSettings.maintenance_mode === '1' ? '1' : '0',
    }
});

const submit = () => {
    form.post('/admin/settings', {
        preserveScroll: true,
        onSuccess: () => {
            // Success handled by flash
        }
    });
};

const activeTab = ref('branding');

const toggleMaintenance = () => {
    form.settings.maintenance_mode = form.settings.maintenance_mode === '1' ? '0' : '1';
};
</script>

<template>
    <Head title="Site Settings" />

    <AdminLayout>
        <div class="py-12 px-6">
            <div class="max-w-4xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2">
                            Site Settings
                        </h1>
                        <p class="text-muted-foreground">Manage your platform's global configuration and branding.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                    <!-- Navigation -->
                    <div class="md:col-span-1 space-y-2">
                        <button 
                            type="button"
                            @click="activeTab = 'branding'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 font-bold uppercase tracking-widest text-[10px]"
                            :class="activeTab === 'branding' ? 'bg-brand-primary text-white shadow-lg shadow-brand-primary/20' : 'text-muted-foreground hover:text-white hover:bg-white/5'"
                        >
                            <Globe class="h-4 w-4" />
                            Branding
                        </button>
                        <button 
                            type="button"
                            @click="activeTab = 'system'"
                            class="w-full flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 font-bold uppercase tracking-widest text-[10px]"
                            :class="activeTab === 'system' ? 'bg-brand-primary text-white shadow-lg shadow-brand-primary/20' : 'text-muted-foreground hover:text-white hover:bg-white/5'"
                        >
                            <ShieldAlert class="h-4 w-4" />
                            System
                        </button>
                    </div>

                    <!-- Content -->
                    <div class="md:col-span-3">
                        <form @submit.prevent="submit" class="space-y-8">
                            <!-- Branding Section -->
                            <Card v-if="activeTab === 'branding'" class="bg-white/5 border-white/10 backdrop-blur-xl border-t-brand-primary/20">
                                <CardHeader>
                                    <div class="flex items-center gap-2 mb-2">
                                        <Paintbrush class="h-5 w-5 text-brand-primary" />
                                        <CardTitle class="text-xl font-black text-white uppercase tracking-tight">Identity & Design</CardTitle>
                                    </div>
                                    <CardDescription>Customize how your site appears to visitors.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <div class="space-y-2">
                                        <Label class="text-[10px] uppercase font-black tracking-widest text-muted-foreground">Site Name</Label>
                                        <Input v-model="form.settings.site_name" placeholder="e.g., RLBmods" class="bg-white/5 border-white/10 h-12" />
                                    </div>

                                    <div class="space-y-2">
                                        <Label class="text-[10px] uppercase font-black tracking-widest text-muted-foreground">Copyright Text</Label>
                                        <div class="relative">
                                            <Copyright class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                            <Input v-model="form.settings.copyright_text" placeholder="e.g., © 2024 RLBmods" class="pl-10 bg-white/5 border-white/10 h-12" />
                                        </div>
                                    </div>

                                    <Separator class="bg-white/5" />

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                                        <FileUpload 
                                            v-model="form.settings.site_logo"
                                            label="Site Logo"
                                            description="Dark mode compatible logo (PNG/SVG)"
                                            :maxSize="2"
                                        />
                                        <FileUpload 
                                            v-model="form.settings.site_favicon"
                                            label="Favicon"
                                            description="Site icon (ICO/PNG)"
                                            :maxSize="1"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- System Section -->
                            <Card v-if="activeTab === 'system'" class="bg-white/5 border-white/10 backdrop-blur-xl border-t-brand-primary/20">
                                <CardHeader>
                                    <div class="flex items-center gap-2 mb-2">
                                        <ShieldAlert class="h-5 w-5 text-brand-primary" />
                                        <CardTitle class="text-xl font-black text-white uppercase tracking-tight">System Controls</CardTitle>
                                    </div>
                                    <CardDescription>Manage global site behavior and accessibility.</CardDescription>
                                </CardHeader>
                                <CardContent class="space-y-6">
                                    <div class="flex items-center justify-between p-4 rounded-2xl bg-white/2 border border-white/5">
                                        <div class="space-y-0.5">
                                            <Label class="text-sm font-bold text-white uppercase italic">Maintenance Mode</Label>
                                            <p class="text-xs text-muted-foreground">When enabled, visitors will see a maintenance page.</p>
                                        </div>
                                        
                                        <!-- Custom Switch -->
                                        <button 
                                            type="button"
                                            @click="toggleMaintenance"
                                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-brand-primary focus:ring-offset-2 focus:ring-offset-black"
                                            :class="form.settings.maintenance_mode === '1' ? 'bg-brand-primary' : 'bg-white/10'"
                                        >
                                            <span 
                                                class="inline-block h-4 w-4 transform rounded-full bg-white transition-transform duration-200"
                                                :class="form.settings.maintenance_mode === '1' ? 'translate-x-6' : 'translate-x-1'"
                                            />
                                        </button>
                                    </div>
                                </CardContent>
                            </Card>

                            <!-- Save Button -->
                            <div class="flex justify-end pt-4">
                                <Button 
                                    type="submit" 
                                    :disabled="form.processing"
                                    class="h-14 px-10 bg-brand-primary hover:bg-brand-primary/90 text-white font-black uppercase italic tracking-widest group rounded-2xl transition-all duration-500 shadow-xl shadow-brand-primary/20"
                                >
                                    <template v-if="form.processing">
                                        <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                                        Saving...
                                    </template>
                                    <template v-else>
                                        <Save class="mr-2 h-5 w-5 transition-transform group-hover:scale-110" />
                                        Save Configuration
                                    </template>
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AdminLayout>
</template>
