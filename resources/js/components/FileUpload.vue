<script setup lang="ts">
import { ref } from 'vue';
import { Upload, X, Image as ImageIcon, Loader2, AlertCircle } from 'lucide-vue-next';
import axios from 'axios';

const props = defineProps<{
    modelValue: string | null;
    label?: string;
    description?: string;
    accept?: string;
    maxSize?: number; // In MB
}>();

const emit = defineEmits(['update:modelValue', 'uploading', 'error']);

const isDragging = ref(false);
const isUploading = ref(false);
const error = ref<string | null>(null);
const fileInput = ref<HTMLInputElement | null>(null);

const handleDragOver = () => {
    isDragging.value = true;
};

const handleDragLeave = () => {
    isDragging.value = false;
};

const handleDrop = (e: DragEvent) => {
    isDragging.value = false;
    const files = e.dataTransfer?.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const files = target.files;
    if (files && files.length > 0) {
        uploadFile(files[0]);
    }
};

const uploadFile = async (file: File) => {
    // Basic validation
    if (props.maxSize && file.size > props.maxSize * 1024 * 1024) {
        error.value = `File is too large. Max size is ${props.maxSize}MB.`;
        emit('error', error.value);
        return;
    }

    if (!file.type.startsWith('image/')) {
        error.value = 'Only image files are allowed.';
        emit('error', error.value);
        return;
    }

    error.value = null;
    isUploading.value = true;
    emit('uploading', true);

    const formData = new FormData();
    formData.append('file', file);

    try {
        const response = await axios.post('/api/upload', formData, {
            headers: {
                'Content-Type': 'multipart/form-data'
            }
        });

        emit('update:modelValue', response.data.url);
        if (fileInput.value) fileInput.value.value = '';
    } catch (err: any) {
        error.value = err.response?.data?.message || 'Failed to upload image.';
        emit('error', error.value);
    } finally {
        isUploading.value = false;
        emit('uploading', false);
    }
};

const clearImage = () => {
    emit('update:modelValue', null);
};
</script>

<template>
    <div class="space-y-2">
        <div v-if="label" class="flex items-center justify-between">
            <label class="text-[10px] uppercase font-black tracking-widest text-muted-foreground">{{ label }}</label>
            <button 
                v-if="modelValue" 
                @click="clearImage"
                type="button"
                class="text-[10px] font-bold text-red-400 hover:text-red-300 transition-colors uppercase"
            >
                Clear
            </button>
        </div>

        <div
            @dragover.prevent="handleDragOver"
            @dragleave.prevent="handleDragLeave"
            @drop.prevent="handleDrop"
            class="relative group cursor-pointer"
        >
            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="accept || 'image/*'"
                @change="handleFileSelect"
            />

            <!-- Upload Area -->
            <div
                v-if="!modelValue && !isUploading"
                @click="fileInput?.click()"
                class="h-32 rounded-2xl border-2 border-dashed transition-all duration-300 flex flex-col items-center justify-center gap-2"
                :class="[
                    isDragging 
                        ? 'bg-brand-primary/10 border-brand-primary' 
                        : 'bg-white/2 border-white/10 hover:border-brand-primary/50'
                ]"
            >
                <div class="size-10 rounded-xl bg-white/5 flex items-center justify-center text-muted-foreground group-hover:text-brand-primary transition-colors">
                    <Upload class="h-5 w-5" />
                </div>
                <div class="text-center">
                    <p class="text-xs font-bold text-white">Drop image here or click to browse</p>
                    <p v-if="description" class="text-[10px] text-muted-foreground">{{ description }}</p>
                </div>
            </div>

            <!-- Preview Area -->
            <div
                v-if="modelValue && !isUploading"
                class="relative h-32 rounded-2xl border border-white/10 overflow-hidden bg-black/40 group"
            >
                <img :src="modelValue" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105" />
                <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-3">
                    <button 
                        @click="fileInput?.click()"
                        type="button"
                        class="size-10 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-all"
                    >
                        <Upload class="h-4 w-4" />
                    </button>
                    <button 
                        @click="clearImage"
                        type="button"
                        class="size-10 rounded-xl bg-red-500/20 hover:bg-red-500/40 text-red-500 flex items-center justify-center transition-all"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Loading State -->
            <div
                v-if="isUploading"
                class="h-32 rounded-2xl border-2 border-white/5 bg-white/2 flex flex-col items-center justify-center gap-2"
            >
                <Loader2 class="h-6 w-6 text-brand-primary animate-spin" />
                <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground animate-pulse">Uploading...</p>
            </div>
        </div>

        <!-- Error Message -->
        <p v-if="error" class="text-[10px] font-bold text-red-400 flex items-center gap-1">
            <AlertCircle class="h-3 w-3" />
            {{ error }}
        </p>
    </div>
</template>
