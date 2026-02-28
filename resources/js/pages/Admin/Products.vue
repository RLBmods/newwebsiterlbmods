<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    Package, 
    Search, 
    Plus, 
    Filter, 
    Tag, 
    Edit2, 
    Trash2, 
    X, 
    Image as ImageIcon,
    Clock,
    Shield,
    Monitor,
    Gamepad2,
    Check,
    Cpu,
    Download,
    Upload,
    HardDrive,
    ChevronDown,
    Loader2
} from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';
import admin from '@/routes/admin';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { 
    Dialog, 
    DialogContent, 
    DialogHeader, 
    DialogTitle, 
    DialogFooter,
    DialogDescription 
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import FileUpload from '@/components/FileUpload.vue';

interface Price {
    id?: number;
    duration: number;
    duration_type: string;
    price: number;
}

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    image_url: string;
    type: string;
    category: string;
    game: string;
    status: number;
    public_status: string;
    version: string;
    tutorial_link: string;
    features: { title: string; description: string }[];
    requirements: {
        platforms?: string[];
        anticheats?: string[];
        gamemodes?: string[];
        [key: string]: any;
    };
    menu_images: string[];
    prices: Price[];
    stock: number;
    is_active: boolean;
    spoofer_included: boolean;
    download_url: string | null;
    file_name: string | null;
}

interface Props {
    products: {
        data: Product[];
        links: any[];
    };
}

const props = defineProps<Props>();

const isEditing = ref(false);
const selectedProduct = ref<Product | null>(null);

const anticheatOptions = ['Vanguard', 'Easy Anticheat', 'Battle eye', 'ricocheat'];
const typeOptions = ['stock', 'valorant', 'pytguard'];

const form = useForm({
    name: '',
    description: '',
    price: 0,
    image_url: '',
    type: 'stock',
    category: '',
    game: '',
    public_status: 'Undetected',
    version: '1.0.0',
    tutorial_link: '',
    features: [] as { title: string; description: string }[],
    requirements: {
        platforms: [] as string[],
        anticheats: [] as string[],
        gamemodes: [] as string[],
    },
    menu_images: [] as string[],
    prices: [] as Price[],
    spoofer_included: false,
    download_url: '' as string | null,
});

// --- Download File Management State ---
const downloadMode = ref<'upload' | 'existing'>('upload');
const existingFiles = ref<{ name: string; url: string; size: number }[]>([]);
const isLoadingFiles = ref(false);
const isUploadingFile = ref(false);
const uploadError = ref<string | null>(null);
const downloadFileInput = ref<HTMLInputElement | null>(null);
const isDraggingFile = ref(false);

const loadExistingFiles = async () => {
    if (existingFiles.value.length > 0) return;
    isLoadingFiles.value = true;
    try {
        const { data } = await axios.get('/admin/download-files');
        existingFiles.value = data;
    } catch {
        // silently fail
    } finally {
        isLoadingFiles.value = false;
    }
};

const uploadDownloadFile = async (file: File) => {
    const ext = file.name.split('.').pop()?.toLowerCase();
    if (!['exe', 'zip'].includes(ext ?? '')) {
        uploadError.value = 'Only .exe and .zip files are allowed.';
        return;
    }
    uploadError.value = null;
    isUploadingFile.value = true;

    const formData = new FormData();
    formData.append('file', file);
    try {
        const { data } = await axios.post('/admin/download-files/upload', formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
        });
        form.download_url = data.url;
        // Refresh existing files list
        existingFiles.value = [];
        await loadExistingFiles();
    } catch (err: any) {
        uploadError.value = err.response?.data?.message || 'Upload failed.';
    } finally {
        isUploadingFile.value = false;
    }
};

const handleDownloadDrop = (e: DragEvent) => {
    isDraggingFile.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) uploadDownloadFile(file);
};

const handleDownloadFileSelect = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (file) uploadDownloadFile(file);
};

const formatBytes = (bytes: number) => {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
};

const openEditModal = (product: Product) => {
    selectedProduct.value = product;
    form.name = product.name;
    form.description = product.description;
    form.price = product.price;
    form.image_url = product.image_url;
    form.type = product.type || 'stock';
    form.category = product.category;
    form.game = product.game;
    form.public_status = product.public_status;
    form.version = product.version;
    form.tutorial_link = product.tutorial_link;
    form.features = [...(product.features || [])];
    form.requirements = {
        platforms: [...(product.requirements?.platforms || [])],
        anticheats: [...(product.requirements?.anticheats || [])],
        gamemodes: [...(product.requirements?.gamemodes || [])],
    };
    form.menu_images = [...(product.menu_images || [])];
    form.prices = product.prices.map(p => ({ ...p }));
    form.spoofer_included = product.spoofer_included;
    form.download_url = product.download_url || null;
    isEditing.value = true;
    // Pre-load file list for the dropdown
    loadExistingFiles();
};

const closeEditModal = () => {
    isEditing.value = false;
    selectedProduct.value = null;
};

// Helpers for dynamic fields
const addFeature = () => form.features.push({ title: '', description: '' });
const removeFeature = (index: number) => form.features.splice(index, 1);

const addRequirement = (type: 'platforms' | 'gamemodes') => {
    form.requirements[type].push('');
};
const removeRequirement = (type: 'platforms' | 'gamemodes', index: number) => {
    form.requirements[type].splice(index, 1);
};

const toggleAnticheat = (ac: string) => {
    const index = form.requirements.anticheats.indexOf(ac);
    if (index > -1) {
        form.requirements.anticheats.splice(index, 1);
    } else {
        form.requirements.anticheats.push(ac);
    }
};

const addMenuImage = () => form.menu_images.push('');
const removeMenuImage = (index: number) => form.menu_images.splice(index, 1);

const addPrice = () => {
    form.prices.push({
        duration: 1,
        duration_type: 'days',
        price: 0,
    });
};
const removePrice = (index: number) => form.prices.splice(index, 1);

const submitUpdate = () => {
    if (!selectedProduct.value) return;
    
    form.put(admin.products.update({ product: selectedProduct.value.id }).url, {
        onSuccess: () => {
            closeEditModal();
        },
    });
};

const deleteProduct = (id: number) => {
    if (confirm('Are you sure you want to delete this product?')) {
        router.delete(admin.products.destroy({ product: id }).url);
    }
};

const getStatusColorClass = (status: string | null | undefined) => {
    if (!status) return 'bg-white/10 text-muted-foreground border-white/20';
    
    switch (status.toLowerCase()) {
        case 'undetected': return 'bg-emerald-500/10 text-emerald-500 border-emerald-500/20';
        case 'use at own risk': return 'bg-yellow-500/10 text-yellow-500 border-yellow-500/20';
        case 'detected': return 'bg-red-500/10 text-red-500 border-red-500/20';
        case 'updating': return 'bg-blue-500/10 text-blue-500 border-blue-500/20';
        default: return 'bg-white/10 text-muted-foreground border-white/20';
    }
};
</script>

<template>
    <Head title="Manage Products" />

    <AdminLayout>
        <div class="py-8 px-6">
            <div class="max-w-7xl mx-auto space-y-8">
                <!-- Header -->
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h1 class="text-4xl font-black text-white tracking-tighter uppercase italic mb-2">
                            Manage Products
                        </h1>
                        <p class="text-muted-foreground">Add and manage your store products</p>
                    </div>
                    <button class="inline-flex items-center gap-2 bg-brand-primary hover:bg-brand-primary/90 text-white px-6 py-3 rounded-xl font-black uppercase italic tracking-tighter transition-all">
                        <Plus class="h-5 w-5" />
                        Create Product
                    </button>
                </div>

                <!-- Filters -->
                <Card class="bg-white/5 border-white/10 backdrop-blur-xl">
                    <CardContent class="p-4">
                        <div class="flex items-center gap-4">
                            <div class="relative flex-1">
                                <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input 
                                    placeholder="Search products by name..." 
                                    class="pl-10 bg-white/5 border-white/10 h-11"
                                />
                            </div>
                            <Button variant="outline" class="h-11 border-white/10 bg-white/5 uppercase tracking-widest font-bold text-xs">
                                <Filter class="mr-2 h-4 w-4" />
                                Filter
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <Card v-for="product in products.data" :key="product.id" class="bg-white/5 border-white/10 backdrop-blur-xl overflow-hidden hover:border-brand-primary/50 transition-colors group">
                        <div class="aspect-video bg-white/5 flex items-center justify-center relative">
                            <img v-if="product.image_url" :src="product.image_url" alt="" class="absolute inset-0 w-full h-full object-cover">
                            <Package v-else class="h-12 w-12 text-white/20 group-hover:scale-110 transition-transform" />
                            <div class="absolute top-4 right-4 px-2.5 py-1 rounded-lg bg-black/50 backdrop-blur-md border border-white/10 flex items-center gap-1.5">
                                <Tag class="h-3 w-3 text-brand-primary" />
                                <span class="text-sm font-black text-white italic">${{ product.price.toFixed(2) }}</span>
                            </div>
                            <div class="absolute bottom-4 left-4">
                                <Badge variant="outline" :class="getStatusColorClass(product.public_status)">
                                    {{ product.public_status || 'Unknown' }}
                                </Badge>
                            </div>
                        </div>
                        <CardContent class="p-6">
                            <div class="flex items-start justify-between gap-4 mb-2">
                                <h3 class="text-xl font-black text-white tracking-tight uppercase italic">{{ product.name }}</h3>
                                <div class="size-2 rounded-full" :class="product.status ? 'bg-emerald-500 shadow-[0_0_10px_rgba(16,185,129,0.5)]' : 'bg-red-500'"></div>
                            </div>
                            <p class="text-sm text-muted-foreground line-clamp-2 mb-4 h-10">{{ product.description }}</p>
                            
                            <div class="flex items-center justify-between py-4 border-y border-white/5 mb-4">
                                <div class="text-center">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-1">Type</p>
                                    <p class="text-sm font-black text-white italic uppercase">{{ product.type }}</p>
                                </div>
                                <div class="text-center px-4 border-x border-white/5">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-1">Game</p>
                                    <p class="text-sm font-black text-white italic uppercase">{{ product.game || 'N/A' }}</p>
                                </div>
                                <div class="text-center">
                                    <p class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground mb-1">Durations</p>
                                    <p class="text-sm font-black text-blue-400 italic">{{ product.prices.length }} SET</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2">
                                <Button 
                                    @click="openEditModal(product)"
                                    class="flex-1 bg-white/5 border-white/10 hover:bg-brand-primary hover:border-brand-primary uppercase font-bold text-xs tracking-widest"
                                    variant="outline"
                                >
                                    <Edit2 class="mr-2 h-3.5 w-3.5" />
                                    Edit
                                </Button>
                                <Button 
                                    @click="deleteProduct(product.id)"
                                    class="bg-white/5 border-white/10 hover:bg-red-500 hover:border-red-500 text-muted-foreground hover:text-white"
                                    variant="outline"
                                    size="icon"
                                >
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>

        <!-- Edit Modal -->
        <Dialog :open="isEditing" @update:open="closeEditModal">
            <DialogContent class="max-w-4xl max-h-[90vh] overflow-y-auto bg-neutral-950 border-white/10 backdrop-blur-2xl p-0">
                <DialogHeader class="p-6 pb-0">
                    <DialogTitle class="text-2xl font-black text-white uppercase italic tracking-tighter">
                        Edit Product: {{ selectedProduct?.name }}
                    </DialogTitle>
                    <DialogDescription class="text-muted-foreground">
                        Update all product details, images, and prices.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-8 p-6">
                    <!-- General Info Section -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-white/5">
                            <Package class="h-4 w-4 text-brand-primary" />
                            <h3 class="text-sm font-black text-white uppercase tracking-widest italic">General Information</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Product Name</Label>
                                <Input v-model="form.name" placeholder="Apex Legends Cheat" class="bg-white/5 border-white/10 h-10" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Base Price ($)</Label>
                                <Input v-model="form.price" type="number" step="0.01" class="bg-white/5 border-white/10 h-10" />
                            </div>
                        </div>
                        <div class="space-y-2">
                            <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Description</Label>
                            <Textarea v-model="form.description" rows="4" class="bg-white/5 border-white/10" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Type</Label>
                                <select 
                                    v-model="form.type" 
                                    class="w-full h-10 rounded-md border border-white/10 bg-white/5 px-3 py-1 text-sm text-white focus:outline-none focus:ring-1 focus:ring-brand-primary custom-select"
                                >
                                    <option v-for="t in typeOptions" :key="t" :value="t" class="bg-neutral-900">{{ t }}</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Category</Label>
                                <Input v-model="form.category" placeholder="Cheats" class="bg-white/5 border-white/10 h-10" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Game</Label>
                                <Input v-model="form.game" placeholder="Apex Legends" class="bg-white/5 border-white/10 h-10" />
                            </div>
                        </div>
                    </div>

                    <!-- Metadata & Status -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-white/5">
                            <Shield class="h-4 w-4 text-emerald-400" />
                            <h3 class="text-sm font-black text-white uppercase tracking-widest italic">Status & Versioning</h3>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Public Status</Label>
                                <select 
                                    v-model="form.public_status" 
                                    class="w-full h-10 rounded-md border border-white/10 bg-white/5 px-3 py-1 text-sm text-white focus:outline-none focus:ring-1 focus:ring-brand-primary custom-select shadow-xl"
                                >
                                    <option value="Undetected" class="bg-neutral-950 text-emerald-400 py-2">Undetected</option>
                                    <option value="Use at own risk" class="bg-neutral-950 text-yellow-400 py-2">Use at own risk</option>
                                    <option value="Detected" class="bg-neutral-950 text-red-400 py-2">Detected</option>
                                    <option value="Updating" class="bg-neutral-950 text-blue-400 py-2">Updating</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Version</Label>
                                <Input v-model="form.version" placeholder="1.0.0" class="bg-white/5 border-white/10 h-10" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Tutorial Link</Label>
                                <Input v-model="form.tutorial_link" placeholder="https://..." class="bg-white/5 border-white/10 h-10" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Spoofer Included</Label>
                                <select 
                                    v-model="form.spoofer_included" 
                                    class="w-full h-10 rounded-md border border-white/10 bg-white/5 px-3 py-1 text-sm text-white focus:outline-none focus:ring-1 focus:ring-brand-primary custom-select"
                                >
                                    <option :value="true" class="bg-neutral-900">Yes</option>
                                    <option :value="false" class="bg-neutral-900">No</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Images Section -->
                    <div class="space-y-6">
                        <div class="flex items-center justify-between pb-2 border-b border-white/5">
                            <div class="flex items-center gap-2">
                                <ImageIcon class="h-4 w-4 text-blue-400" />
                                <h3 class="text-sm font-black text-white uppercase tracking-widest italic">Product Media</h3>
                            </div>
                        </div>

                        <FileUpload 
                            v-model="form.image_url"
                            label="Main Product Image"
                            description="This is the primary image shown in the shop (PNG/JPG)"
                            :maxSize="2"
                        />

                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <Label class="text-xs font-bold uppercase tracking-widest text-muted-foreground">Menu / Gallery Images</Label>
                                <Button @click="addMenuImage" size="sm" variant="ghost" class="h-8 text-[10px] font-black uppercase text-brand-primary px-2">
                                    <Plus class="mr-1 h-3 w-3" /> Add Gallery Slot
                                </Button>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div v-for="(img, index) in form.menu_images" :key="index" class="relative">
                                    <FileUpload 
                                        v-model="form.menu_images[index]"
                                        :maxSize="2"
                                    />
                                    <Button 
                                        @click="removeMenuImage(index)" 
                                        size="icon" 
                                        variant="ghost" 
                                        class="absolute -top-2 -right-2 size-6 rounded-full bg-red-500 text-white z-20 hover:bg-red-600"
                                    >
                                        <X class="h-3 w-3" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Platforms & Metadata Fields -->
                    <div class="space-y-6">
                        <div class="flex items-center gap-2 pb-2 border-b border-white/5">
                            <Monitor class="h-4 w-4 text-yellow-400" />
                            <h3 class="text-sm font-black text-white uppercase tracking-widest italic">Specs & Features</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Anti-Cheats (Refined Choice) -->
                            <div class="space-y-3">
                                <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground block mb-2">Anti-Cheats (Select Multiple)</Label>
                                <div class="grid grid-cols-2 gap-2">
                                    <button 
                                        v-for="ac in anticheatOptions" 
                                        :key="ac"
                                        @click="toggleAnticheat(ac)"
                                        type="button"
                                        class="flex items-center justify-between px-3 py-2 rounded-lg border text-xs font-bold transition-all uppercase tracking-tight"
                                        :class="form.requirements.anticheats.includes(ac) 
                                            ? 'bg-brand-primary/20 border-brand-primary text-brand-primary' 
                                            : 'bg-white/2 border-white/10 text-muted-foreground hover:border-white/20'"
                                    >
                                        {{ ac }}
                                        <Check v-if="form.requirements.anticheats.includes(ac)" class="h-3 w-3" />
                                    </button>
                                </div>
                            </div>

                            <!-- Platforms -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Platforms</Label>
                                    <Button @click="addRequirement('platforms')" size="sm" variant="ghost" class="h-6 text-[10px] uppercase text-brand-primary p-0">
                                        <Plus class="mr-1 h-3 w-3" /> Add
                                    </Button>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="(p, i) in form.requirements.platforms" :key="i" class="flex gap-2">
                                        <Input v-model="form.requirements.platforms[i]" placeholder="Windows 10/11" class="h-9 bg-white/5 border-white/10 text-xs" />
                                        <Button @click="removeRequirement('platforms', i)" size="icon" variant="ghost" class="size-9 text-red-500/50">
                                            <X class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Game Modes -->
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Game Modes</Label>
                                    <Button @click="addRequirement('gamemodes')" size="sm" variant="ghost" class="h-6 text-[10px] uppercase text-brand-primary p-0">
                                        <Plus class="mr-1 h-3 w-3" /> Add
                                    </Button>
                                </div>
                                <div class="space-y-2">
                                    <div v-for="(g, i) in form.requirements.gamemodes" :key="i" class="flex gap-2">
                                        <Input v-model="form.requirements.gamemodes[i]" placeholder="Windowed / Borderless" class="h-9 bg-white/5 border-white/10 text-xs" />
                                        <Button @click="removeRequirement('gamemodes', i)" size="icon" variant="ghost" class="size-9 text-red-500/50">
                                            <X class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Features -->
                            <div class="space-y-4">
                                <div class="flex items-center justify-between">
                                    <Label class="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground">Main Features</Label>
                                    <Button @click="addFeature" type="button" size="sm" variant="ghost" class="h-6 text-[10px] uppercase text-brand-primary p-0">
                                        <Plus class="mr-1 h-3 w-3" /> Add Feature Group
                                    </Button>
                                </div>
                                <div class="space-y-4">
                                    <div v-for="(f, i) in form.features" :key="i" class="p-3 rounded-lg bg-white/2 border border-white/5 space-y-3 relative group/feat">
                                        <div class="space-y-1.5">
                                            <Label class="text-[10px] uppercase text-muted-foreground font-bold">Group Title (e.g. Aim)</Label>
                                            <Input v-model="form.features[i].title" placeholder="Aimbot" class="h-8 bg-white/5 border-white/10 text-xs" />
                                        </div>
                                        <div class="space-y-1.5">
                                            <Label class="text-[10px] uppercase text-muted-foreground font-bold">Features (one per line)</Label>
                                            <Textarea v-model="form.features[i].description" placeholder="Enable&#10;Silent Aim&#10;Anti Aim" class="min-h-[80px] bg-white/5 border-white/10 text-xs py-2" />
                                        </div>
                                        <Button 
                                            @click="removeFeature(i)" 
                                            type="button"
                                            size="icon" 
                                            variant="ghost" 
                                            class="absolute -top-2 -right-2 size-6 rounded-full bg-red-500 text-white opacity-0 group-hover/feat:opacity-100 transition-opacity"
                                        >
                                            <X class="h-3 w-3" />
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing & Durations Section -->
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-white/5">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-brand-primary" />
                                <h3 class="text-sm font-black text-white uppercase tracking-widest italic">Durations & Pricing</h3>
                            </div>
                            <Button @click="addPrice" size="sm" variant="ghost" class="h-8 text-[10px] font-black uppercase text-brand-primary">
                                <Plus class="mr-1 h-3 w-3" /> Add Duration
                            </Button>
                        </div>
                        
                        <div v-if="form.prices.length === 0" class="text-center py-8 rounded-xl border border-dashed border-white/10 text-muted-foreground text-sm">
                            No custom durations added.
                        </div>

                        <div v-for="(price, index) in form.prices" :key="index" class="p-4 rounded-xl bg-white/2 border border-white/5 grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                            <div class="space-y-2">
                                <Label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Duration</Label>
                                <Input v-model="price.duration" type="number" class="bg-neutral-900 border-white/5 h-9" />
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Type</Label>
                                <select 
                                    v-model="price.duration_type" 
                                    class="w-full h-9 rounded-md border border-white/5 bg-neutral-900 px-3 py-1 text-sm text-white focus:outline-none custom-select"
                                >
                                    <option value="days">Days</option>
                                    <option value="weeks">Weeks</option>
                                    <option value="months">Months</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <Label class="text-[10px] font-bold uppercase tracking-widest text-muted-foreground">Price ($)</Label>
                                <Input v-model="price.price" type="number" step="0.01" class="bg-neutral-900 border-white/5 h-9 text-brand-primary font-black italic" />
                            </div>
                            <div class="flex justify-end">
                                <Button @click="removePrice(index)" size="icon" variant="destructive" class="size-9 bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white border-none">
                                    <Trash2 class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>

                    <!-- Download File Section -->
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 pb-2 border-b border-white/5">
                            <Download class="h-4 w-4 text-brand-primary" />
                            <h3 class="text-sm font-black text-white uppercase tracking-widest italic">Download File</h3>
                            <span class="ml-auto text-[10px] text-muted-foreground font-bold uppercase">exe / zip only</span>
                        </div>

                        <!-- Current file indicator -->
                        <div v-if="form.download_url" class="flex items-center gap-3 p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                            <HardDrive class="size-4 text-emerald-400 shrink-0" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-bold text-emerald-400 uppercase tracking-widest mb-0.5">Assigned File</p>
                                <p class="text-[11px] text-white font-mono truncate">{{ form.download_url.split('/').pop() }}</p>
                            </div>
                            <button @click="form.download_url = null" type="button" class="text-muted-foreground hover:text-red-400 transition-colors shrink-0">
                                <X class="size-4" />
                            </button>
                        </div>

                        <!-- Mode toggle -->
                        <div class="flex gap-2">
                            <button
                                type="button"
                                @click="downloadMode = 'upload'"
                                class="flex-1 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest transition-all border"
                                :class="downloadMode === 'upload' ? 'bg-brand-primary border-brand-primary text-white' : 'bg-white/5 border-white/10 text-muted-foreground hover:border-white/20'"
                            >
                                <Upload class="inline size-3.5 mr-1" /> Upload New
                            </button>
                            <button
                                type="button"
                                @click="downloadMode = 'existing'; loadExistingFiles()"
                                class="flex-1 py-2 rounded-lg text-[11px] font-black uppercase tracking-widest transition-all border"
                                :class="downloadMode === 'existing' ? 'bg-brand-primary border-brand-primary text-white' : 'bg-white/5 border-white/10 text-muted-foreground hover:border-white/20'"
                            >
                                <ChevronDown class="inline size-3.5 mr-1" /> Select Existing
                            </button>
                        </div>

                        <!-- Upload Mode -->
                        <div v-if="downloadMode === 'upload'">
                            <input
                                ref="downloadFileInput"
                                type="file"
                                accept=".exe,.zip"
                                class="hidden"
                                @change="handleDownloadFileSelect"
                            />
                            <!-- Drop Zone -->
                            <div
                                v-if="!isUploadingFile"
                                @dragover.prevent="isDraggingFile = true"
                                @dragleave.prevent="isDraggingFile = false"
                                @drop.prevent="handleDownloadDrop"
                                @click="downloadFileInput?.click()"
                                class="h-32 rounded-2xl border-2 border-dashed flex flex-col items-center justify-center gap-2 cursor-pointer transition-all duration-300"
                                :class="isDraggingFile ? 'bg-brand-primary/10 border-brand-primary' : 'bg-white/2 border-white/10 hover:border-brand-primary/50'"
                            >
                                <div class="size-10 rounded-xl bg-white/5 flex items-center justify-center text-muted-foreground">
                                    <Download class="size-5" />
                                </div>
                                <p class="text-xs font-bold text-white">Drop .exe or .zip here, or click to browse</p>
                                <p class="text-[10px] text-muted-foreground">Max 500 MB</p>
                            </div>
                            <!-- Uploading indicator -->
                            <div v-else class="h-32 rounded-2xl border-2 border-white/5 bg-white/2 flex flex-col items-center justify-center gap-2">
                                <Loader2 class="size-6 text-brand-primary animate-spin" />
                                <p class="text-[11px] font-black uppercase tracking-widest text-muted-foreground animate-pulse">Uploading...</p>
                            </div>
                            <p v-if="uploadError" class="mt-2 text-[11px] text-red-400 font-bold">{{ uploadError }}</p>
                        </div>

                        <!-- Existing Files Mode -->
                        <div v-else>
                            <div v-if="isLoadingFiles" class="h-12 flex items-center justify-center">
                                <Loader2 class="size-5 text-brand-primary animate-spin" />
                            </div>
                            <div v-else-if="existingFiles.length === 0" class="text-center py-6 text-muted-foreground text-sm border border-dashed border-white/10 rounded-xl">
                                No files uploaded yet.
                            </div>
                            <div v-else class="space-y-2 max-h-48 overflow-y-auto pr-1">
                                <button
                                    v-for="f in existingFiles"
                                    :key="f.url"
                                    type="button"
                                    @click="form.download_url = f.url"
                                    class="w-full flex items-center gap-3 p-3 rounded-xl border transition-all text-left"
                                    :class="form.download_url === f.url ? 'bg-brand-primary/10 border-brand-primary text-white' : 'bg-white/2 border-white/5 text-muted-foreground hover:border-white/20'"
                                >
                                    <HardDrive class="size-4 shrink-0" :class="form.download_url === f.url ? 'text-brand-primary' : ''" />
                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-bold truncate">{{ f.name }}</p>
                                        <p class="text-[10px] text-muted-foreground/60">{{ formatBytes(f.size) }}</p>
                                    </div>
                                    <Check v-if="form.download_url === f.url" class="size-4 text-brand-primary shrink-0" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <DialogFooter class="border-t border-white/5 p-6 sticky bottom-0 bg-neutral-950/90 backdrop-blur-xl">
                    <Button @click="closeEditModal" variant="ghost" class="uppercase font-bold text-xs tracking-widest text-muted-foreground">Cancel</Button>
                    <Button @click="submitUpdate" class="bg-brand-primary hover:bg-brand-primary/90 text-white uppercase font-black italic tracking-tight px-8" :disabled="form.processing">
                        <Check v-if="!form.processing" class="mr-2 h-4 w-4" />
                        {{ form.processing ? 'Saving...' : 'Save Product Changes' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AdminLayout>
</template>

<style scoped>
/* Custom styling for inputs to look more premium */
input, textarea, select {
    transition: all 0.2s ease;
}
input:focus, textarea:focus {
    border-color: var(--brand-primary) !important;
    box-shadow: 0 0 0 1px rgba(178, 0, 3, 0.2);
}

.custom-select {
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24' viewBox='0 0 24 24' fill='none' stroke='%23666' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.5rem center;
    background-size: 1.25em;
}

/* Fixing option colors as requested */
select option {
    background-color: #0a0a0a;
    color: #fff;
    padding: 10px;
}

select option[value="Undetected"] { color: #10b981; }
select option[value="Use at own risk"] { color: #fbbf24; }
select option[value="Detected"] { color: #ef4444; }
select option[value="Updating"] { color: #3b82f6; }
</style>
