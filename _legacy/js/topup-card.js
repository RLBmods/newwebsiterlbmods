class CardPaymentHandler {
    constructor(topupSystem) {
        this.topupSystem = topupSystem;
        this.state = {
            paymentWindow: null,
            checkInterval: null,
            attempts: 0,
            maxAttempts: 30
        };
    }

    initiatePayment(redirectUrl) {
        // Open payment window
        this.state.paymentWindow = window.open(redirectUrl, '_blank');
        
        // Start checking payment status after 5 seconds
        setTimeout(() => {
            this.state.checkInterval = setInterval(() => this.checkPaymentStatus(), 2000);
        }, 5000);
    }

    async checkPaymentStatus() {
        this.state.attempts++;
        
        if (this.state.attempts > this.state.maxAttempts) {
            clearInterval(this.state.checkInterval);
            this.topupSystem.showError('Payment verification timeout. Please check your balance later.');
            return;
        }
        
        try {
            const response = await fetch('/api/topup/card/?action=check_status', {
                headers: { 'Client-Key': this.topupSystem.config.clientKey }
            });
            const data = await response.json();
            
            if (data.success) {
                switch(data.data.status) {
                    case 'completed':
                        clearInterval(this.state.checkInterval);
                        this.topupSystem.showSuccess();
                        break;
                    case 'failed':
                        clearInterval(this.state.checkInterval);
                        this.topupSystem.showError('Payment failed. Please try again.');
                        break;
                    case 'pending':
                        // Still processing
                        break;
                    default:
                        clearInterval(this.state.checkInterval);
                        this.topupSystem.showError('Unexpected payment status');
                }
            } else {
                this.topupSystem.showError(data.message || 'Failed to check payment status');
            }
        } catch (error) {
            console.error('Status check error:', error);
            this.topupSystem.showError('Connection error while checking status');
        }
    }

    cleanup() {
        if (this.state.checkInterval) {
            clearInterval(this.state.checkInterval);
        }
        if (this.state.paymentWindow) {
            this.state.paymentWindow.close();
        }
    }
}