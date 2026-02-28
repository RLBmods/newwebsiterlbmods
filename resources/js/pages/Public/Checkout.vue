<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import FrontLayout from '@/layouts/FrontLayout.vue';
import { 
    ShoppingCart, 
    ShieldCheck, 
    CreditCard, 
    Lock, 
    ArrowRight, 
    UserPlus, 
    LogIn,
    ChevronLeft,
    Trash2,
    Package
} from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { useCart } from '@/composables/useCart';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';

const { cart, cartTotal, cartCount, removeFromCart, clearCart } = useCart();
const page = usePage();
const toast = useToast();

const user = computed(() => page.props.auth.user);

const cryptoStatus = computed<'success' | 'cancel' | null>(() => {
    const url = page.url as string;
    const queryStart = url.indexOf('?');
    if (queryStart === -1) return null;
    const params = new URLSearchParams(url.slice(queryStart + 1));
    const val = params.get('crypto');
    if (val === 'success' || val === 'cancel') {
        return val;
    }
    return null;
});

const form = useForm({
    items: cart.value.map(item => ({
        productId: item.productId,
        priceId: item.priceId,
        quantity: item.quantity
    }))
});

const paymentMethod = ref<'balance' | 'paytabs' | 'nowpayments'>('balance');

const handleCheckout = () => {
    if (cart.value.length === 0) {
        toast.error('Your cart is empty.');
        return;
    }

    if (paymentMethod.value === 'paytabs') {
        handlePaytabsCheckout();
        return;
    }
    if (paymentMethod.value === 'nowpayments') {
        handleNowPaymentsCheckout();
        return;
    }

    if (!user.value) {
        toast.error('Please sign in to complete your purchase');
        return;
    }

    form.transform((data) => ({
        ...data,
        items: cart.value.map(item => ({
            productId: item.productId,
            priceId: item.priceId,
            quantity: item.quantity
        }))
    })).post('/checkout/process', {
        onSuccess: () => {
            clearCart();
            toast.success('Thank you for your purchase!');
        },
        onError: (errors) => {
            const message = errors.message || 'Failed to process order. Please try again.';
            toast.error(message);
        }
    });
};

const handlePaytabsCheckout = () => {
    if (!user.value) {
        toast.error('Please sign in to complete your purchase');
        return;
    }

    form.transform((data) => ({
        ...data,
        items: cart.value.map(item => ({
            productId: item.productId,
            priceId: item.priceId,
            quantity: item.quantity
        }))
    })).post('/checkout/paytabs', {
        onError: (errors) => {
            toast.error(errors.message || 'Failed to initiate payment.');
        }
    });
};

const handleNowPaymentsCheckout = () => {
    if (!user.value) {
        toast.error('Please sign in to complete your purchase');
        return;
    }

    form.transform((data) => ({
        ...data,
        items: cart.value.map(item => ({
            productId: item.productId,
            priceId: item.priceId,
            quantity: item.quantity
        }))
    })).post('/checkout/nowpayments', {
        onError: (errors) => {
            toast.error(errors.message || 'Failed to initiate crypto payment.');
        }
    });
};
</script>

<template>
    <Head title="Checkout - RLBMODS" />

    <FrontLayout>
        <section class="py-32 px-6">
            <div class="max-w-7xl mx-auto">
                <div v-if="cryptoStatus" class="mb-6">
                    <div
                        v-if="cryptoStatus === 'success'"
                        class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100 flex items-center justify-between gap-4"
                    >
                        <span class="font-medium">
                            Crypto payment initiated. It may take a few minutes for confirmations. Your purchases will appear automatically once complete.
                        </span>
                        <Link href="/purchases" class="text-xs font-bold uppercase tracking-widest underline">
                            View Orders
                        </Link>
                    </div>
                    <div
                        v-else
                        class="rounded-2xl border border-red-500/40 bg-red-500/10 px-4 py-3 text-sm text-red-100"
                    >
                        <span class="font-medium">
                            Crypto payment was cancelled or did not complete. You can try again or choose another payment method.
                        </span>
                    </div>
                </div>

                <div class="flex items-center gap-4 mb-16">
                    <Link href="/shop" class="group flex items-center gap-2 text-muted-foreground hover:text-white transition-colors">
                        <ChevronLeft class="size-4 group-hover:-translate-x-1 transition-transform" />
                        <span class="text-xs font-black uppercase tracking-widest">Back to Shop</span>
                    </Link>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-12 gap-16 items-start">
                    
                    <!-- Left: Order Summary -->
                    <div class="lg:col-span-7 space-y-10">
                        <div>
                            <h1 class="text-5xl font-black text-white tracking-tighter uppercase italic mb-4">Checkout</h1>
                            <p class="text-muted-foreground font-medium">Review your order before proceeding to payment.</p>
                        </div>

                        <div class="space-y-4">
                            <div v-if="cart.length === 0" class="py-20 text-center bg-white/5 rounded-[3rem] border border-dashed border-white/10">
                                <div class="size-16 rounded-2xl bg-white/5 flex items-center justify-center text-muted-foreground/30 mx-auto mb-6">
                                    <ShoppingCart class="size-8" />
                                </div>
                                <p class="text-sm font-bold text-white uppercase tracking-widest mb-6">Your cart is empty</p>
                                <Link href="/shop">
                                    <Button class="bg-brand-primary hover:bg-brand-primary/80 text-white rounded-xl font-black uppercase text-xs tracking-widest px-8">
                                        Browse Products
                                    </Button>
                                </Link>
                            </div>

                            <div v-for="item in cart" :key="item.id" class="flex items-center gap-6 p-6 rounded-[2.5rem] bg-white/2 border border-white/5 group hover:bg-white/5 transition-all">
                                <div class="size-24 rounded-2xl overflow-hidden shrink-0 border border-white/5 bg-black/40">
                                    <img :src="item.image" class="w-full h-full object-cover" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between gap-4 mb-2">
                                        <h3 class="text-xl font-black text-white truncate uppercase italic">{{ item.productName }}</h3>
                                        <button @click="removeFromCart(item.id)" class="text-muted-foreground/20 hover:text-red-500 transition-colors">
                                            <Trash2 class="size-4" />
                                        </button>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-[10px] font-black uppercase tracking-widest text-brand-primary border border-brand-primary/20 px-2 py-0.5 rounded-lg bg-brand-primary/5">
                                            {{ item.optionName }}
                                        </span>
                                        <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                                            Qty: {{ item.quantity }}
                                        </span>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <p class="text-lg font-black text-white italic">${{ (item.price * item.quantity).toFixed(2) }}</p>
                                </div>
                            </div>
                        </div>

                        <div v-if="cart.length > 0" class="p-10 rounded-[3rem] bg-white/2 border border-white/5 space-y-6">
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-xs font-black uppercase tracking-widest">Subtotal</span>
                                <span class="text-sm font-bold">${{ cartTotal.toFixed(2) }}</span>
                            </div>
                            <div class="flex items-center justify-between text-muted-foreground">
                                <span class="text-xs font-black uppercase tracking-widest">Processing Fee</span>
                                <span class="text-sm font-bold">$0.00</span>
                            </div>
                            <div class="h-px bg-white/5"></div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm font-black uppercase tracking-widest text-white">Grand Total</span>
                                <span class="text-4xl font-black text-brand-primary italic">${{ cartTotal.toFixed(2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Right: Auth Wall / Payment -->
                    <div class="lg:col-span-5">
                        <div class="sticky top-32 space-y-8">
                            
                            <!-- If Guest: Auth Wall -->
                            <div v-if="!user" class="p-10 rounded-[4rem] bg-[#0A0A0A] border border-brand-primary/20 shadow-2xl relative overflow-hidden group">
                                <div class="absolute -top-32 -right-32 size-64 bg-brand-primary/10 blur-[100px] rounded-full"></div>
                                
                                <div class="size-16 rounded-[2rem] bg-brand-primary/10 border border-brand-primary/20 flex items-center justify-center text-brand-primary mb-8 relative z-10">
                                    <Lock class="size-8" />
                                </div>

                                <h2 class="text-3xl font-black text-white tracking-tighter uppercase italic mb-6 relative z-10">Sign in to checkout</h2>
                                <p class="text-muted-foreground font-medium mb-10 relative z-10 leading-relaxed">
                                    You need an account to manage your licenses, receive automated delivery, and access technical support.
                                </p>

                                <div class="space-y-4 relative z-10">
                                    <Link href="/login?redirect=/checkout">
                                        <Button class="w-full h-16 rounded-[2rem] bg-white/5 hover:bg-white/10 text-white font-black uppercase text-xs tracking-widest border border-white/10 transition-all flex items-center justify-center gap-3">
                                            <LogIn class="size-4" />
                                            Already a user? Login
                                        </Button>
                                    </Link>
                                    <Link href="/register?redirect=/checkout">
                                        <Button class="w-full h-16 rounded-[2rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-xs tracking-widest border-b-4 border-brand-primary/50 transition-all flex items-center justify-center gap-3">
                                            <UserPlus class="size-4" />
                                            New here? Create Account
                                        </Button>
                                    </Link>
                                </div>
                            </div>

                            <!-- If Auth: Payment Selection -->
                            <div v-else class="p-10 rounded-[4rem] bg-[#0A0A0A] border border-white/5 shadow-2xl relative overflow-hidden group">
                                <div class="absolute -top-32 -right-32 size-64 bg-emerald-500/10 blur-[100px] rounded-full"></div>

                                <div class="flex items-center gap-4 mb-10 relative z-10">
                                    <div class="size-12 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400">
                                        <ShieldCheck class="size-6" />
                                    </div>
                                    <div>
                                        <p class="text-[10px] font-black uppercase tracking-widest text-muted-foreground">Logged in as</p>
                                        <p class="text-sm font-black text-white">{{ user.name }}</p>
                                    </div>
                                </div>

                                <h2 class="text-2xl font-black text-white tracking-tighter uppercase italic mb-8 relative z-10">Payment Method</h2>

                                <div class="space-y-4 mb-10 relative z-10">
                                    <div
                                        class="p-6 rounded-[2.5rem] border flex items-center justify-between cursor-pointer transition"
                                        :class="paymentMethod === 'balance' ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-white/2 border-white/10 hover:border-white/30'"
                                        @click="paymentMethod = 'balance'"
                                    >
                                        <div class="flex items-center gap-4">
                                            <div class="size-10 rounded-xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-emerald-400">
                                                <CreditCard class="size-5" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-black text-white uppercase tracking-tight">Account Balance</span>
                                                <span class="text-[10px] font-black text-emerald-400 uppercase tracking-widest">Available</span>
                                            </div>
                                        </div>
                                        <div class="text-xl font-black text-white italic">
                                            ${{ Number(user.balance || 0).toFixed(2) }}
                                        </div>
                                    </div>
                                    <div
                                        class="p-6 rounded-[2.5rem] border flex items-center justify-between cursor-pointer transition"
                                        :class="paymentMethod === 'paytabs' ? 'bg-brand-primary/10 border-brand-primary/40' : 'bg-white/2 border-white/10 hover:border-white/30'"
                                        @click="paymentMethod = 'paytabs'"
                                    >
                                        <div class="flex items-center gap-4">
                                            <div class="size-10 rounded-xl bg-brand-primary/20 border border-brand-primary/50 flex items-center justify-center text-brand-primary">
                                                <CreditCard class="size-5" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-black text-white uppercase tracking-tight">Card & Wallet</span>
                                                <span class="text-[10px] font-black text-muted-foreground uppercase tracking-widest">PayTabs • Card, Apple Pay, Google Pay</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div
                                        class="p-6 rounded-[2.5rem] border flex items-center justify-between cursor-pointer transition"
                                        :class="paymentMethod === 'nowpayments' ? 'bg-white/5 border-white/40' : 'bg-white/2 border-white/10 hover:border-white/30'"
                                        @click="paymentMethod = 'nowpayments'"
                                    >
                                        <div class="flex items-center gap-4">
                                            <div class="size-10 rounded-xl bg-white/5 border border-white/10 flex items-center justify-center text-white">
                                                <CreditCard class="size-5" />
                                            </div>
                                            <div class="flex flex-col">
                                                <span class="text-sm font-black text-white uppercase tracking-tight">Crypto</span>
                                                <span class="text-[10px] font-black text-muted-foreground uppercase tracking-widest">NOWPayments • All coins supported</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <Button 
                                    @click="handleCheckout"
                                    :disabled="cart.length === 0 || form.processing"
                                    class="w-full h-20 rounded-[2.5rem] bg-brand-primary hover:bg-brand-primary/80 text-white font-black uppercase text-lg tracking-widest border-b-8 border-brand-primary/50 hover:border-b-4 active:border-b-0 active:translate-y-[4px] transition-all flex items-center justify-center gap-4 relative z-10"
                                >
                                    <ShoppingCart class="size-6" />
                                    <span v-if="form.processing">Processing...</span>
                                    <span v-else>
                                        {{
                                            paymentMethod === 'balance'
                                                ? 'Place Order with Balance'
                                                : paymentMethod === 'paytabs'
                                                    ? 'Pay Securely with Card / Wallet'
                                                    : 'Pay with Crypto'
                                        }}
                                    </span>
                                </Button>
                            </div>

                            <!-- Trust Badges -->
                            <div class="grid grid-cols-2 gap-4">
                                <div class="bg-white/2 border border-white/5 p-6 rounded-[2rem] flex flex-col items-center text-center gap-2">
                                    <Lock class="size-5 text-muted-foreground/40" />
                                    <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Secure SSL</span>
                                </div>
                                <div class="bg-white/2 border border-white/5 p-6 rounded-[2rem] flex flex-col items-center text-center gap-2">
                                    <Package class="size-5 text-muted-foreground/40" />
                                    <span class="text-[10px] font-black uppercase tracking-widest text-muted-foreground/60">Instant Delivery</span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>
    </FrontLayout>
</template>
