import { ref, computed, watch, onMounted } from 'vue';

export interface CartItem {
    id: number;
    productId: number;
    priceId: number;
    name: string;
    productName: string;
    optionName: string; // e.g. "1 Day", "1 Month"
    price: number;
    quantity: number;
    image: string;
    game: string;
}

const cart = ref<CartItem[]>([]);

export function useCart() {
    // Load cart from localStorage
    const loadCart = () => {
        const storedCart = localStorage.getItem('rlb_cart');
        if (storedCart) {
            try {
                cart.value = JSON.parse(storedCart);
            } catch (e) {
                console.error('Failed to parse cart from localStorage', e);
                cart.value = [];
            }
        }
    };

    // Save cart to localStorage
    const saveCart = () => {
        localStorage.setItem('rlb_cart', JSON.stringify(cart.value));
    };

    const addToCart = (item: Omit<CartItem, 'id' | 'quantity'>) => {
        const existingItem = cart.value.find(
            i => i.productId === item.productId && i.priceId === item.priceId
        );

        if (existingItem) {
            existingItem.quantity += 1;
        } else {
            cart.value.push({
                ...item,
                id: Date.now(),
                quantity: 1
            });
        }
        saveCart();
    };

    const removeFromCart = (id: number) => {
        const index = cart.value.findIndex(i => i.id === id);
        if (index !== -1) {
            cart.value.splice(index, 1);
            saveCart();
        }
    };

    const updateQuantity = (id: number, quantity: number) => {
        const item = cart.value.find(i => i.id === id);
        if (item) {
            item.quantity = Math.max(1, quantity);
            saveCart();
        }
    };

    const clearCart = () => {
        cart.value = [];
        saveCart();
    };

    const cartCount = computed(() => {
        return cart.value.reduce((acc, item) => acc + item.quantity, 0);
    });

    const cartTotal = computed(() => {
        return cart.value.reduce((acc, item) => acc + (item.price * item.quantity), 0);
    });

    // Initialize cart on mount if count is 0
    onMounted(() => {
        if (cart.value.length === 0) {
            loadCart();
        }
    });

    return {
        cart,
        addToCart,
        removeFromCart,
        updateQuantity,
        clearCart,
        cartCount,
        cartTotal
    };
}
